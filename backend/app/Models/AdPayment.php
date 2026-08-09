<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdPayment extends Model
{
    protected $fillable = [
        'business_id',
        'ad_campaign_id',
        'gateway',
        'amount',
        'status',
        'transaction_id',
        'reference',
        'metadata',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'metadata' => 'array',
        'paid_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(TravelPartner::class, 'business_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }
}
