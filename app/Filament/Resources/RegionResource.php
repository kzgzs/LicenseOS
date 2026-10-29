<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RegionResource\Pages;
use App\Models\Region;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RegionResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('区域');
    }

    public static function getPluralModelLabel(): string
    {
        return __('区域');
    }

    protected static ?string $model = Region::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?int $navigationSort = 1;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.system');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('code')
                ->required()
                ->maxLength(10)
                ->placeholder('US, EU, SG, ME')
                ->helperText(__('admin.region.helper.code')),
            Forms\Components\TextInput::make('name')
                ->label(__('admin.region.name'))
                ->required()
                ->maxLength(100)
                ->helperText(__('admin.region.helper.name')),
            Forms\Components\TextInput::make('display_name')
                ->label(__('admin.region.display_name'))
                ->required()
                ->maxLength(100)
                ->helperText(__('admin.region.helper.display_name')),
            Forms\Components\TextInput::make('timezone')
                ->label(__('admin.region.timezone'))
                ->required()
                ->placeholder('America/New_York')
                ->helperText(__('admin.region.helper.timezone')),
            Forms\Components\TextInput::make('primary_currency')
                ->label(__('admin.region.primary_currency'))
                ->required()
                ->maxLength(3)
                ->default('USD')
                ->helperText(__('admin.region.helper.currency')),
            Forms\Components\TagsInput::make('supported_currencies')
                ->label(__('admin.region.supported_currencies'))
                ->separator(',')
                ->helperText(__('admin.region.helper.currencies')),
            Forms\Components\TextInput::make('stripe_account_id')
                ->label(__('admin.region.stripe_account'))
                ->helperText(__('admin.region.helper.stripe')),
            Forms\Components\TextInput::make('adyen_account_id')
                ->label(__('admin.region.adyen_account'))
                ->helperText(__('admin.region.helper.adyen')),
            Forms\Components\Toggle::make('is_primary')
                ->label(__('admin.region.is_primary'))
                ->default(false)
                ->helperText(__('admin.region.helper.is_primary')),
            Forms\Components\Select::make('status')
                ->options([
                    'active' => __('admin.region.opt.active'),
                    'inactive' => __('admin.region.opt.inactive'),
                    'maintenance' => __('admin.region.opt.maintenance'),
                ])
                ->default('active')
                ->required()
                ->helperText(__('admin.region.helper.status')),
            Forms\Components\KeyValue::make('config')
                ->label(__('admin.region.config'))
                ->columnSpanFull()
                ->helperText(__('admin.region.helper.config')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                Tables\Columns\TextColumn::make('display_name')
                    ->label(__('admin.region.display_name'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')->label(__('admin.common.name'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('timezone'),
                Tables\Columns\TextColumn::make('primary_currency')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'USD' => 'success',
                        'EUR' => 'info',
                        'SGD' => 'warning',
                        'AED' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_primary')
                    ->boolean()
                    ->label(__('admin.region.is_primary')),
                Tables\Columns\TextColumn::make('status')->label(__('admin.common.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'inactive' => 'danger',
                        'maintenance' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('tenants_count')
                    ->counts('tenants')
                    ->label(__('admin.region.tenants')),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => __('admin.region.opt.active'),
                        'inactive' => __('admin.region.opt.inactive'),
                        'maintenance' => __('admin.region.opt.maintenance'),
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRegions::route('/'),
            'create' => Pages\CreateRegion::route('/create'),
            'edit' => Pages\EditRegion::route('/{record}/edit'),
        ];
    }
}
