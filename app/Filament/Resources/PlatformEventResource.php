<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlatformEventResource\Pages;
use App\Models\PlatformEvent;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlatformEventResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('平台事件');
    }

    public static function getPluralModelLabel(): string
    {
        return __('平台事件');
    }

    protected static ?string $model = PlatformEvent::class;
    protected static ?string $navigationIcon = 'heroicon-o-bell';
    protected static ?int $navigationSort = 1;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.system');
    }



    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->label(__('admin.common.id'))->sortable(),
                Tables\Columns\TextColumn::make('type')->badge()->label(__('admin.common.type'))->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'order.paid' => 'success',
                        'payout.requested' => 'warning',
                        'license.created' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('processed')->label(__('admin.event.processed'))->boolean(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->label(__('admin.common.created_at'))->dateTime()->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'order.paid' => __('admin.event.order_paid'),
                        'payout.requested' => __('admin.event.payout_requested'),
                        'license.created' => __('admin.event.license_created'),
                    ]),
                Tables\Filters\TernaryFilter::make('processed'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlatformEvents::route('/'),
        ];
    }
}
