<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Models\Subscription;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('订阅');
    }

    public static function getPluralModelLabel(): string
    {
        return __('订阅');
    }

    protected static ?string $model = Subscription::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?int $navigationSort = 3;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.products');
    }



    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->label(__('admin.common.id'))->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->searchable()
                    ->label(__('admin.subscription.subscriber')),
                Tables\Columns\TextColumn::make('product.name')
                    ->searchable()
                    ->label(__('admin.subscription.product')),
                Tables\Columns\TextColumn::make('status')->label(__('admin.common.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active'   => 'success',
                        'canceled' => 'warning',
                        'expired'  => 'danger',
                        default    => 'gray',
                    }),
                Tables\Columns\TextColumn::make('renew_at')
                    ->dateTime()
                    ->label(__('admin.subscription.next_renewal'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active'   => __('admin.subscription.opt.active'),
                        'canceled' => __('admin.subscription.opt.canceled'),
                        'expired'  => __('admin.subscription.opt.expired'),
                    ]),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
        ];
    }
}
