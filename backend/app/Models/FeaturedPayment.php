<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeaturedPayment extends Model
{
    protected $fillable = [
        'place_id', 'featured_type', 'amount', 'currency',
        'payment_method', 'status', 'duration_months', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'duration_months' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }
}
