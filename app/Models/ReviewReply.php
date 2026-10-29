<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewReply extends Model
{
    protected $fillable = [
        'review_id', 'review_parent_id', 'user_id', 'content', 'reply_type',
    ];

    protected $casts = [
        'reply_type' => 'string',
    ];

    public function review()
    {
        return $this->belongsTo(Review::class);
    }

    public function reviewParent()
    {
        return $this->belongsTo(Review::class, 'review_parent_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
