<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserAchievement extends Pivot
{
    protected $table = 'user_achievements';

    protected $fillable = [
        'user_id', 'achievement_id', 'unlocked_at', 'metadata',
        'is_suspicious', 'suspicious_reason', 'flagged_by',
        'cleared_at', 'cleared_by',
    ];

    protected function casts(): array
    {
        return [
            'unlocked_at' => 'datetime',
            'metadata' => 'array',
            'is_suspicious' => 'boolean',
            'cleared_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function achievement()
    {
        return $this->belongsTo(Achievement::class);
    }

    public function flagger()
    {
        return $this->belongsTo(User::class, 'flagged_by');
    }

    public function clearer()
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }
}
