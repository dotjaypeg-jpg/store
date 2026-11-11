<?php

namespace Chargefield\Store\Filament\Resources;

use Chargefield\Store\Filament\Resources\PricingRuleResource\Pages;
use Chargefield\Store\Models\PricingRule;
use Chargefield\Store\Store;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;

class PricingRuleResource extends Resource
{
    public static function getModel(): string
    {
        return Store::model('pricing_rule');
    }

    public static function getNavigationGroup(): ?string
    {
        return config('store.navigation.pricing');
    }

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Pricing';

    protected static ?string $modelLabel = 'price rule';

    protected static ?string $slug = 'pricing';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('category')
                ->options(fn () => Store::productCategories())
                ->required()
                ->native(false),
            Forms\Components\TextInput::make('size')
                ->helperText('The variant size this price applies to (e.g. 60x90 cm / 24x36″). Leave blank to price every size the same.')
                ->placeholder('Any size'),
            Forms\Components\TextInput::make('price')
                ->numeric()
                ->prefix('$')
                ->required()
                ->helperText('The sell price. Every product in this category/size uses it unless it has a custom price.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Group by category so "Poster" shows once as a header with its sizes
            // nested under it, instead of the badge repeating on every size row.
            ->groups([
                Group::make('category')
                    ->titlePrefixedWithLabel(false)
                    ->getTitleFromRecordUsing(fn (PricingRule $r) => ucfirst((string) $r->category)),
            ])
            ->defaultGroup('category')
            ->defaultSort('price')
            ->columns([
                Tables\Columns\TextColumn::make('size')
                    ->placeholder('Any size'),
                Tables\Columns\TextInputColumn::make('price')
                    ->rules(['numeric', 'min:0'])
                    ->type('number')
                    ->label('Sell price ($)'),
                Tables\Columns\TextColumn::make('maxCost')
                    ->label('Highest cost')
                    ->state(fn (PricingRule $r) => $r->maxCost())
                    ->money(fn () => Store::currency())
                    ->placeholder('—')
                    ->description('what we pay'),
                Tables\Columns\IconColumn::make('margin')
                    ->label('Margin')
                    ->state(fn (PricingRule $r) => ! $r->isBelowCost())
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(fn (PricingRule $r) => $r->isBelowCost()
                        ? 'Below cost! This price is lower than what the provider charges.'
                        : 'Priced above cost.'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('No price rules yet')
            ->emptyStateDescription('Run "php artisan pricing:build" to seed the book from current prices, or add rules here.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPricingRules::route('/'),
            'create' => Pages\CreatePricingRule::route('/create'),
            'edit' => Pages\EditPricingRule::route('/{record}/edit'),
        ];
    }
}
