<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    protected $table = 'notification_log';

    protected $fillable = [
        'user_id', 'channel', 'type', 'subject', 'body', 'sent',
    ];

    protected $casts = [
        'sent' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeSent($query)
    {
        return $query->where('sent', true);
    }

    public function scopeForType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
