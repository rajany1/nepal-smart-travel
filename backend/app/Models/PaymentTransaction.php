<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $table = 'payment_transactions';

    protected $fillable = [
        'reference_id',
        'method',
        'amount_npr',
        'account_details',
        'status',
        'idempotency_key',
        'gateway_transaction_id',
        'gateway_response',
        'failure_reason',
        'metadata',
        'completed_at',
        'user_id',
        'partner_id',
        'withdrawal_id',
        'partner_withdrawal_id',
        'payout_id',
        'topup_id',
    ];

    protected $casts = [
        'amount_npr' => 'decimal:2',
        'account_details' => 'array',
        'gateway_response' => 'array',
        'metadata' => 'array',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(TravelPartner::class, 'partner_id');
    }

    public function withdrawal(): BelongsTo
    {
        return $this->belongsTo(Withdrawal::class);
    }

    public function partnerWithdrawal(): BelongsTo
    {
        return $this->belongsTo(PartnerWithdrawal::class, 'partner_withdrawal_id');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'processing']);
    }
}