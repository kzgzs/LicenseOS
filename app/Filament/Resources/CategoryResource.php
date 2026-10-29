<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CategoryResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('分类');
    }

    public static function getPluralModelLabel(): string
    {
        return __('分类');
    }

    protected static ?string $model = Category::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?int $navigationSort = 1;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.products');
    }



    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('slug')
                ->maxLength(255)
                ->helperText(__('admin.category.helper.slug')),
            Forms\Components\Textarea::make('description')
                ->rows(3),
            Forms\Components\Select::make('parent_id')
                ->label(__('admin.category.parent'))
                ->options(fn () => Category::whereNull('parent_id')
                    ->orWhere('parent_id', 0)
                    ->pluck('name', 'id'))
                ->searchable()
                ->nullable(),
            Forms\Components\TextInput::make('sort_order')
                ->label(__('admin.category.sort_order'))
                ->numeric()
                ->default(0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('admin.common.name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')->label(__('admin.common.slug')),
                Tables\Columns\TextColumn::make('parent.name')
                    ->label(__('admin.category.parent'))
                    ->default('-'),
                Tables\Columns\TextColumn::make('sort_order')->label(__('admin.common.sort_order'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('products_count')
                    ->label(__('admin.category.products'))
                    ->counts('products')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('sort_order', 'asc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit'   => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
