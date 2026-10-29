<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BadgeResource\Pages;
use App\Models\DeveloperBadge;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BadgeResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('徽章');
    }

    public static function getPluralModelLabel(): string
    {
        return __('徽章');
    }

    protected static ?string $model = DeveloperBadge::class;
    protected static ?string $navigationIcon = 'heroicon-o-trophy';
    protected static ?int $navigationSort = 66;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.marketing');
    }



    public static function form(Form $form): Form
    {
        $typeOptions = [];
        foreach (DeveloperBadge::TYPES as $key => $info) {
            $typeOptions[$key] = $info['label'];
        }

        return $form->schema([
            Forms\Components\Select::make('user_id')
                ->relationship('user', 'name')
                ->searchable()->preload()->required(),
            Forms\Components\Select::make('badge_type')
                ->options($typeOptions)
                ->required()
                ->reactive()
                ->afterStateUpdated(fn ($state, callable $set) => $set('label', DeveloperBadge::TYPES[$state]['label'] ?? '')),
            Forms\Components\TextInput::make('label')
                ->required()->maxLength(100),
            Forms\Components\Textarea::make('description')->rows(2),
            Forms\Components\TextInput::make('icon')->maxLength(50),
            Forms\Components\DateTimePicker::make('awarded_at')
                ->default(now()),
            Forms\Components\DateTimePicker::make('expires_at')
                ->nullable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label(__('admin.badge.developer'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('badge_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'verified' => 'info',
                        'top_rated' => 'warning',
                        'fast_delivery' => 'success',
                        'specialist' => 'primary',
                        'partner' => 'success',
                        default => 'secondary',
                    }),
                Tables\Columns\TextColumn::make('label')->limit(20)->label(__('admin.common.label_field'))->limit(20),
                Tables\Columns\TextColumn::make('awarded_at')
                    ->dateTime('Y-m-d')->sortable(),
                Tables\Columns\TextColumn::make('expires_at')->label(__('admin.common.expires_at'))
                    ->dateTime('Y-m-d')->sortable()
                    ->placeholder(__('admin.badge.never')),
                Tables\Columns\IconColumn::make('is_active')->label(__('admin.common.is_active'))
                    ->boolean()
                    ->state(fn ($record) => $record->isActive()),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('badge_type')
                    ->options(collect(DeveloperBadge::TYPES)->mapWithKeys(fn ($info, $key) => [$key => $info['label']])->toArray()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBadges::route('/'),
        ];
    }
}
