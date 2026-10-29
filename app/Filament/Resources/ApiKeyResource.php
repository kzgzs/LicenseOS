<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApiKeyResource\Pages;
use App\Models\ApiKey;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ApiKeyResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('API密钥');
    }

    public static function getPluralModelLabel(): string
    {
        return __('API密钥');
    }

    protected static ?string $model = ApiKey::class;
    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?int $navigationSort = 2;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.system');
    }



    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('tenant_id')
                ->relationship('tenant', 'name')
                ->required(),
            Forms\Components\TextInput::make('key')
                ->default(fn () => 'losk_' . \Illuminate\Support\Str::random(40))
                ->required(),
            Forms\Components\Toggle::make('active')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->label(__('admin.common.id'))->sortable(),
                Tables\Columns\TextColumn::make('tenant.name')->searchable()->label(__('admin.apikey.developer')),
                Tables\Columns\TextColumn::make('key')->copyable()->limit(30),
                Tables\Columns\IconColumn::make('active')->boolean()->label(__('admin.common.active'))->boolean(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->label(__('admin.common.created_at'))->dateTime()->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApiKeys::route('/'),
            'create' => Pages\CreateApiKey::route('/create'),
            'edit' => Pages\EditApiKey::route('/{record}/edit'),
        ];
    }
}
