<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdCampaign extends Model
{
    protected $fillable = [
        'name', 'business_id', 'ad_type', 'content', 'image',
        'target_url', 'target_district', 'target_category',
        'contexts', 'budget', 'paid_amount', 'spent_amount', 'payment_status', 'paused_by', 'gateway', 'gateway_ref',
        'max_impressions', 'current_impressions', 'current_clicks',
        'status', 'rejection_reason', 'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'spent_amount' => 'decimal:2',
            'cost_per_view' => 'decimal:2',
            'cost_per_click' => 'decimal:2',
            'contexts' => 'array',
            'max_impressions' => 'integer',
            'current_impressions' => 'integer',
            'current_clicks' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(TravelPartner::class, 'business_id');
    }

    public function impressions(): HasMany
    {
        return $this->hasMany(AdImpression::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(AdClick::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            });
    }

    public function hasBudget(): bool
    {
        if ((float) $this->budget > 0 && (float) $this->spent_amount >= (float) $this->budget) {
            return false;
        }
        if ($this->max_impressions > 0) {
            return $this->current_impressions < $this->max_impressions;
        }
        return true;
    }

    public function calculateSpend(): float
    {
        $cpm = (float) GameSetting::getValue('ad_cpm', 50);
        $cpc = (float) GameSetting::getValue('ad_cpc', 10);
        return round(($this->current_impressions / 1000) * $cpm + $this->current_clicks * $cpc, 2);
    }

    public function budgetRemaining(): float
    {
        return round((float) $this->budget - (float) $this->spent_amount, 2);
    }

    public function ctr(): float
    {
        if ($this->current_impressions <= 0) return 0;
        return round($this->current_clicks / $this->current_impressions * 100, 2);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(AdPayment::class, 'ad_campaign_id');
    }

    public function matchesContext(?string $context): bool
    {
        if (empty($this->contexts)) return true;
        if ($context === null) return false;
        return in_array($context, $this->contexts, true);
    }
}
