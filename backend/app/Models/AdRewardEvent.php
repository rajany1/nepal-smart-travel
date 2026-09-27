<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\CoinSetting;

class AdRewardEvent extends Model
{
    protected $fillable = [
        'ad_campaign_id',
        'user_id',
        'report_id',
        'event_type',
        'event_status',
        'gross_amount',
        'user_share',
        'admin_share',
        'coins_credited',
        'coin_to_npr_rate',
        'user_share_percent',
        'idempotency_key',
        'metadata',
        'event_time',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:4',
        'user_share' => 'decimal:4',
        'admin_share' => 'decimal:4',
        'coins_credited' => 'decimal:4',
        'coin_to_npr_rate' => 'decimal:4',
        'user_share_percent' => 'decimal:2',
        'metadata' => 'array',
        'event_time' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AdCampaign::class, 'ad_campaign_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * Generate a canonical idempotency key for an ad event.
     *
     * Uses the configured cooldown window to produce a deterministic key.
     * Same logical event always produces the same key.
     *
     * @param string $prefix 'imp' or 'click'
     * @param int $userId
     * @param int $campaignId
     * @return string
     */
    public static function generateIdempotencyKey(string $prefix, int $userId, int $campaignId): string
    {
        $cooldownMinutes = (int) CoinSetting::getValue('impression_cooldown_minutes', 10);
        $window = (int) (now()->timestamp / ($cooldownMinutes * 60));
        return "{$prefix}:{$userId}:{$campaignId}:{$window}";
    }
}
