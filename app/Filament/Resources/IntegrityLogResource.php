<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IntegrityLogResource\Pages;
use App\Models\DomainIntegrityLog;
use App\Models\SiteSetting;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IntegrityLogResource extends Resource
{
    protected static ?string $model = DomainIntegrityLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?int $navigationSort = 90;

    public static function getModelLabel(): string
    {
        return __('完整性日志');
    }

    public static function getPluralModelLabel(): string
    {
        return __('完整性日志');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.security');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('domain')
                    ->label(__('域名'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label(__('产品'))
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_valid')
                    ->label(__('完整性'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle'),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label(__('IP'))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('时间'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('tampered')
                    ->label(__('仅篡改记录'))
                    ->query(fn (Builder $query) => $query->where('is_valid', false)),
            ])
            ->actions([
                // 只读，无编辑操作
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('remove_blacklist')
                    ->label(__('解除黑名单'))
                    ->action(function ($records) {
                        $blacklist = json_decode(SiteSetting::get('blacklisted_domains', '[]'), true) ?: [];
                        foreach ($records as $record) {
                            $blacklist = array_values(array_diff($blacklist, [$record->domain]));
                        }
                        SiteSetting::set('blacklisted_domains', json_encode(array_values($blacklist)));
                    })
                    ->requiresConfirmation()
                    ->color('warning'),
                Tables\Actions\BulkAction::make('reset_tampered')
                    ->label(__('重置篡改标记'))
                    ->action(function ($records) {
                        foreach ($records as $record) {
                            if ($record->license_id) {
                                \App\Models\License::where('id', $record->license_id)
                                    ->update(['is_tampered' => false, 'tampered_at' => null]);
                            }
                        }
                    })
                    ->requiresConfirmation()
                    ->color('success'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIntegrityLogs::route('/'),
        ];
    }
}
