<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderPaidNotification extends Notification
{
    use Queueable;

    public function __construct(protected Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('notification.order_paid_title'),
            'message' => __('notification.order_paid_message', [
                'product' => $this->order->product?->name ?? 'N/A',
                'amount' => number_format((float) $this->order->amount, 2),
            ]),
            'order_id' => $this->order->id,
            'icon' => 'shopping-cart',
            'url' => route('my-orders'),
        ];
    }
}
