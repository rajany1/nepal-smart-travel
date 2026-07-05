<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionTransaction extends Model
{
    protected $fillable = [
        'booking_id', 'total_commission', 'reward_pool_contribution',
        'platform_revenue', 'status', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'total_commission' => 'decimal:2',
            'reward_pool_contribution' => 'decimal:2',
            'platform_revenue' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
