<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use App\Models\TicketReply;
use App\Models\SiteSetting;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EditTicket extends EditRecord
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        $data = $this->form->getState();

        // If admin wrote a reply, post it to the thread
        if (!empty($data['admin_reply'])) {
            TicketReply::create([
                'ticket_id' => $record->id,
                'user_id'   => null,
                'is_admin'  => true,
                'message'   => $data['admin_reply'],
            ]);

            // Auto-set status to in_progress if still open
            if ($record->status === 'open') {
                $record->update(['status' => 'in_progress']);
            }

            // Notify user via email
            $this->notifyUser($record, $data['admin_reply']);

            // Clear the reply field
            $this->form->fill(array_merge($this->form->getState(), ['admin_reply' => '']));
        }
    }

    private function notifyUser($ticket, string $reply): void
    {
        $siteName = __(SiteSetting::get('site_name', config('app.name', 'Platform')));
        $fromAddress = SiteSetting::get('mail_from_address', config('mail.from.address'));
        $fromName = __(SiteSetting::get('mail_from_name', config('mail.from.name', config('app.name', 'Platform'))));

        if (!$ticket->user || !$ticket->user->email) return;

        try {
            Mail::raw(
                "Hi {$ticket->user->name},\n\n" .
                "Your support ticket #{$ticket->id} has a new reply:\n\n" .
                "--- Support Team Reply ---\n{$reply}\n\n" .
                "--- Your Original Message ---\n{$ticket->subject}\n\n" .
                "You can view and reply at: " . config('app.url') . "/tickets/{$ticket->id}\n\n" .
                "Best regards,\n{$siteName} Support Team",
                function ($msg) use ($ticket, $siteName, $fromAddress, $fromName) {
                    $msg->to($ticket->user->email, $ticket->user->name)
                        ->subject("[{$siteName}] Ticket #{$ticket->id} - New Reply");
                    if ($fromAddress) {
                        $msg->from($fromAddress, $fromName);
                    }
                }
            );
        } catch (\Exception $e) {
            Log::warning('Ticket reply email failed: ' . $e->getMessage());
        }
    }
}
