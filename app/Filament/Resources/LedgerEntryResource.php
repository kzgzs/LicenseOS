<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LedgerEntryResource\Pages;
use App\Models\LedgerEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LedgerEntryResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('账本记录');
    }

    public static function getPluralModelLabel(): string
    {
        return __('账本记录');
    }

    protected static ?string $model = LedgerEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?int $navigationSort = 1;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.finance');
    }


    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('tenant_id')
                ->relationship('tenant', 'name')
                ->searchable()
                ->nullable(),
            Forms\Components\Select::make('user_id')
                ->relationship('user', 'name')
                ->searchable()
                ->nullable(),
            Forms\Components\Select::make('region_id')
                ->relationship('region', 'name')
                ->searchable()
                ->nullable(),
            Forms\Components\TextInput::make('currency')
                ->required()
                ->maxLength(3)
                ->default('USD'),
            Forms\Components\TextInput::make('amount')
                ->required()
                ->numeric()
                ->prefix('$'),
            Forms\Components\TextInput::make('amount_base')
                ->numeric()
                ->prefix('$')
                ->helperText(__('admin.ledger.helper.amount_base')),
            Forms\Components\TextInput::make('exchange_rate')
                ->numeric()
                ->default(1.0),
            Forms\Components\Select::make('type')
                ->options([
                    'income' => __('admin.ledger.opt.income'),
                    'payout' => __('admin.ledger.opt.payout'),
                    'fee' => __('admin.ledger.opt.fee'),
                    'refund' => __('admin.ledger.opt.refund'),
                    'commission' => __('admin.ledger.opt.commission'),
                    'tax' => __('admin.ledger.opt.tax'),
                    'transfer' => __('admin.ledger.opt.transfer'),
                ])
                ->required(),
            Forms\Components\Select::make('status')
                ->options([
                    'pending' => __('admin.order.opt.pending'),
                    'completed' => __('admin.ledger.opt.completed'),
                    'failed' => __('admin.order.opt.failed'),
                    'settled' => __('admin.ledger.opt.settled'),
                ])
                ->default('completed'),
            Forms\Components\Textarea::make('description')
                ->label(__('admin.ledger.description'))
                ->columnSpanFull(),
            Forms\Components\TextInput::make('reference_type')
                ->label(__('admin.ledger.reference_type')),
            Forms\Components\TextInput::make('reference_id')
                ->label(__('admin.ledger.reference_id'))
                ->numeric(),
            Forms\Components\DateTimePicker::make('settled_at')
                ->label(__('admin.ledger.settled_at')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->label(__('admin.common.id'))->sortable(),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label(__('admin.ledger.tenant'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('currency')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'USD' => 'success',
                        'EUR' => 'info',
                        'GBP' => 'warning',
                        'SGD' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('amount')->label(__('admin.common.amount'))
                    ->money(fn ($record) => $record->currency ?? 'USD')
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount_base')
                    ->money('USD')
                    ->label(__('admin.ledger.base_usd'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('exchange_rate')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')->label(__('admin.common.type'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'income' => 'success',
                        'payout' => 'danger',
                        'fee' => 'warning',
                        'refund' => 'danger',
                        'commission' => 'info',
                        'tax' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('status')->label(__('admin.common.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'settled' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('region.name')
                    ->label(__('admin.ledger.region')),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'income' => __('admin.ledger.opt.income'),
                        'payout' => __('admin.ledger.opt.payout'),
                        'fee' => __('admin.ledger.opt.fee'),
                        'refund' => __('admin.ledger.opt.refund'),
                        'commission' => __('admin.ledger.opt.commission'),
                        'tax' => __('admin.ledger.opt.tax'),
                    ]),
                Tables\Filters\SelectFilter::make('currency')
                    ->options([
                        'USD' => 'USD', 'EUR' => 'EUR', 'GBP' => 'GBP',
                        'SGD' => 'SGD', 'JPY' => 'JPY', 'CNY' => 'CNY',
                        'INR' => 'INR', 'AED' => 'AED',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => __('admin.order.opt.pending'),
                        'completed' => __('admin.ledger.opt.completed'),
                        'failed' => __('admin.order.opt.failed'),
                        'settled' => __('admin.ledger.opt.settled'),
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLedgerEntries::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }
}
