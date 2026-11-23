<?php

namespace Chargefield\Store\Filament\Resources;

use Chargefield\Store\Contracts\LabelPurchaser;
use Chargefield\Store\Filament\Resources\OrderResource\Pages;
use Chargefield\Store\Fulfillment\ShipmentRecorder;
use Chargefield\Store\Models\Order;
use Chargefield\Store\Store;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Fulfilment actions for the order view-page header. "Mark shipped" records a
     * shipment (status, tracking, shipped_at) and — via a bound ShipmentNotifier —
     * emails the buyer once; "Buy & print label" creates a real carrier shipment.
     * Aimed at self-shipped ('manual') orders, which no provider webhook ever
     * moves along.
     *
     * @return array<int, Action>
     */
    public static function orderActions(): array
    {
        return [
            Action::make('markShipped')
                ->label('Mark shipped')
                ->icon('heroicon-o-truck')
                ->color('success')
                ->visible(fn (Order $record) => $record->isPaid() && static::shippableProviders($record) !== [])
                ->form([
                    Select::make('provider')
                        ->label('Fulfilment')
                        ->options(fn (Order $record) => static::shippableProviders($record))
                        ->default(fn (Order $record) => array_key_first(static::shippableProviders($record)))
                        // One candidate → no need to choose; the default is submitted.
                        ->visible(fn (Order $record) => count(static::shippableProviders($record)) > 1)
                        ->required(),
                    TextInput::make('tracking_number')->label('Tracking number')->maxLength(255),
                    TextInput::make('tracking_url')->label('Tracking URL')->url()->maxLength(2048)
                        ->helperText('Optional — a link the buyer can follow to track the parcel.'),
                ])
                ->modalSubmitActionLabel('Mark shipped')
                ->action(function (Order $record, array $data) {
                    $provider = $data['provider'] ?? array_key_first(static::shippableProviders($record));
                    app(ShipmentRecorder::class)->record(
                        $record,
                        (string) $provider,
                        filled($data['tracking_number'] ?? null) ? $data['tracking_number'] : null,
                        filled($data['tracking_url'] ?? null) ? $data['tracking_url'] : null,
                    );
                    Notification::make()
                        ->title('Marked shipped'.(filled($record->email) ? ' — buyer emailed' : ''))
                        ->success()
                        ->send();
                }),
            Action::make('buyLabel')
                ->label('Buy & print label')
                ->icon('heroicon-o-printer')
                ->color('primary')
                // Only when the host app binds a purchaser and the order has an
                // unshipped self-shipped ('manual') parcel to label.
                ->visible(fn (Order $record) => app()->bound(LabelPurchaser::class)
                    && $record->isPaid()
                    && array_key_exists('manual', static::shippableProviders($record)))
                ->requiresConfirmation()
                ->modalHeading('Buy a shipping label')
                ->modalDescription('Creates a real shipment with the carrier, buys the label, and marks the order shipped. This charges your carrier account.')
                ->modalSubmitActionLabel('Buy label')
                ->action(function (Order $record) {
                    try {
                        app(LabelPurchaser::class)->purchase($record, 'manual');
                    } catch (\Throwable $e) {
                        report($e);
                        Notification::make()->title('Could not buy the label')->body($e->getMessage())->danger()->persistent()->send();

                        return;
                    }
                    Notification::make()->title('Label bought — order marked shipped')->success()->send();
                }),
            Action::make('downloadLabel')
                ->label('Download label')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn (Order $record) => $record->fulfillments()->whereNotNull('label_path')->exists())
                ->action(function (Order $record) {
                    $disk = Storage::disk((string) config('store.labels.disk', 'local'));
                    $fulfillment = $record->fulfillments()->whereNotNull('label_path')->latest('id')->first();

                    if (! $fulfillment || ! $disk->exists((string) $fulfillment->label_path)) {
                        Notification::make()->title('Label file not found')->danger()->send();

                        return null;
                    }

                    return $disk->download(
                        (string) $fulfillment->label_path,
                        'label-'.$record->order_number.'.'.strtolower($fulfillment->label_format ?: 'pdf'),
                    );
                }),
            Action::make('printMailingSlip')
                ->label('Print mailing slip')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                // A stamp-and-mail slip (return address + ship-to) for lettermail —
                // no carrier account needed. Shown when the host app provides the URL.
                ->visible(fn (Order $record) => filled($record->shipping_address)
                    && filled(Store::mailingSlipUrl($record)))
                ->url(fn (Order $record) => Store::mailingSlipUrl($record))
                ->openUrlInNewTab(),
        ];
    }

    /**
     * Providers on this order that can still be marked shipped: each fulfilment
     * not already shipped/delivered, keyed provider => label. When the order has no
     * fulfilment rows at all, offers a self-shipped ('manual') record. Empty when
     * every fulfilment has shipped (so the action hides).
     *
     * @return array<string, string>
     */
    protected static function shippableProviders(Order $record): array
    {
        $providers = $record->fulfillments()
            ->whereNotIn('status', ['shipped', 'delivered'])
            ->orderBy('provider')
            ->pluck('provider')
            ->all();

        if ($providers === [] && ! $record->fulfillments()->exists()) {
            $providers = ['manual'];
        }

        return collect($providers)
            ->mapWithKeys(fn (string $p) => [$p => ucfirst($p).($p === 'manual' ? ' (you ship)' : '')])
            ->all();
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
                        TextEntry::make('shipped_at')->label('Shipped')->dateTime('M j, Y')->placeholder('—'),
                        TextEntry::make('provider_order_id')->label('Provider order')->placeholder('—'),
                        TextEntry::make('tracking_number')->label('Tracking no.')->placeholder('—')->copyable(),
                        TextEntry::make('tracking_url')->label('Tracking link')->placeholder('—')
                            ->url(fn ($state) => $state ?: null, true),
                        TextEntry::make('carrier')->label('Label')->placeholder('—')
                            ->formatStateUsing(fn ($state, $record) => $state
                                ? ucfirst((string) $state).($record->label_cost ? ' · $'.$record->label_cost : '')
                                : '—'),
                    ])->columns(3),
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
