<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Alert extends Model
{
    protected $fillable = [
        'uuid',
        'title',
        'title_ne',
        'description',
        'alert_type',
        'severity',
        'latitude',
        'longitude',
        'expires_at',
        'affected_district',
        'created_by',
        'source_type',
        'source_id',
        'is_broadcast',
        'target_user_id',
        'sender_type',
        'link_type',
        'link_value',
        'bipad_id',
        'bipad_hazard_id',
        'bipad_source',
        'bipad_raw',
    ];

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * Alerts currently visible to users: no expiry, or an expiry in the future.
     */
    public function scopeActive($query)
    {
        return $query->where(function ($builder) {
            $builder->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Alerts whose expiry time has passed.
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expires_at')->where('expires_at', '<=', now());
    }

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'expires_at' => 'datetime',
            'is_broadcast' => 'boolean',
            'bipad_id' => 'integer',
            'bipad_hazard_id' => 'integer',
            'bipad_raw' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Alert $alert) {
            if (empty($alert->uuid)) {
                $alert->uuid = (string) Str::uuid();
            }
        });
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}