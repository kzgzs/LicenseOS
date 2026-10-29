<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FailedJobResource\Pages;
use App\Models\FailedJob;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Artisan;

class FailedJobResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('失败任务');
    }

    public static function getPluralModelLabel(): string
    {
        return __('失败任务');
    }

    protected static ?string $model = FailedJob::class;
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?int $navigationSort = 99;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.system');
    }



    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->label(__('admin.common.id'))->sortable(),
                Tables\Columns\TextColumn::make('queue')
                    ->label(__('admin.common.queue'))
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('payload')
                    ->limit(80)
                    ->label(__('admin.failed_job.job_class'))
                    ->getStateUsing(function ($record) {
                        $payload = json_decode($record->payload, true);
                        return $payload['displayName'] ?? 'Unknown';
                    }),
                Tables\Columns\TextColumn::make('exception')
                    ->limit(100)
                    ->wrap()
                    ->color('danger'),
                Tables\Columns\TextColumn::make('failed_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('failed_at', 'desc')
            ->actions([
                Action::make('retry')
                    ->label(__('admin.failed_job.retry'))
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        Artisan::call('queue:retry', ['id' => [$record->id]]);
                    }),
                Action::make('forget')
                    ->label(__('admin.failed_job.delete'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        Artisan::call('queue:forget', ['id' => $record->id]);
                    }),
            ])
            ->headerActions([
                Action::make('retry_all')
                    ->label(__('admin.failed_job.retry_all'))
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function () {
                        Artisan::call('queue:retry', ['id' => ['all']]);
                    }),
                Action::make('flush')
                    ->label(__('admin.failed_job.flush_all'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function () {
                        Artisan::call('queue:flush');
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFailedJobs::route('/'),
        ];
    }

    /**
     * Override the canAccess method since we're not using a standard Eloquent model.
     */
    public static function canAccess(): bool
    {
        return true;
    }
}
