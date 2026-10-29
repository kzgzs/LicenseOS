<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayoutResource\Pages;
use App\Models\Payout;
use App\Models\SiteSetting;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;

class PayoutResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('提现');
    }

    public static function getPluralModelLabel(): string
    {
        return __('提现');
    }

    protected static ?string $model = Payout::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?int $navigationSort = 3;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.finance');
    }



    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->label(__('admin.common.id'))->sortable(),
                Tables\Columns\TextColumn::make('tenant.name')->searchable()->label(__('admin.payout.developer')),
                Tables\Columns\TextColumn::make('amount')->label(__('admin.common.amount'))
                    ->prefix(SiteSetting::get('currency_symbol', '¥'))
                    ->numeric(decimalPlaces: 2),
                Tables\Columns\TextColumn::make('method')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'stripe' => 'info',
                        'alipay' => 'success',
                        'wechat' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('tenant.alipay_account')
                    ->label(__('admin.payout.alipay'))
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('tenant.wechat_account')
                    ->label(__('admin.payout.wechat'))
                    ->placeholder('-')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge()->label(__('admin.common.status'))->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'processing' => 'info',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.common.created_at'))
                    ->dateTime(timezone: SiteSetting::get('timezone', 'Asia/Shanghai'))
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['pending' => __('admin.order.opt.pending'), 'processing' => __('admin.payout.opt.processing'), 'paid' => __('admin.payout.opt.paid'), 'failed' => __('admin.payout.opt.failed')]),
                Tables\Filters\SelectFilter::make('method')
                    ->options(['stripe' => 'Stripe', 'alipay' => 'Alipay', 'wechat' => 'WeChat']),
            ])
            ->actions([
                Action::make('process')
                    ->visible(fn (Payout $record) => $record->status === 'pending')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (Payout $record) {
                        $record->markProcessing();
                    }),
                Action::make('mark_paid')
                    ->visible(fn (Payout $record) => $record->status === 'processing')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Payout $record) {
                        $record->markPaid();
                    }),
                Action::make('mark_failed')
                    ->visible(fn (Payout $record) => in_array($record->status, ['pending', 'processing']))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Payout $record) {
                        $record->markFailed();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayouts::route('/'),
        ];
    }
}
