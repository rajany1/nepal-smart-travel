<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RewardOffer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'title',
        'offer_type',
        'discount_value',
        'value_npr',
        'value_npr_locked',
        'price_xp',
        'description',
        'terms',
        'image',
        'status',
        'paused_by',
        'starts_at',
        'ends_at',
        'usage_limit',
        'used_count',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'admin_removed_reason',
    ];

    protected $casts = [
        'discount_value' => 'float',
        'value_npr' => 'float',
        'value_npr_locked' => 'boolean',
        'price_xp' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(TravelPartner::class, 'business_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(OfferRedemption::class, 'offer_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'approved')
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function label(): string
    {
        return match ($this->offer_type) {
            'percentage_off' => $this->discount_value . '% OFF',
            'fixed_off' => 'Rs. ' . number_format((float) $this->discount_value) . ' OFF',
            'free_item' => 'FREE ' . $this->title,
            'buy_one_get_one' => 'BUY 1 GET 1',
            default => $this->title,
        };
    }

    public function isEnded(): bool
    {
        return $this->ends_at !== null && $this->ends_at->lte(now());
    }

    public function isActive(): bool
    {
        if ($this->status !== 'approved') {
            return false;
        }
        if ($this->starts_at !== null && $this->starts_at->gt(now())) {
            return false;
        }
        if ($this->isEnded()) {
            return false;
        }
        if ($this->usage_limit !== null && (int) $this->usage_limit > 0 && (int) $this->used_count >= (int) $this->usage_limit) {
            return false;
        }
        return true;
    }

    public function isSystemLocked(): bool
    {
        return $this->paused_by === 'system' || $this->isEnded();
    }

    /**
     * Auto-expire ended offers: status -> paused, paused_by -> system.
     * Returns number of offers updated.
     */
    public static function expireEnded(): int
    {
        return static::where('status', 'approved')
            ->where('ends_at', '<=', now())
            ->update(['status' => 'paused', 'paused_by' => 'system']);

    }
}
