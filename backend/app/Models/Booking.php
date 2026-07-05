<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    protected $fillable = [
        'travel_partner_id', 'user_id', 'customer_name',
        'customer_phone', 'customer_email', 'amount',
        'commission_earned', 'reward_pool_share', 'status',
        'notes', 'booked_at', 'confirmed_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'commission_earned' => 'decimal:2',
            'reward_pool_share' => 'decimal:2',
            'booked_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function travelPartner(): BelongsTo
    {
        return $this->belongsTo(TravelPartner::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function commissionTransaction(): HasOne
    {
        return $this->hasOne(CommissionTransaction::class);
    }

    public function isPending(): bool { return $this->status === 'pending'; }
    public function isConfirmed(): bool { return $this->status === 'confirmed'; }
    public function isCompleted(): bool { return $this->status === 'completed'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }
}
