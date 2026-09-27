<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerWalletTransaction extends Model
{
    protected $fillable = [
        'partner_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'actor_id',
        'description',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
    ];

    public $timestamps = false;

    public function partner(): BelongsTo
    {
        return $this->belongsTo(TravelPartner::class, 'partner_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Get the referenced model (e.g., PartnerPayment, PartnerWithdrawal).
     */
    public function reference()
    {
        if (!$this->reference_type || !$this->reference_id) {
            return null;
        }

        $modelClass = match($this->reference_type) {
            'PartnerPayment' => PartnerPayment::class,
            'PartnerWithdrawal' => PartnerWithdrawal::class,
            'Payout' => Payout::class,
            default => null,
        };

        return $modelClass ? $modelClass::find($this->reference_id) : null;
    }
}
