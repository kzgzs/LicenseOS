<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Models\Project;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProjectResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('项目');
    }

    public static function getPluralModelLabel(): string
    {
        return __('项目');
    }

    protected static ?string $model = Project::class;
    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?int $navigationSort = 60;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.freelance');
    }



    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('title')->required()->maxLength(200),
            Forms\Components\Select::make('client_id')
                ->relationship('client', 'name')
                ->searchable()->preload()->required(),
            Forms\Components\Textarea::make('description')->required()->rows(4),
            Forms\Components\Select::make('category')
                ->options(\App\Models\Project::CATEGORIES)
                ->searchable()
                ->default('website'),
            Forms\Components\TextInput::make('budget_min')->numeric()->prefix('$'),
            Forms\Components\TextInput::make('budget_max')->numeric()->prefix('$'),
            Forms\Components\Select::make('status')
                ->options([
                    'open' => __('admin.project.opt.open'),
                    'in_progress' => __('admin.project.opt.in_progress'),
                    'completed' => __('admin.project.opt.completed'),
                    'cancelled' => __('admin.project.opt.cancelled'),
                ])->default('open'),
            Forms\Components\TextInput::make('bid_count')->numeric()->default(0)->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->limit(30)->label(__('admin.common.title'))->searchable()->limit(30),
                Tables\Columns\TextColumn::make('client.name')->label(__('admin.project.client'))->searchable(),
                Tables\Columns\TextColumn::make('category')->badge()->label(__('admin.common.category'))->badge(),
                Tables\Columns\TextColumn::make('budget_max')->money('USD')->sortable(),
                Tables\Columns\TextColumn::make('bid_count')->label(__('admin.project.bids'))->sortable(),
                Tables\Columns\TextColumn::make('status')->label(__('admin.common.status'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'open' => 'success',
                        'in_progress' => 'warning',
                        'completed' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')->date()->sortable()->label(__('admin.common.created_at'))->date()->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}
