<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    protected $fillable = [
        'travel_partner_id',
        'amount',
        'status',
        'payment_method',
        'payment_detail',
        'note',
        'admin_note',
        'requested_at',
        'processed_at',
        'processed_by',
    ];

    protected $casts = [
        'amount' => 'float',
        'requested_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(TravelPartner::class, 'travel_partner_id');
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }
}
