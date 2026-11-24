<?php

namespace Chargefield\Store\Filament\Resources;

use Chargefield\Store\Filament\Resources\BlankResource\Pages;
use Chargefield\Store\Store;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Stock / blank definitions (tees, paper stocks). One place to edit the
 * material blurb, wash-care and size guide shown on every product that uses the
 * blank — so product-page spec/care is data, never hardcoded. Seed the shared
 * defaults with `php artisan store:seed-blanks`.
 */
class BlankResource extends Resource
{
    public static function getModel(): string
    {
        return Store::model('blank');
    }

    public static function getNavigationGroup(): ?string
    {
        return config('store.navigation.blanks', config('store.navigation.pricing'));
    }

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'stock / blank';

    /** @return array<string, string> */
    protected static function categoryOptions(): array
    {
        return Store::productCategories();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->helperText('e.g. Heavyweight Cotton Tee'),
                    Forms\Components\Select::make('category')
                        ->options(fn () => static::categoryOptions())
                        ->required()
                        ->native(false),
                    Forms\Components\Toggle::make('is_default')
                        ->label('Default for this category')
                        ->helperText('Products in this category with no blank set inherit this one.'),
                    Forms\Components\Textarea::make('blurb')
                        ->label('Material / spec')
                        ->rows(3)
                        ->columnSpanFull()
                        ->helperText('The garment or stock details — fabric, weight, fit, print method.'),
                ]),

            Forms\Components\Section::make('Care')
                ->description('Wash & care guidance shown on the product page.')
                ->schema([
                    Forms\Components\Repeater::make('care')
                        ->hiddenLabel()
                        ->schema([
                            Forms\Components\TextInput::make('title')->placeholder('Wash')->required(),
                            Forms\Components\TextInput::make('body')->placeholder('Machine wash cold, inside out.')->required(),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Add care line'),
                ]),

            Forms\Components\Section::make('Size guide')
                ->description('Optional. One row per size; add measurement columns as needed.')
                ->schema([
                    Forms\Components\Repeater::make('size_guide')
                        ->hiddenLabel()
                        ->schema([
                            Forms\Components\TextInput::make('label')->placeholder('M')->required(),
                            Forms\Components\TextInput::make('chest')->placeholder('Chest (in)'),
                            Forms\Components\TextInput::make('length')->placeholder('Length (in)'),
                        ])
                        ->columns(3)
                        ->defaultItems(0)
                        ->addActionLabel('Add size'),
                ])
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => static::categoryOptions()[$state] ?? $state),
                Tables\Columns\IconColumn::make('is_default')->boolean()->label('Default'),
                Tables\Columns\TextColumn::make('products_count')->counts('products')->label('Products'),
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
            'index' => Pages\ListBlanks::route('/'),
            'create' => Pages\CreateBlank::route('/create'),
            'edit' => Pages\EditBlank::route('/{record}/edit'),
        ];
    }
}
