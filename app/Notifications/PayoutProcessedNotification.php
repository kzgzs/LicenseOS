<?php

namespace App\Notifications;

use App\Models\Payout;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PayoutProcessedNotification extends Notification
{
    use Queueable;

    public function __construct(protected Payout $payout) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('notification.payout_processed_title'),
            'message' => __('notification.payout_processed_message', [
                'amount' => number_format((float) $this->payout->amount, 2),
            ]),
            'payout_id' => $this->payout->id,
            'icon' => 'banknotes',
            'url' => route('developer.payouts.index'),
        ];
    }
}
