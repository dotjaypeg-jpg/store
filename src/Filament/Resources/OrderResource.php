<?php

namespace Chargefield\Store\Filament\Resources;

use Chargefield\Store\Filament\Resources\OrderResource\Pages;
use Chargefield\Store\Store;
use Filament\Forms\Form;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    public static function getModel(): string
    {
        return Store::model('order');
    }

    public static function getNavigationGroup(): ?string
    {
        return config('store.navigation.orders');
    }

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?int $navigationSort = 1;

    // Orders are created by checkout — never hand-authored here.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        // Count this store's own paid orders — historical Shopify sales shouldn't
        // dominate the badge.
        return (string) static::getModel()::where('source', 'store')->where('status', 'paid')->count();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order')
                    ->searchable()
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state) => $state === 'shopify' ? 'gray' : 'info'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'refunded' => 'gray',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('total')
                    ->money(fn ($record) => $record->currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('placed_at')
                    ->label('Placed')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->placeholder('—')
                    ->description(fn ($record) => $record->placed_at ? null : 'created '.$record->created_at->format('M j, Y')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('source')
                    ->options([
                        'store' => 'This store',
                        'shopify' => 'Shopify (historical)',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'refunded' => 'Refunded',
                        'cancelled' => 'Cancelled',
                        'failed' => 'Failed',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Order')->columns(3)->schema([
                TextEntry::make('order_number')->label('Order'),
                TextEntry::make('source')->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->color(fn (string $state) => $state === 'shopify' ? 'gray' : 'info'),
                TextEntry::make('status')->badge()->color(fn (string $state) => match ($state) {
                    'paid' => 'success',
                    'pending' => 'warning',
                    'refunded' => 'gray',
                    default => 'danger',
                }),
                TextEntry::make('placed_at')->label('Placed')->dateTime()->placeholder('—'),
                TextEntry::make('created_at')->label('Recorded')->dateTime(),
                TextEntry::make('email')->placeholder('—'),
                TextEntry::make('customer_name')->label('Name')->placeholder('—'),
                TextEntry::make('ships_to')
                    ->label('Ships to')
                    ->placeholder('—')
                    // Compute a one-line string via ->state() so Filament never renders the
                    // raw shipping_address array (that throws "array to string conversion").
                    ->state(function ($record) {
                        $a = (array) $record->shipping_address;
                        $inner = is_array($a['address'] ?? null) ? $a['address'] : [];
                        $get = fn (...$keys) => collect($keys)->map(fn ($k) => $a[$k] ?? $inner[$k] ?? null)->first(fn ($v) => filled($v));

                        return collect([
                            $get('address1', 'line1'), $get('line2'),
                            $get('city'), $get('province', 'state'), $get('zip', 'postal_code'), $get('country'),
                        ])->filter()->join(', ') ?: null;
                    }),
                TextEntry::make('pii_redacted_at')
                    ->label('Personal data')
                    ->badge()
                    ->placeholder('Full details retained')
                    ->color(fn ($state) => $state ? 'gray' : 'info')
                    ->formatStateUsing(fn ($state) => 'Reduced '.$state->format('M j, Y')),
                TextEntry::make('subtotal')->money(fn ($record) => $record->currency),
                TextEntry::make('shipping_total')->label('Shipping')->money(fn ($record) => $record->currency),
                TextEntry::make('total')->money(fn ($record) => $record->currency)->weight('bold'),
            ]),
            Section::make('Items')->schema([
                RepeatableEntry::make('items')->schema([
                    // Links to the product page when the product still exists (matched
                    // by id, else by title for historical imports); plain text if it's
                    // been removed. Opens the storefront in a new tab.
                    TextEntry::make('title')
                        ->url(fn ($record) => $record->productUrl())
                        ->openUrlInNewTab()
                        ->color(fn ($record) => $record->productUrl() ? 'primary' : null)
                        ->tooltip(fn ($record) => $record->productUrl() ? 'View product' : 'Product no longer exists'),
                    TextEntry::make('variant_title')->label('Variant')->placeholder('—'),
                    TextEntry::make('quantity')->label('Qty'),
                    TextEntry::make('price')->money(fn () => Store::currency()),
                    TextEntry::make('sku')->label('Gelato UID')->placeholder('—'),
                ])->columns(5),
            ]),
            Section::make('Fulfilment')
                ->description('One entry per print provider this order was routed to.')
                ->visible(fn ($record) => $record->fulfillments()->exists())
                ->schema([
                    RepeatableEntry::make('fulfillments')->schema([
                        TextEntry::make('provider')->badge(),
                        TextEntry::make('status')->badge()->color(fn (string $state) => match ($state) {
                            'shipped', 'delivered' => 'success',
                            'failed', 'cancelled' => 'danger',
                            'draft' => 'gray',
                            default => 'warning',
                        }),
                        TextEntry::make('provider_order_id')->label('Provider order')->placeholder('—'),
                        TextEntry::make('tracking_url')->label('Tracking')->placeholder('—')
                            ->url(fn ($state) => $state ?: null, true),
                    ])->columns(4),
                ]),

            Section::make('Shipping address')
                ->visible(fn ($record) => filled($record->shipping_address))
                ->schema([
                    TextEntry::make('shipping_address_full')
                        ->label('')
                        ->state(fn ($record) => is_array($record->shipping_address)
                            ? json_encode($record->shipping_address, JSON_PRETTY_PRINT)
                            : (string) $record->shipping_address),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
