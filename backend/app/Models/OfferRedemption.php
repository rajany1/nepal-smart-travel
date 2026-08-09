<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferRedemption extends Model
{
    protected $fillable = [
        'offer_id',
        'user_id',
        'booking_id',
        'code',
        'value_npr',
        'commission_percent',
        'admin_commission',
        'partner_earnings',
        'status',
        'claimed_at',
        'used_at',
        'applied_at',
        'consumed_at',
        'discount_amount',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
        'used_at' => 'datetime',
        'applied_at' => 'datetime',
        'consumed_at' => 'datetime',
        'discount_amount' => 'float',
        'value_npr' => 'float',
        'commission_percent' => 'float',
        'admin_commission' => 'float',
        'partner_earnings' => 'float',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(RewardOffer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'claimed')
            ->whereNull('booking_id')
            ->whereNull('consumed_at');
    }

    public function isApplied(): bool
    {
        return $this->booking_id !== null && $this->consumed_at === null;
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}