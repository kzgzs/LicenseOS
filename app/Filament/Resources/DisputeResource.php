<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DisputeResource\Pages;
use App\Models\ProjectDispute;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DisputeResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('争议');
    }

    public static function getPluralModelLabel(): string
    {
        return __('争议');
    }

    protected static ?string $model = ProjectDispute::class;
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';
    protected static ?int $navigationSort = 65;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.orders');
    }



    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('admin.dispute.details'))->schema([
                Forms\Components\Select::make('contract_id')
                    ->relationship('contract', 'id')
                    ->searchable()->preload()->required()
                    ->disabled(fn ($record) => $record !== null),
                Forms\Components\Select::make('milestone_id')
                    ->relationship('milestone', 'title')
                    ->searchable()->preload()->nullable(),
                Forms\Components\Select::make('reason')
                    ->options(ProjectDispute::REASONS)
                    ->required()
                    ->disabled(fn ($record) => $record !== null),
                Forms\Components\Textarea::make('description')
                    ->rows(4)
                    ->required()
                    ->disabled(fn ($record) => $record !== null),
            ]),

            Forms\Components\Section::make(__('admin.dispute.resolution'))->schema([
                Forms\Components\Select::make('status')
                    ->options(ProjectDispute::STATUSES)
                    ->default('open')
                    ->required(),
                Forms\Components\Textarea::make('admin_note')
                    ->rows(3)
                    ->helperText(__('admin.dispute.helper.admin_note')),
                Forms\Components\TextInput::make('client_refund')
                    ->numeric()->prefix('¥')->default(0),
                Forms\Components\TextInput::make('developer_payout')
                    ->numeric()->prefix('¥')->default(0),
                Forms\Components\DateTimePicker::make('resolved_at')
                    ->nullable(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->searchable()->label(__('admin.common.id'))->sortable()->searchable(),
                Tables\Columns\TextColumn::make('contract.project.title')
                    ->label(__('admin.dispute.project'))->limit(30)->searchable(),
                Tables\Columns\TextColumn::make('raisedByUser.name')
                    ->label(__('admin.dispute.raised_by'))->searchable(),
                Tables\Columns\TextColumn::make('reason')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'not_delivered' => 'danger',
                        'quality_issue' => 'warning',
                        'scope_change' => 'info',
                        'no_response' => 'gray',
                        default => 'secondary',
                    }),
                Tables\Columns\TextColumn::make('status')->label(__('admin.common.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'warning',
                        'under_review' => 'info',
                        'closed' => 'gray',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('client_refund')
                    ->prefix('¥')->sortable(),
                Tables\Columns\TextColumn::make('developer_payout')
                    ->prefix('¥')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.common.created_at'))
                    ->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(ProjectDispute::STATUSES),
                Tables\Filters\SelectFilter::make('reason')
                    ->options(ProjectDispute::REASONS),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDisputes::route('/'),
        ];
    }
}
