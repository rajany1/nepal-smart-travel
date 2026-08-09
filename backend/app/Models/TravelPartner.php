<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelPartner extends Model
{
    protected $fillable = [
        'name', 'type', 'description', 'logo', 'phone', 'email',
        'website', 'address', 'district', 'commission_rate',
        'commission_fixed', 'value_npr', 'is_active',
        'user_id', 'verification_status', 'rejected_reason',
    ];

    protected function casts(): array
    {
        return [
            'commission_rate' => 'decimal:2',
            'commission_fixed' => 'decimal:2',
            'value_npr' => 'decimal:2',
            'is_active' => 'boolean',
            'verification_status' => 'string',
        ];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(RewardOffer::class, 'business_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function sponsors(): HasMany
    {
        return $this->hasMany(Sponsor::class);
    }

    public function adCampaigns(): HasMany
    {
        return $this->hasMany(AdCampaign::class, 'business_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function offerEarned(): float
    {
        return (float) OfferRedemption::whereIn('offer_id', $this->offers()->pluck('id'))
            ->where('status', 'used')
            ->sum('partner_earnings');
    }

    public function payoutsHeld(): float
    {
        return (float) $this->payouts()->whereIn('status', ['pending', 'paid'])->sum('amount');
    }

    public function payoutBalance(): float
    {
        return round($this->offerEarned() - $this->payoutsHeld(), 2);
    }
}
