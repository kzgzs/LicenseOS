<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SkillResource\Pages;
use App\Models\DeveloperSkill;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SkillResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('技能');
    }

    public static function getPluralModelLabel(): string
    {
        return __('技能');
    }

    protected static ?string $model = DeveloperSkill::class;
    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';
    protected static ?int $navigationSort = 67;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.freelance');
    }



    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->relationship('user', 'name')
                ->searchable()->preload()->required(),
            Forms\Components\TextInput::make('skill')
                ->required()->maxLength(50),
            Forms\Components\Select::make('level')
                ->options(DeveloperSkill::LEVELS)
                ->default(3)
                ->required(),
            Forms\Components\Toggle::make('verified')
                ->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('admin.skill.developer'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('skill')
                    ->searchable()->sortable(),
                Tables\Columns\TextColumn::make('level')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 4 => 'success',
                        $state >= 3 => 'info',
                        $state >= 2 => 'warning',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (int $state) => DeveloperSkill::LEVELS[$state] ?? __('admin.skill.unknown')),
                Tables\Columns\IconColumn::make('verified')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.common.created_at'))
                    ->dateTime('Y-m-d')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('level')
                    ->options(DeveloperSkill::LEVELS),
                Tables\Filters\TernaryFilter::make('verified'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSkills::route('/'),
        ];
    }
}
