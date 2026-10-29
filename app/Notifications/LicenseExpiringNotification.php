<?php

namespace App\Notifications;

use App\Models\License;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LicenseExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(protected License $license) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $daysLeft = $this->license->expire_at
            ? now()->diffInDays($this->license->expire_at, false)
            : null;

        return [
            'title' => __('notification.license_expiring_title'),
            'message' => __('notification.license_expiring_message', [
                'product' => $this->license->product?->name ?? 'N/A',
                'days' => max(0, (int) $daysLeft),
            ]),
            'license_id' => $this->license->id,
            'icon' => 'exclamation-triangle',
            'url' => route('my-licenses'),
        ];
    }
}
