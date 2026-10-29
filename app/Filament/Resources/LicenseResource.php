<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LicenseResource\Pages;
use App\Models\License;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LicenseResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('许可证');
    }

    public static function getPluralModelLabel(): string
    {
        return __('许可证');
    }

    protected static ?string $model = License::class;
    protected static ?string $navigationIcon = 'heroicon-o-key';
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.products');
    }



    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('tenant_id')
                ->relationship('tenant', 'name')->required(),
            Forms\Components\Select::make('product_id')
                ->relationship('product', 'name')->required(),
            Forms\Components\TextInput::make('license_key')
                ->required()->helperText(__('admin.license.helper.key')),
            Forms\Components\Select::make('status')
                ->options(['active' => __('admin.license.opt.active'), 'expired' => __('admin.license.opt.expired'), 'revoked' => __('admin.license.opt.revoked')])
                ->default('active'),
            Forms\Components\TextInput::make('max_domains')->label(__('admin.license.max_domains'))->numeric()->default(1),
            Forms\Components\DateTimePicker::make('expire_at')->label(__('admin.license.expire_at')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('license_key')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('product.name'),
                Tables\Columns\TextColumn::make('tenant.name'),
                Tables\Columns\TextColumn::make('status')->label(__('admin.common.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'expired' => 'warning',
                        'revoked' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('max_domains'),
                Tables\Columns\TextColumn::make('expire_at')->dateTime(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLicenses::route('/'),
            'create' => Pages\CreateLicense::route('/create'),
            'edit' => Pages\EditLicense::route('/{record}/edit'),
        ];
    }
}
