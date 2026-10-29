<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantResource\Pages;
use App\Models\Tenant;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class TenantResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('开发者账户');
    }

    public static function getPluralModelLabel(): string
    {
        return __('开发者账户');
    }

    protected static ?string $model = Tenant::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static bool $shouldRegisterNavigation = false;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.users');
    }



    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('admin.tenant.account_info'))
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('admin.tenant.name'))
                        ->required()
                        ->helperText(__('admin.tenant.helper.name')),
                    Forms\Components\TextInput::make('email')
                        ->label(__('admin.tenant.email'))
                        ->email()
                        ->required()
                        ->helperText(__('admin.tenant.helper.email')),
                    Forms\Components\TextInput::make('password')
                        ->label(fn (?Tenant $record): string => $record ? __('admin.tenant.reset_pwd') : __('admin.user.password'))
                        ->password()
                        ->revealable()
                        ->required(fn (?Tenant $record): bool => $record === null)
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->minLength(6)
                        ->helperText(fn (?Tenant $record): string => $record
                            ? __('admin.tenant.helper.reset_pwd')
                            : __('admin.tenant.helper.init_pwd')),
                    Forms\Components\TextInput::make('commission_rate')->label(__('admin.user.commission_rate'))->numeric()->default(10)->suffix('%'),
                    Forms\Components\Select::make('status')
                        ->options(['active' => __('admin.user.opt.active'), 'suspended' => __('admin.user.opt.suspended')])
                        ->default('active'),
                ]),

            Forms\Components\Section::make(__('admin.tenant.payout_config'))
                ->description(__('admin.tenant.payout_config_desc'))
                ->schema([
                    Forms\Components\Tabs::make(__('admin.tenant.payout_methods'))
                        ->tabs([
                            Forms\Components\Tabs\Tab::make('Stripe Connect')
                                ->schema([
                                    Forms\Components\TextInput::make('stripe_account_id')
                                        ->label(__('admin.user.stripe_account'))
                                        ->placeholder('acct_...')
                                        ->helperText(__('admin.tenant.helper.stripe')),
                                ]),
                            Forms\Components\Tabs\Tab::make('Alipay')
                                ->schema([
                                    Forms\Components\TextInput::make('alipay_account')
                                        ->label(__('admin.tenant.alipay_account'))
                                        ->placeholder(__('payout.alipay_placeholder'))
                                        ->helperText(__('admin.tenant.helper.alipay')),
                                ]),
                            Forms\Components\Tabs\Tab::make('WeChat Pay')
                                ->schema([
                                    Forms\Components\TextInput::make('wechat_account')
                                        ->label(__('admin.tenant.wechat_openid'))
                                        ->placeholder('oXxXx...')
                                        ->helperText(__('admin.tenant.helper.wechat')),
                                ]),
                        ]),
                ]),
        ]);
    }

    /**
     * After creating a tenant, create the primary developer user account.
     */
    public static function afterCreate(Tenant $record, array $data): void
    {
        if (!empty($data['password']) && !empty($data['email'])) {
            User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'], // User model 'hashed' cast auto-hashes
                'role' => 'developer',
                'tenant_id' => $record->id,
            ]);
        }
    }

    /**
     * After saving, sync email/name/password to the primary developer user account.
     */
    public static function afterSave(Tenant $record, array $data): void
    {
        $user = User::where('tenant_id', $record->id)->first();
        if (!$user) {
            return;
        }

        $syncData = [];

        // Always sync email
        if ($record->email && $record->email !== $user->email) {
            $syncData['email'] = $record->email;
        }

        // Always sync name
        if ($record->name && $record->name !== $user->name) {
            $syncData['name'] = $record->name;
        }

        // Sync password if provided (dehydrated only when filled)
        if (!empty($data['password'])) {
            $syncData['password'] = $data['password']; // User model 'hashed' cast auto-hashes
        }

        if (!empty($syncData)) {
            $user->update($syncData);
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->label(__('admin.common.name'))->searchable(),
                Tables\Columns\TextColumn::make('email')->label(__('admin.common.email')),
                Tables\Columns\TextColumn::make('commission_rate')->suffix('%'),
                Tables\Columns\TextColumn::make('status')->label(__('admin.common.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('resetPassword')
                    ->label(__('admin.tenant.reset_pwd_btn'))
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('password')
                            ->label(__('admin.tenant.new_password'))
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(6),
                    ])
                    ->action(function (Tenant $record, array $data): void {
                        $user = User::where('tenant_id', $record->id)->first();
                        if ($user) {
                            $user->update(['password' => $data['password']]);
                            Notification::make()
                                ->title(__('admin.tenant.password_reset'))
                                ->body("Password for {$user->email} has been reset successfully.")
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('admin.tenant.no_user'))
                                ->body(__('admin.tenant.no_user_body'))
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
