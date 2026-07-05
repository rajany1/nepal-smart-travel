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
        'budget', 'cost_per_view', 'max_impressions',
        'current_impressions', 'status', 'starts_at', 'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'budget' => 'decimal:2',
            'cost_per_view' => 'decimal:2',
            'max_impressions' => 'integer',
            'current_impressions' => 'integer',
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
        if ($this->max_impressions > 0) {
            return $this->current_impressions < $this->max_impressions;
        }
        return true;
    }
}
