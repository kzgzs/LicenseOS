<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScoreResource\Pages;
use App\Models\DeveloperScore;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ScoreResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('评分');
    }

    public static function getPluralModelLabel(): string
    {
        return __('评分');
    }

    protected static ?string $model = DeveloperScore::class;
    protected static ?string $navigationIcon = 'heroicon-o-star';
    protected static ?int $navigationSort = 71;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.freelance');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('composite_score')->sortable()->label(__('admin.score.score')),
                Tables\Columns\BadgeColumn::make('tier')->label(__('admin.common.tier'))
                    ->colors([
                        'warning' => 'bronze',
                        'gray' => 'silver',
                        'success' => 'gold',
                        'info' => 'platinum',
                        'primary' => 'diamond',
                    ]),
                Tables\Columns\TextColumn::make('completion_rate')->label(__('admin.common.completion_rate'))->label(__('admin.score.completion_rate'))->suffix('%'),
                Tables\Columns\TextColumn::make('avg_rating')->label(__('admin.common.avg_rating'))->label(__('admin.score.avg_rating')),
                Tables\Columns\TextColumn::make('project_count')->label(__('admin.score.project_count')),
                Tables\Columns\TextColumn::make('dispute_count')->label(__('admin.score.dispute_count')),
                Tables\Columns\TextColumn::make('total_earned')->label(__('admin.score.total_earned'))->money('CNY'),
                Tables\Columns\TextColumn::make('last_calculated_at')->dateTime()->sortable()->label(__('admin.common.last_calculated_at'))->dateTime()->sortable(),
            ])
            ->defaultSort('composite_score', 'desc')
            ->actions([
                Tables\Actions\Action::make('recalculate')
                    ->action(function (DeveloperScore $record) {
                        app(\App\Services\CreditScoreService::class)->calculate($record->user_id);
                    })
                    ->icon('heroicon-o-arrow-path'),
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
            'index' => Pages\ListScores::route('/'),
        ];
    }
}
