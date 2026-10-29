<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectReviewResource\Pages;
use App\Models\ProjectReview;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProjectReviewResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('项目评价');
    }

    public static function getPluralModelLabel(): string
    {
        return __('项目评价');
    }

    protected static ?string $model = ProjectReview::class;
    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?int $navigationSort = 72;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.freelance');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('contract.project.title')->searchable()->label(__('admin.project_review.project')),
                Tables\Columns\TextColumn::make('reviewer.name')->label(__('admin.project_review.reviewer')),
                Tables\Columns\TextColumn::make('reviewee.name')->label(__('admin.project_review.reviewee')),
                Tables\Columns\TextColumn::make('rating')->label(__('admin.common.rating'))->label(__('admin.project_review.quality'))->sortable(),
                Tables\Columns\TextColumn::make('communication')->label(__('admin.project_review.communication'))->sortable(),
                Tables\Columns\TextColumn::make('quality')->label(__('admin.project_review.quality'))->sortable(),
                Tables\Columns\TextColumn::make('timeliness')->label(__('admin.project_review.timeliness'))->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->label(__('admin.common.created_at'))->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListProjectReviews::route('/'),
        ];
    }
}
