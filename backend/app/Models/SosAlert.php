<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class SosAlert extends Model
{
    protected $fillable = [
        'user_id',
        'latitude',
        'longitude',
        'location_accuracy',
        'status',
        'emergency_type',
        'message',
        'started_at',
        'last_location_update_at',
        'resolved_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'location_accuracy' => 'decimal:2',
        'started_at' => 'datetime',
        'last_location_update_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeNearby(Builder $query, float $lat, float $lng, float $radiusKm = 5): Builder
    {
        $latDelta = $radiusKm / 111.0;
        $lngDelta = $radiusKm / (111.0 * cos(deg2rad($lat)));

        return $query->active()
            ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta]);
    }

    public function isExpired(): bool
    {
        return $this->status === 'active' &&
            $this->started_at &&
            $this->started_at->diffInHours(now()) >= 2;
    }

    public function getDurationAttribute(): ?int
    {
        if (!$this->started_at) return null;
        $end = $this->resolved_at ?? now();
        return $this->started_at->diffInSeconds($end);
    }
}
