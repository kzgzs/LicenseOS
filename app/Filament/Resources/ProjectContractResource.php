<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectContractResource\Pages;
use App\Models\ProjectContract;
use App\Models\SiteSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProjectContractResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('合同');
    }

    public static function getPluralModelLabel(): string
    {
        return __('合同');
    }

    protected static ?string $model = ProjectContract::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 61;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.freelance');
    }



    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('project_id')
                ->label(__('freelance.project'))
                ->relationship('project', 'title')
                ->searchable()->preload()->required(),
            Forms\Components\Select::make('client_id')
                ->label(__('freelance.client'))
                ->relationship('client', 'name')
                ->searchable()->preload()->required(),
            Forms\Components\Select::make('developer_id')
                ->label(__('freelance.developer'))
                ->relationship('developer', 'name')
                ->searchable()->preload()->required(),
            Forms\Components\TextInput::make('total_amount')->label(__('freelance.total_amount'))->numeric()->prefix(SiteSetting::get('currency_symbol', '¥'))->required(),
            Forms\Components\TextInput::make('platform_fee')->label(__('freelance.platform_fee'))->numeric()->prefix(SiteSetting::get('currency_symbol', '¥')),
            Forms\Components\TextInput::make('developer_income')->label(__('freelance.developer_income'))->numeric()->prefix(SiteSetting::get('currency_symbol', '¥')),
            Forms\Components\Select::make('status')
                ->label(__('freelance.status'))
                ->options([
                    'active' => __('admin.contract.opt.active'),
                    'completed' => __('admin.contract.opt.completed'),
                    'cancelled' => __('admin.contract.opt.cancelled'),
                    'disputed' => __('admin.contract.opt.disputed'),
                ])->default('active'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('project.title')->label(__('admin.contract.project'))->searchable()->limit(25),
                Tables\Columns\TextColumn::make('client.name')->label(__('admin.contract.client'))->searchable(),
                Tables\Columns\TextColumn::make('developer.name')->label(__('admin.contract.developer'))->searchable(),
                Tables\Columns\TextColumn::make('total_amount')->label(__('admin.contract.total_amount'))->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('platform_fee')->money('USD')->label(__('admin.contract.fee')),
                Tables\Columns\TextColumn::make('status')->label(__('admin.common.status'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'warning',
                        'completed' => 'success',
                        'disputed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')->date()->sortable()->label(__('admin.common.created_at'))->date()->sortable(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjectContracts::route('/'),
            'create' => Pages\CreateProjectContract::route('/create'),
            'edit' => Pages\EditProjectContract::route('/{record}/edit'),
        ];
    }
}
