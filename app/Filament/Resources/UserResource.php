<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('用户');
    }

    public static function getPluralModelLabel(): string
    {
        return __('用户');
    }

    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?int $navigationSort = 1;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.users');
    }



    public static function form(Form $form): Form
    {
        return $form->schema([
            // 1. Account Type
            Forms\Components\Select::make('role')
                ->label(__('admin.user.account_type'))
                ->options([
                    'customer' => __('admin.user.opt.customer'),
                    'developer' => __('admin.user.opt.developer'),
                ])
                ->default('customer')
                ->required()
                ->live()
                ->visible(fn (?User $record): bool => $record === null || ($record->id !== 1 && $record->role !== 'super_admin'))
                ->helperText(__('admin.user.helper.account_type')),

            // 2. Studio / Company Name (Developer only)
            Forms\Components\TextInput::make('tenant_name')
                ->label(__('admin.user.studio_name'))
                ->visible(fn (Forms\Get $get): bool => $get('role') === 'developer')
                ->helperText(__('admin.user.helper.studio')),

            // 3. Name
            Forms\Components\TextInput::make('name')
                ->label(__('admin.user.name'))
                ->required()
                ->maxLength(255)
                ->autocomplete('off'),

            // 4. Email
            Forms\Components\TextInput::make('email')
                ->label(__('admin.user.email'))
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->autocomplete('off')
                ->helperText(__('admin.user.helper.email')),

            // 5. Password
            Forms\Components\TextInput::make('password')
                ->label(fn (?User $record): string => $record ? __('admin.user.reset_password') : __('admin.user.password'))
                ->password()
                ->revealable()
                ->required(fn (?User $record): bool => $record === null)
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null)
                ->afterStateHydrated(fn (Forms\Components\TextInput $component, ?User $record) => $record ? $component->state('') : null)
                ->autocomplete('new-password')
                ->minLength(6)
                ->helperText(fn (?User $record): string => $record
                    ? __('admin.user.helper.password_keep')
                    : __('admin.user.helper.password_min')),

            // 6. Commission Rate (Developer only)
            Forms\Components\TextInput::make('commission_rate')
                ->label(__('admin.user.commission_rate'))
                ->numeric()
                ->default(10)
                ->suffix('%')
                ->visible(fn (Forms\Get $get): bool => $get('role') === 'developer')
                ->helperText(__('admin.user.helper.commission')),

            // 7. Account Status (Developer only)
            Forms\Components\Select::make('status')
                ->label(__('admin.user.status'))
                ->options(['active' => __('admin.user.opt.active'), 'suspended' => __('admin.user.opt.suspended')])
                ->default('active')
                ->visible(fn (Forms\Get $get): bool => $get('role') === 'developer')
                ->helperText(__('admin.user.helper.status')),

            // 8. Payout Accounts (Developer only)
            Forms\Components\Section::make(__('admin.user.payout_accounts'))
                ->description(__('admin.user.payout_desc'))
                ->visible(fn (Forms\Get $get): bool => $get('role') === 'developer')
                ->schema([
                    Forms\Components\TextInput::make('stripe_account_id')
                        ->label(__('admin.user.stripe_account'))
                        ->placeholder('acct_...')
                        ->maxLength(255)
                        ->helperText(__('admin.user.helper.stripe')),
                    Forms\Components\TextInput::make('alipay_account')
                        ->label(__('admin.user.alipay_account'))
                        ->placeholder(__('payout.alipay_placeholder'))
                        ->maxLength(255)
                        ->helperText(__('admin.user.helper.alipay')),
                    Forms\Components\TextInput::make('wechat_account')
                        ->label(__('admin.user.wechat_pay_account'))
                        ->placeholder(__('payout.wechat_placeholder'))
                        ->maxLength(255)
                        ->helperText(__('admin.user.helper.wechat')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable()->label(__('admin.common.id'))->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->label(__('admin.common.name'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->sortable()->label(__('admin.common.email'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('role')
                    ->label(__('admin.user.account_type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'super_admin' => __('admin.user.super_admin'),
                        'developer' => __('auth.developer'),
                        'customer' => __('auth.customer'),
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'super_admin' => 'danger',
                        'developer' => 'success',
                        'customer' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label(__('admin.user.studio'))
                    ->getStateUsing(fn (User $record): string => $record->role === 'developer' ? ($record->tenant?->name ?? '-') : '-')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tenant.commission_rate')
                    ->label(__('admin.user.commission'))
                    ->getStateUsing(fn (User $record): string => $record->role === 'developer' ? ($record->tenant?->commission_rate !== null ? number_format($record->tenant->commission_rate, 2) . '%' : '-') : '-')
                    ->default('-'),
                Tables\Columns\TextColumn::make('tenant.status')
                    ->label(__('admin.common.status'))
                    ->getStateUsing(fn (User $record): ?string => $record->role === 'developer' ? $record->tenant?->status : null)
                    ->badge(fn (User $record): bool => $record->role === 'developer')
                    ->color(fn (?string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'danger',
                        default => 'gray',
                    })
                    ->default('-'),
                Tables\Columns\TextColumn::make('created_at')->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label(__('admin.user.account_type'))
                    ->options([
                        'super_admin' => __('admin.user.super_admin'),
                        'developer' => __('admin.user.opt.developer'),
                        'customer' => __('admin.user.opt.customer'),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn (User $record): bool => $record->id === 1 || $record->role === 'super_admin'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
