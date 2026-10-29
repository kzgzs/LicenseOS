<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TemplateResource\Pages;
use App\Models\ProjectTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TemplateResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('模板');
    }

    public static function getPluralModelLabel(): string
    {
        return __('模板');
    }

    protected static ?string $model = ProjectTemplate::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 70;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.freelance');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('admin.template.template_info'))->schema([
                Forms\Components\TextInput::make('title')->required()->maxLength(150),
                Forms\Components\Textarea::make('description')->required()->rows(3),
                Forms\Components\Select::make('category')
                    ->options(\App\Models\Project::CATEGORIES)
                    ->required(),
                Forms\Components\TagsInput::make('skills'),
                Forms\Components\TextInput::make('budget_min')->numeric()->prefix('$'),
                Forms\Components\TextInput::make('budget_max')->numeric()->prefix('$'),
                Forms\Components\TextInput::make('currency')->default('USD')->maxLength(3),
                Forms\Components\Select::make('duration')
                    ->options(\App\Models\Project::durations()),
            ])->columns(2),

            Forms\Components\Section::make(__('admin.template.status_section'))->schema([
                Forms\Components\Toggle::make('is_active')->default(true),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->sortable()->label(__('admin.common.title'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('category')->sortable()->label(__('admin.common.category'))->sortable(),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label(__('admin.common.is_active'))->boolean(),
                Tables\Columns\TextColumn::make('use_count')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->label(__('admin.common.created_at'))->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options(\App\Models\Project::CATEGORIES),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListTemplates::route('/'),
        ];
    }
}
