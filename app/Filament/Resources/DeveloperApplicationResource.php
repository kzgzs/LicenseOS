<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeveloperApplicationResource\Pages;
use App\Models\DeveloperApplication;
use App\Models\ApiKey;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Hash;

class DeveloperApplicationResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('开发者申请');
    }

    public static function getPluralModelLabel(): string
    {
        return __('开发者申请');
    }

    protected static ?string $model = DeveloperApplication::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-plus';
    protected static ?int $navigationSort = 3;
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.users');
    }



    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('email')->email()->required(),
            Forms\Components\Textarea::make('reason')->rows(3),
            Forms\Components\Select::make('status')
                ->options(['pending' => __('admin.devapp.opt.pending'), 'approved' => __('admin.devapp.opt.approved'), 'rejected' => __('admin.devapp.opt.rejected')])
                ->default('pending'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable()->label('#'),
                TextColumn::make('name')->searchable()->label(__('admin.devapp.name')),
                TextColumn::make('email')->searchable()->label(__('admin.devapp.email')),
                TextColumn::make('reason')->limit(50)->label(__('admin.devapp.reason')),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')->dateTime()->sortable()->label(__('admin.devapp.applied')),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(['pending' => __('admin.devapp.opt.pending'), 'approved' => __('admin.devapp.opt.approved'), 'rejected' => __('admin.devapp.opt.rejected')]),
            ])
            ->actions([
                Action::make('approve')
                    ->label(__('admin.devapp.approve'))
                    ->visible(fn (DeveloperApplication $record) => $record->status === 'pending')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
                    ->form([
                        Forms\Components\TextInput::make('tenant_name')
                            ->label(__('admin.devapp.studio_name'))
                            ->default(fn ($record) => $record->name . ' Studio')
                            ->required(),
                        Forms\Components\TextInput::make('password')
                            ->label(__('admin.devapp.set_password'))
                            ->password()
                            ->revealable()
                            ->default('changeme123')
                            ->minLength(6)
                            ->required()
                            ->helperText(__('admin.devapp.helper.password')),
                    ])
                    ->action(function (DeveloperApplication $record, array $data) {
                        // Create tenant
                        $tenant = Tenant::create([
                            'name' => $data['tenant_name'],
                            'email' => $record->email,
                            'commission_rate' => (int) SiteSetting::get('default_commission_rate', '10'),
                            'status' => 'active',
                        ]);

                        // Generate API key
                        try { ApiKey::generateFor($tenant); } catch (\Throwable $e) {}

                        // Create user with the specified password
                        User::create([
                            'name' => $record->name,
                            'email' => $record->email,
                            'password' => Hash::make($data['password']),
                            'role' => 'developer',
                            'tenant_id' => $tenant->id,
                        ]);

                        $record->update(['status' => 'approved']);

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title(__('admin.devapp.approved_title'))
                            ->body("{$record->name} has been approved and added to {$data['tenant_name']}.")
                            ->send();
                    }),
                Action::make('reject')
                    ->label(__('admin.devapp.reject'))
                    ->visible(fn (DeveloperApplication $record) => $record->status === 'pending')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->action(function (DeveloperApplication $record) {
                        $record->update(['status' => 'rejected']);

                        \Filament\Notifications\Notification::make()
                            ->warning()
                            ->title(__('admin.devapp.rejected_title'))
                            ->body("{$record->name}'s application has been rejected.")
                            ->send();
                    }),
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
            'index' => Pages\ListDeveloperApplications::route('/'),
        ];
    }
}
