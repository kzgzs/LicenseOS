<?php

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use App\Models\User;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterSave(): void
    {
        // Fallback: force sync email/name to users table
        $user = User::where('tenant_id', $this->record->id)->first();
        if ($user && ($user->email !== $this->record->email || $user->name !== $this->record->name)) {
            $user->update([
                'email' => $this->record->email,
                'name'  => $this->record->name,
            ]);
        }

        Notification::make()
            ->title('Developer Account saved')
            ->body('Login email synced. Use "Reset Pwd" button in the list to reset password if needed.')
            ->success()
            ->send();
    }
}
