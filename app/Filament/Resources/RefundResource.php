<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RefundResource\Pages;
use App\Models\Refund;
use App\Models\SiteSetting;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RefundResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('退款');
    }

    public static function getPluralModelLabel(): string
    {
        return __('退款');
    }

    protected static ?string $model = Refund::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';
    protected static ?int $navigationSort = 5;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.orders');
    }



    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->label(__('admin.common.id'))->sortable(),
                Tables\Columns\TextColumn::make('order.id')
                    ->label(__('admin.refund.order'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('order.product.name')
                    ->label(__('admin.refund.product'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('order.tenant.name')
                    ->label(__('admin.refund.developer')),
                Tables\Columns\TextColumn::make('amount')->label(__('admin.common.amount'))
                    ->prefix(SiteSetting::get('currency_symbol', '¥'))
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('reason')
                    ->limit(50),
                Tables\Columns\TextColumn::make('status')->label(__('admin.common.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending'   => 'warning',
                        'failed'    => 'danger',
                        default     => 'gray',
                    }),
                Tables\Columns\TextColumn::make('stripe_refund_id')
                    ->label(__('admin.refund.stripe_refund'))
                    ->default('-'),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.common.created_at'))
                    ->dateTime(timezone: SiteSetting::get('timezone', 'Asia/Shanghai'))
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'completed' => __('admin.refund.opt.completed'),
                        'pending'   => __('admin.order.opt.pending'),
                        'failed'    => __('admin.order.opt.failed'),
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRefunds::route('/'),
        ];
    }
}
