<?php

namespace App\Filament\Resources\ContactSubmissionResource\Pages;

use App\Filament\Resources\ContactSubmissionResource;
use App\Models\SiteSetting;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EditContactSubmission extends EditRecord
{
    protected static string $resource = ContactSubmissionResource::class;
    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Auto-set status to 'replied' when admin writes a reply
        if (!empty($data['admin_reply']) && $data['status'] !== 'replied') {
            $data['status'] = 'replied';
        }
        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record;

        if ($record->status === 'read' && !$record->read_at) {
            $record->update(['read_at' => now()]);
        }

        // Send reply emails when admin has written a reply (auto or manual 'replied')
        if ($record->status === 'replied' && $record->admin_reply && !$record->replied_at) {
            $record->update(['replied_at' => now()]);
            $this->sendReplyEmails($record);
        }
    }

    private function sendReplyEmails($record): void
    {
        $adminEmail = SiteSetting::get('contact_email');
        $siteName = __(SiteSetting::get('site_name', config('app.name', 'Platform')));
        $fromAddress = SiteSetting::get('mail_from_address', config('mail.from.address'));
        $fromName = __(SiteSetting::get('mail_from_name', config('mail.from.name', config('app.name', 'Platform'))));

        // Email to user (the person who submitted the message)
        if ($record->email && $record->admin_reply) {
            try {
                Mail::raw(
                    "Hi {$record->name},\n\n" .
                    "We've replied to your message:\n\n" .
                    "--- Your Message ---\n{$record->message}\n\n" .
                    "--- Our Reply ---\n{$record->admin_reply}\n\n" .
                    "Best regards,\n{$siteName} Team",
                    function ($msg) use ($record, $siteName, $fromAddress, $fromName) {
                        $msg->to($record->email, $record->name)
                            ->subject("[{$siteName}] Reply to your message");
                        if ($fromAddress) {
                            $msg->from($fromAddress, $fromName);
                        }
                    }
                );
            } catch (\Exception $e) {
                Log::warning('Reply email to user failed: ' . $e->getMessage());
            }
        }

        // Email to admin (notification of their own reply)
        if ($adminEmail && $record->admin_reply) {
            try {
                Mail::raw(
                    "Reply sent to {$record->name} ({$record->email}):\n\n" .
                    "--- Original Message ---\n{$record->message}\n\n" .
                    "--- Your Reply ---\n{$record->admin_reply}\n\n" .
                    "Contact: {$record->email}" .
                    ($record->other_contact ? " / {$record->other_contact}" : ''),
                    function ($msg) use ($adminEmail, $siteName, $fromAddress, $fromName) {
                        $msg->to($adminEmail)
                            ->subject("[{$siteName}] Reply notification sent");
                        if ($fromAddress) {
                            $msg->from($fromAddress, $fromName);
                        }
                    }
                );
            } catch (\Exception $e) {
                Log::warning('Reply email to admin failed: ' . $e->getMessage());
            }
        }
    }
}
