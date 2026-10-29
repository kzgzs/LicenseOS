<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VerificationResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VerificationResource extends Resource
{

    public static function getModelLabel(): string
    {
        return __('企业认证');
    }

    public static function getPluralModelLabel(): string
    {
        return __('企业认证');
    }

    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?int $navigationSort = 73;
    protected static ?string $modelLabel = null;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('account_type', 'company');
    }
    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_group.freelance');
    }



    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->disabled(),
            Forms\Components\TextInput::make('email')->disabled(),
            Forms\Components\TextInput::make('company_name')->disabled(),
            Forms\Components\ViewField::make('business_license')
                ->label(__('admin.verification.business_license'))
                ->view('filament.forms.verification-license'),
            Forms\Components\Select::make('verification_status')
                ->options([
                    'pending' => __('admin.verification.opt.pending'),
                    'approved' => __('admin.verification.opt.approved'),
                    'rejected' => __('admin.verification.opt.rejected'),
                ])
                ->required(),
            Forms\Components\Textarea::make('verification_note')
                ->label(__('admin.verification.review_note'))
                ->helperText(__('admin.verification.helper.note')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable()->label(__('admin.common.name'))->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->label(__('admin.common.email'))->searchable(),
                Tables\Columns\TextColumn::make('company_name')->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('verification_status')
                    ->label(__('admin.common.status'))
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                        'gray' => 'none',
                    ]),
                Tables\Columns\TextColumn::make('verified_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->label(__('admin.common.created_at'))->dateTime()->sortable(),
            ])
            ->defaultSort('verification_status', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('verification_status')
                    ->options([
                        'pending' => __('admin.verification.opt.pending'),
                        'approved' => __('admin.verification.opt.approved'),
                        'rejected' => __('admin.verification.opt.rejected'),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label(__('admin.verification.review')),
                Tables\Actions\Action::make('approve')
                    ->action(function (User $record) {
                        $record->update([
                            'verification_status' => 'approved',
                            'verified_at' => now(),
                        ]);
                        self::notifyUser($record, 'approved');
                    })
                    ->requiresConfirmation()
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (User $record) => $record->verification_status === 'pending'),
                Tables\Actions\Action::make('reject')
                    ->action(function (User $record, array $data) {
                        $record->update([
                            'verification_status' => 'rejected',
                            'verification_note' => $data['note'] ?? null,
                        ]);
                        self::notifyUser($record, 'rejected', $data['note'] ?? '');
                    })
                    ->form([
                        Forms\Components\Textarea::make('note')->label(__('admin.verification.rejection_reason'))->required(),
                    ])
                    ->requiresConfirmation()
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn (User $record) => $record->verification_status === 'pending'),
            ])
            ->bulkActions([]);
    }

    protected static function notifyUser(User $user, string $status, string $note = ''): void
    {
        // In-app notification
        try {
            $title = $status === 'approved'
                ? __('notification.verification_approved')
                : __('notification.verification_rejected');
            $body = $status === 'rejected' && $note ? $note : '';

            \DB::table('notifications')->insert([
                'id' => \Illuminate\Support\Str::uuid(),
                'type' => 'verification',
                'notifiable_type' => User::class,
                'notifiable_id' => $user->id,
                'data' => json_encode(['title' => $title, 'body' => $body]),
                'read_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            \Log::warning('Verification notification failed: ' . $e->getMessage());
        }

        // Email
        try {
            if ($user->email) {
                \Illuminate\Support\Facades\Mail::to($user->email)->queue(
                    new \App\Mail\VerificationResultMail($user, $status, $note)
                );
            }
        } catch (\Exception $e) {
            \Log::warning('Verification email failed: ' . $e->getMessage());
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVerifications::route('/'),
            'edit' => Pages\EditVerification::route('/{record}/edit'),
        ];
    }
}
