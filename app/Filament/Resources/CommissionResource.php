<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CommissionResource\Pages;
use App\Models\Commission;
use App\Models\SiteSetting;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CommissionResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('佣金');
    }

    public static function getPluralModelLabel(): string
    {
        return __('佣金');
    }

    protected static ?string $model = Commission::class;
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?int $navigationSort = 2;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.finance');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->label(__('admin.common.id'))->sortable(),
                Tables\Columns\TextColumn::make('order_id'),
                Tables\Columns\TextColumn::make('tenant.name')->searchable(),
                Tables\Columns\TextColumn::make('platform_fee')
                    ->prefix(SiteSetting::get('currency_symbol', '¥'))->numeric(decimalPlaces: 2)->label(__('admin.commission.platform_fee')),
                Tables\Columns\TextColumn::make('developer_income')
                    ->prefix(SiteSetting::get('currency_symbol', '¥'))->numeric(decimalPlaces: 2)->label(__('admin.commission.developer_income')),
                Tables\Columns\TextColumn::make('rate')->suffix('%'),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.common.created_at'))
                    ->dateTime(timezone: SiteSetting::get('timezone', 'Asia/Shanghai'))
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommissions::route('/'),
        ];
    }
}
