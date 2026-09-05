<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PartnerPayment extends Model
{
    protected $table = 'partner_payments';

    protected $fillable = [
        'partner_id',
        'user_id',
        'amount',
        'payment_method',
        'payment_id',
        'commission_percent',
        'commission_amount',
        'partner_amount',
        'redeem_code',
        'qr_data',
        'status',
        'paid_at',
        'scanned_at',
        'scanned_by',
        'expires_at',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'commission_percent' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'partner_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'scanned_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PartnerPayment $payment) {
            if (empty($payment->redeem_code)) {
                $payment->redeem_code = 'PAY-' . strtoupper(Str::random(6));
            }
            if (empty($payment->qr_data)) {
                $payment->qr_data = json_encode([
                    'type' => 'partner_payment',
                    'code' => $payment->redeem_code,
                    'partner_id' => $payment->partner_id,
                    'amount' => $payment->amount,
                    'ts' => now()->timestamp,
                ]);
            }
            if (empty($payment->expires_at)) {
                $payment->expires_at = now()->addHours(24);
            }
        });
    }

    public function partner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TravelPartner::class, 'partner_id');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scanner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast() && $this->status === 'pending';
    }

    public function markCompleted(User $scannedBy): bool
    {
        if ($this->status !== 'pending') {
            return false;
        }
        if ($this->isExpired()) {
            $this->update(['status' => 'expired']);
            return false;
        }

        $this->update([
            'status' => 'completed',
            'scanned_at' => now(),
            'scanned_by' => $scannedBy->id,
        ]);

        // Credit partner wallet
        $wallet = PartnerWallet::getForPartner($this->partner_id);
        $wallet->credit((float) $this->partner_amount);

        return true;
    }
}
