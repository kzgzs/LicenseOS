<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NavigationItemResource\Pages;
use App\Models\NavigationItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NavigationItemResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('导航菜单');
    }

    public static function getPluralModelLabel(): string
    {
        return __('导航菜单');
    }

    protected static ?string $model = NavigationItem::class;
    protected static ?string $navigationIcon = 'heroicon-o-bars-3';
    protected static ?int $navigationSort = 90;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.marketing');
    }



    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('admin.nav_item.details'))
                ->schema([
                    Forms\Components\TextInput::make('label')
                        ->required()
                        ->maxLength(100)
                        ->helperText(__('admin.nav_item.helper.label')),
                    Forms\Components\TextInput::make('url')
                        ->required()
                        ->maxLength(255)
                        ->helperText(__('admin.nav_item.helper.url')),
                    Forms\Components\Select::make('target')
                        ->options([
                            '_self' => __('admin.nav_item.opt.self'),
                            '_blank' => __('admin.nav_item.opt.blank'),
                        ])
                        ->default('_self'),
                    Forms\Components\Select::make('group')
                        ->options([
                            'header' => __('admin.nav_item.opt.header'),
                            'footer' => __('admin.nav_item.opt.footer'),
                            'sidebar' => __('admin.nav_item.opt.sidebar'),
                        ])
                        ->default('header')
                        ->required(),
                    Forms\Components\TextInput::make('icon')
                        ->helperText(__('admin.nav_item.helper.icon')),
                    Forms\Components\Select::make('parent_id')
                        ->label(__('admin.nav_item.parent'))
                        ->options(fn () => NavigationItem::whereNull('parent_id')->pluck('label', 'id'))
                        ->searchable()
                        ->nullable(),
                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->helperText(__('admin.nav_item.helper.sort')),
                    Forms\Components\Toggle::make('is_active')
                        ->default(true)
                        ->label(__('admin.nav_item.is_active')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')->searchable()->label(__('admin.common.label_field'))->searchable(),
                Tables\Columns\TextColumn::make('url')->limit(40)->label(__('admin.common.url'))->limit(40),
                Tables\Columns\TextColumn::make('group')
                    ->label(__('admin.nav_item.group'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'header' => 'info',
                        'footer' => 'gray',
                        'sidebar' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('parent.label')->label(__('admin.nav_item.parent')),
                Tables\Columns\TextColumn::make('sort_order')->sortable()->label(__('admin.common.sort_order'))->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label(__('admin.common.is_active'))->boolean(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNavigationItems::route('/'),
            'create' => Pages\CreateNavigationItem::route('/create'),
            'edit' => Pages\EditNavigationItem::route('/{record}/edit'),
        ];
    }
}
