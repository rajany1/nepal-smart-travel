<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $table = 'idempotency_keys';

    protected $fillable = [
        'key',
        'user_id',
        'route',
        'method',
        'request_body',
        'status',
        'response_code',
        'response_body',
        'response_headers',
        'completed_at',
    ];

    protected $casts = [
        'response_headers' => 'array',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope('recent', function ($query) {
            $query->where('created_at', '>=', now()->subDays(7));
        });
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}