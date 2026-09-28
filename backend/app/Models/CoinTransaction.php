<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CoinTransaction extends Model
{
    protected $table = 'coin_transactions';

    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'ad_campaign_id',
        'report_id',
        'reverses_transaction_id',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adCampaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * The original credit this transaction reverses (null for ordinary rows).
     */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(CoinTransaction::class, 'reverses_transaction_id');
    }

    /**
     * The compensating reversal created for this credit (if any).
     */
    public function reversedBy(): HasOne
    {
        return $this->hasOne(CoinTransaction::class, 'reverses_transaction_id')->latestOfMany();
    }
}
