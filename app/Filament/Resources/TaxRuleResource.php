<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaxRuleResource\Pages;
use App\Models\TaxRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TaxRuleResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('税规');
    }

    public static function getPluralModelLabel(): string
    {
        return __('税规');
    }

    protected static ?string $model = TaxRule::class;
    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.system');
    }



    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('country_code')
                ->required()
                ->maxLength(2)
                ->helperText(__('admin.tax.helper.country')),
            Forms\Components\TextInput::make('state_code')
                ->label(__('admin.tax.state_code'))
                ->nullable()
                ->maxLength(10)
                ->helperText(__('admin.tax.helper.state')),
            Forms\Components\Select::make('tax_type')
                ->label(__('admin.tax.tax_type'))
                ->options(['vat' => __('admin.tax.opt.vat'), 'gst' => __('admin.tax.opt.gst'), 'sales_tax' => __('admin.tax.opt.sales_tax'), 'consumption_tax' => __('admin.tax.opt.consumption_tax')])
                ->required()
                ->helperText(__('admin.tax.helper.tax_type')),
            Forms\Components\TextInput::make('rate')
                ->label(__('admin.tax.rate'))
                ->numeric()
                ->required()
                ->suffix('%')
                ->helperText(__('admin.tax.helper.rate')),
            Forms\Components\TextInput::make('name')
                ->label(__('admin.tax.name'))
                ->required()
                ->helperText(__('admin.tax.helper.name')),
            Forms\Components\Toggle::make('applies_to_digital')
                ->label(__('admin.tax.applies_to_digital'))
                ->default(true)
                ->helperText(__('admin.tax.helper.digital')),
            Forms\Components\Toggle::make('applies_to_business')
                ->label(__('admin.tax.applies_to_business'))
                ->default(false)
                ->helperText(__('admin.tax.helper.business')),
            Forms\Components\Toggle::make('active')
                ->label(__('admin.tax.active'))
                ->default(true)
                ->helperText(__('admin.tax.helper.active')),
            Forms\Components\DatePicker::make('effective_from')
                ->label(__('admin.tax.effective_from'))
                ->nullable()
                ->helperText(__('admin.tax.helper.from')),
            Forms\Components\DatePicker::make('effective_until')
                ->label(__('admin.tax.effective_until'))
                ->nullable()
                ->helperText(__('admin.tax.helper.until')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('country_code'),
                Tables\Columns\TextColumn::make('state_code')->default('-'),
                Tables\Columns\TextColumn::make('name')->searchable()->label(__('admin.common.name'))->searchable(),
                Tables\Columns\TextColumn::make('tax_type')->badge()->color(fn (string $state): string => match ($state) { 'vat' => 'primary', 'gst' => 'success', 'sales_tax' => 'warning', default => 'gray' }),
                Tables\Columns\TextColumn::make('rate')->suffix('%'),
                Tables\Columns\IconColumn::make('active')->boolean()->label(__('admin.common.active'))->boolean(),
            ])
            ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTaxRules::route('/'),
            'create' => Pages\CreateTaxRule::route('/create'),
            'edit' => Pages\EditTaxRule::route('/{record}/edit'),
        ];
    }
}
