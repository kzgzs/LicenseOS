<?php

namespace App\Notifications;

use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewReviewNotification extends Notification
{
    use Queueable;

    public function __construct(protected Review $review) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => __('notification.new_review_title'),
            'message' => __('notification.new_review_message', [
                'product' => $this->review->product?->name ?? 'N/A',
                'rating' => $this->review->rating,
            ]),
            'review_id' => $this->review->id,
            'icon' => 'star',
            'url' => route('developer.reviews.index'),
        ];
    }
}
