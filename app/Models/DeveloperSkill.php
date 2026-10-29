<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeveloperSkill extends Model
{
    public const LEVELS = [
        1 => 'Beginner',
        2 => 'Intermediate',
        3 => 'Advanced',
        4 => 'Expert',
        5 => 'Master',
    ];

    protected $fillable = [
        'user_id', 'skill', 'level', 'verified',
    ];

    protected $casts = [
        'level' => 'integer',
        'verified' => 'boolean',
    ];

    public function user() { return $this->belongsTo(User::class); }

    public function getLevelLabelAttribute(): string
    {
        return self::LEVELS[$this->level] ?? 'Unknown';
    }

    public static function levelOptions(): array
    {
        $result = [];
        foreach (self::LEVELS as $key => $label) {
            $result[$key] = 'skill.level_' . $key;
        }
        return $result;
    }
}
