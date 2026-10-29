<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UpdatePackageResource\Pages;
use App\Models\UpdatePackage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UpdatePackageResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('更新包');
    }

    public static function getPluralModelLabel(): string
    {
        return __('更新包');
    }

    protected static ?string $model = UpdatePackage::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?int $navigationSort = 3;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.products');
    }



    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('product_id')
                ->relationship('product', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->helperText(__('admin.update_pkg.helper.product')),
            Forms\Components\TextInput::make('version')
                ->label(__('admin.update_pkg.version'))
                ->required()
                ->helperText(__('admin.update_pkg.helper.version')),
            Forms\Components\TextInput::make('file_url')
                ->required()
                ->label(__('admin.update_pkg.download_url'))
                ->helperText(__('admin.update_pkg.helper.url')),
            Forms\Components\Textarea::make('changelog')
                ->label(__('admin.update_pkg.changelog'))
                ->rows(3)
                ->helperText(__('admin.update_pkg.helper.changelog')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->label(__('admin.common.id'))->sortable(),
                Tables\Columns\TextColumn::make('product.name')->searchable(),
                Tables\Columns\TextColumn::make('version')->label(__('admin.common.version')),
                Tables\Columns\TextColumn::make('file_url')->limit(40),
                Tables\Columns\TextColumn::make('changelog')->limit(50),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->label(__('admin.common.created_at'))->dateTime()->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUpdatePackages::route('/'),
            'create' => Pages\CreateUpdatePackage::route('/create'),
            'edit' => Pages\EditUpdatePackage::route('/{record}/edit'),
        ];
    }
}
