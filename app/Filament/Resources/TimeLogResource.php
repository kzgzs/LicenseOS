<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TimeLogResource\Pages;
use App\Models\ProjectTimeLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TimeLogResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('工时记录');
    }

    public static function getPluralModelLabel(): string
    {
        return __('工时记录');
    }

    protected static ?string $model = ProjectTimeLog::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?int $navigationSort = 68;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.freelance');
    }



    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('contract_id')
                ->relationship('contract', 'id')
                ->searchable()->preload()->required(),
            Forms\Components\Select::make('milestone_id')
                ->relationship('milestone', 'title')
                ->searchable()->preload()->nullable(),
            Forms\Components\Select::make('developer_id')
                ->relationship('developer', 'name')
                ->searchable()->preload()->required()
                ->label(__('admin.timelog.developer')),
            Forms\Components\Textarea::make('description')->rows(2)
                ->label(__('admin.timelog.description')),
            Forms\Components\TextInput::make('minutes')
                ->numeric()->default(0)->required()
                ->suffix(__('admin.timelog.minutes'))
                ->label(__('admin.timelog.minutes')),
            Forms\Components\DateTimePicker::make('started_at')
                ->label(__('admin.timelog.started_at')),
            Forms\Components\DateTimePicker::make('ended_at')
                ->label(__('admin.timelog.ended_at')),
            Forms\Components\TextInput::make('amount')
                ->numeric()->prefix('¥')->default(0)
                ->label(__('admin.timelog.amount')),
            Forms\Components\Select::make('status')
                ->options([
                    'pending' => __('admin.timelog.opt.pending'),
                    'approved' => __('admin.timelog.opt.approved'),
                    'rejected' => __('admin.timelog.opt.rejected'),
                    'billed' => __('admin.timelog.opt.billed'),
                ])
                ->default('pending')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->label(__('admin.common.id'))->sortable(),
                Tables\Columns\TextColumn::make('contract.project.title')
                    ->label(__('admin.timelog.project'))->limit(25)->searchable(),
                Tables\Columns\TextColumn::make('developer.name')
                    ->label(__('admin.timelog.developer'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('minutes')
                    ->formatStateUsing(fn (int $state) => floor($state / 60) . 'h ' . ($state % 60) . 'm')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')->label(__('admin.common.amount'))
                    ->prefix('¥')->sortable(),
                Tables\Columns\TextColumn::make('status')->label(__('admin.common.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'billed' => 'info',
                        default => 'secondary',
                    }),
                Tables\Columns\TextColumn::make('started_at')
                    ->dateTime('Y-m-d')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => __('admin.timelog.opt.pending'),
                        'approved' => __('admin.timelog.opt.approved'),
                        'rejected' => __('admin.timelog.opt.rejected'),
                        'billed' => __('admin.timelog.opt.billed'),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTimeLogs::route('/'),
        ];
    }
}
