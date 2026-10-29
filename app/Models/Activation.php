<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activation extends Model
{
    use HasFactory;

    protected $fillable = [
        'license_id',
        'domain',
        'ip',
        'last_check',
        'is_tampered',
        'tampered_at',
    ];

    protected $casts = [
        'last_check' => 'datetime',
        'is_tampered' => 'boolean',
        'tampered_at' => 'datetime',
    ];

    public function license()
    {
        return $this->belongsTo(License::class);
    }
}
