<?php

namespace Chargefield\Store\Filament\Resources;

use Chargefield\Store\Filament\Resources\StockistResource\Pages;
use Chargefield\Store\Store;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class StockistResource extends Resource
{
    public static function getModel(): string
    {
        return Store::model('stockist');
    }

    public static function getNavigationGroup(): ?string
    {
        return config('store.navigation.stockists');
    }

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (string $state, Forms\Set $set, string $operation) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
            Forms\Components\TextInput::make('slug')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Forms\Components\FileUpload::make('logo')
                ->label('Logo')
                ->disk('public')
                ->directory('uploads/stockists')
                ->visibility('public')
                ->image()
                ->helperText('Shows small and centered above each edition this stockist offers.')
                ->columnSpanFull(),
            Forms\Components\TextInput::make('url')
                ->label('Website')
                ->url(),
            Forms\Components\TextInput::make('position')
                ->numeric()
                ->default(0)
                ->helperText('Lower numbers appear first.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('position')
            ->defaultSort('position')
            ->columns([
                Tables\Columns\ImageColumn::make('logo')
                    ->label('Logo')
                    // logoUrl() is a root-relative path (/assets/stockists/…); make it an
                    // absolute URL so Filament renders it directly rather than through the
                    // storage disk (which would 404).
                    ->getStateUsing(function ($record) {
                        $url = $record->logoUrl();

                        return $url && ! Str::startsWith($url, ['http://', 'https://']) ? url($url) : $url;
                    })
                    ->height(34),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                // Only for sites whose Stockist has an editions() relation
                // (johngodfrey); a site without one would error on the count.
                ...(method_exists(static::getModel(), 'editions') ? [
                    Tables\Columns\TextColumn::make('editions_count')
                        ->label('Editions')
                        ->counts('editions'),
                ] : []),
                Tables\Columns\TextColumn::make('url')
                    ->label('Website')
                    ->url(fn ($record) => $record->url, true)
                    ->placeholder('—'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockists::route('/'),
            'create' => Pages\CreateStockist::route('/create'),
            'edit' => Pages\EditStockist::route('/{record}/edit'),
        ];
    }
}
