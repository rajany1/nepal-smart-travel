<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PartnerWallet extends Model
{
    protected $table = 'partner_wallets';

    protected $fillable = [
        'partner_id',
        'balance',
        'total_earned',
        'total_withdrawn',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'total_earned' => 'decimal:2',
            'total_withdrawn' => 'decimal:2',
        ];
    }

    public function partner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(TravelPartner::class, 'partner_id');
    }

    public function credit(float $amount): void
    {
        $this->increment('balance', $amount);
        $this->increment('total_earned', $amount);
    }

    public function debit(float $amount): bool
    {
        if ((float) $this->balance < $amount) {
            return false;
        }
        $this->decrement('balance', $amount);
        $this->increment('total_withdrawn', $amount);
        return true;
    }

    public function canWithdraw(float $amount): bool
    {
        return (float) $this->balance >= $amount;
    }

    public static function getForPartner(int $partnerId): self
    {
        return static::firstOrCreate(
            ['partner_id' => $partnerId],
            ['balance' => 0, 'total_earned' => 0, 'total_withdrawn' => 0]
        );
    }
}
