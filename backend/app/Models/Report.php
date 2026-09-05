<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Report extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'category_id',
        'place_id',
        'title',
        'description',
        'priority',
        'status',
        'moderation_message',
        'authenticity_score',
        'latitude',
        'longitude',
        'district',
        'helpful_count',
        'unhelpful_count',
        'comments_count',
        'verified_by',
        'verified_at',
        'photo_gps_lat',
        'photo_gps_lng',
        'gps_verification_status',
        'gps_distance_km',
        'photo_captured_at',
        'is_live_capture',
        'ai_analysis',
        'ai_analyzed_at',
        'location_name',
        'report_subcategory',
        'is_active',
        'expires_at',
        'confirmed_by_count',
        'last_confirmed_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'photo_gps_lat' => 'decimal:7',
        'photo_gps_lng' => 'decimal:7',
        'gps_distance_km' => 'decimal:3',
        'is_live_capture' => 'boolean',
        'is_active' => 'boolean',
        'photo_captured_at' => 'datetime',
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_confirmed_at' => 'datetime',
        'ai_analysis' => 'array',
        'ai_analyzed_at' => 'datetime',
        'authenticity_score' => 'decimal:2',
        'helpful_count' => 'integer',
        'unhelpful_count' => 'integer',
        'comments_count' => 'integer',
        'confirmed_by_count' => 'integer',
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ReportCategorie::class, 'category_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(ReportComment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(ReportReaction::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(ReportMedia::class);
    }

    public function confirmations(): HasMany
    {
        return $this->hasMany(ReportConfirmation::class);
    }

    public function getConfidenceScoreAttribute(): int
    {
        $authScore = (int) ($this->authenticity_score ?? 0);
        $confirmBonus = min(100, $this->confirmed_by_count * 15);
        return min(100, $confirmBonus + $authScore);
    }

    public function getTimeStateAttribute(): string
    {
        $minutesAgo = now()->diffInMinutes($this->created_at);
        if ($minutesAgo < 60) return 'live';
        if ($this->created_at->isToday()) return 'today';
        if ($this->created_at->diffInDays(now()) <= 7) return 'recent';
        return 'expired';
    }

    protected static function booted(): void
    {
        static::creating(function ($report) {
            if (empty($report->uuid)) {
                $report->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }
}