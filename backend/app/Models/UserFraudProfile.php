<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFraudProfile extends Model
{
    protected $fillable = [
        'user_id',
        'fraud_score',
        'fraud_flags',
        'is_suspicious',
        'suspicious_reason',
    ];

    protected function casts(): array
    {
        return [
            'fraud_flags' => 'array',
            'fraud_score' => 'integer',
            'is_suspicious' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function getForUser(int $userId): self
    {
        return static::firstOrCreate(['user_id' => $userId], [
            'fraud_score' => 0,
            'fraud_flags' => [],
            'is_suspicious' => false,
        ]);
    }
}
