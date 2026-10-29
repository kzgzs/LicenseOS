<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Models\SiteSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('订单');
    }

    public static function getPluralModelLabel(): string
    {
        return __('订单');
    }

    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?int $navigationSort = 1;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.orders');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('tenant_id')
                ->relationship('tenant', 'name')->required(),
            Forms\Components\Select::make('product_id')
                ->relationship('product', 'name')->required(),
            Forms\Components\TextInput::make('amount')->label(__('admin.order.amount'))->numeric()->required(),
            Forms\Components\Select::make('status')
                ->options(['pending' => __('admin.order.opt.pending'), 'paid' => __('admin.order.opt.paid'), 'failed' => __('admin.order.opt.failed'), 'refunded' => __('admin.order.opt.refunded')])
                ->default('pending'),
            Forms\Components\TextInput::make('payment_method')->label(__('admin.order.payment_method')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->label(__('admin.common.id'))->sortable(),
                Tables\Columns\TextColumn::make('tenant.name')->searchable(),
                Tables\Columns\TextColumn::make('product.name')->searchable(),
                Tables\Columns\TextColumn::make('amount')->label(__('admin.common.amount'))
                    ->prefix(SiteSetting::get('currency_symbol', '¥'))
                    ->numeric(decimalPlaces: 2),
                Tables\Columns\TextColumn::make('status')->label(__('admin.common.status'))->label(__('admin.common.status'))->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success', 'pending' => 'warning',
                        'failed' => 'danger', 'refunded' => 'gray', default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('payment_method'),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.common.created_at'))
                    ->dateTime(timezone: SiteSetting::get('timezone', 'Asia/Shanghai'))
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['pending' => __('admin.order.opt.pending'), 'paid' => __('admin.order.opt.paid'), 'failed' => __('admin.order.opt.failed'), 'refunded' => __('admin.order.opt.refunded')]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
        ];
    }
}
