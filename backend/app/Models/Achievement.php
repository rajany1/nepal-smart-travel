<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Achievement extends Model
{
    protected $fillable = [
        'name', 'display_name', 'description', 'icon',
        'category', 'criteria', 'xp_reward', 'is_system', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'criteria' => 'array',
            'is_system' => 'boolean',
            'xp_reward' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot(['unlocked_at', 'is_suspicious', 'suspicious_reason', 'flagged_by', 'cleared_at', 'cleared_by'])
            ->withTimestamps();
    }
}
