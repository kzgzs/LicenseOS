<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdResource\Pages;
use App\Models\Ad;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AdResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('广告活动');
    }

    public static function getPluralModelLabel(): string
    {
        return __('广告活动');
    }

    protected static ?string $model = Ad::class;
    protected static ?string $navigationIcon = 'heroicon-o-megaphone';
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.marketing');
    }



    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')
                ->required()
                ->maxLength(255),
            Forms\Components\Select::make('type')
                ->options([
                    'banner' => __('admin.ad.opt.banner'),
                    'featured' => __('admin.ad.opt.featured'),
                    'search_boost' => __('admin.ad.opt.search_boost'),
                ])
                ->required(),
            Forms\Components\TextInput::make('bid_price')
                ->numeric()
                ->prefix('$')
                ->required(),
            Forms\Components\TextInput::make('daily_budget')
                ->numeric()
                ->prefix('$')
                ->nullable(),
            Forms\Components\DatePicker::make('start_at')->required(),
            Forms\Components\DatePicker::make('end_at')->required(),
            Forms\Components\Select::make('product_id')
                ->relationship('product', 'name')
                ->searchable()
                ->required(),
            Forms\Components\Select::make('tenant_id')
                ->relationship('tenant', 'name')
                ->required(),
            Forms\Components\Select::make('status')
                ->options([
                    'pending' => __('admin.order.opt.pending'),
                    'active' => __('admin.user.opt.active'),
                    'paused' => __('admin.ad.opt.paused'),
                    'expired' => __('admin.subscription.opt.expired'),
                ])
                ->default('pending'),
            Forms\Components\TextInput::make('target_keywords')
                ->maxLength(255),
            Forms\Components\TextInput::make('banner_image')
                ->url()
                ->nullable(),
            Forms\Components\TextInput::make('landing_url')
                ->url()
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->label(__('admin.common.title'))->searchable(),
                Tables\Columns\TextColumn::make('type')->label(__('admin.common.type'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'banner' => 'primary',
                        'featured' => 'success',
                        'search_boost' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('bid_price')->prefix('$')->sortable(),
                Tables\Columns\TextColumn::make('product.name')->label(__('admin.ad.product')),
                Tables\Columns\TextColumn::make('tenant.name')->label(__('admin.ad.developer')),
                Tables\Columns\TextColumn::make('status')->label(__('admin.common.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'active' => 'success',
                        'paused' => 'gray',
                        'expired' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('impressions')->sortable(),
                Tables\Columns\TextColumn::make('clicks')->sortable(),
                Tables\Columns\TextColumn::make('total_spent')->prefix('$')->sortable(),
                Tables\Columns\TextColumn::make('start_at')->date(),
                Tables\Columns\TextColumn::make('end_at')->date(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAds::route('/'),
            'create' => Pages\CreateAd::route('/create'),
            'edit' => Pages\EditAd::route('/{record}/edit'),
        ];
    }
}
