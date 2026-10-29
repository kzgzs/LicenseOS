<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformEvent extends Model
{
    protected $table = 'events';

    protected $fillable = [
        'type', 'payload', 'processed',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed' => 'boolean',
    ];

    public function markProcessed(): void
    {
        $this->update(['processed' => true]);
    }

    public function scopeUnprocessed($query)
    {
        return $query->where('processed', false);
    }

    public static function dispatch(string $type, array $payload): self
    {
        return self::create([
            'type' => $type,
            'payload' => $payload,
        ]);
    }
}
