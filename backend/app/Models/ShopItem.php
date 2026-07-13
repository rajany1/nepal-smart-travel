<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopItem extends Model
{
    protected $fillable = [
        'name', 'description', 'icon', 'sponsor_id',
        'reward_type', 'discount_type', 'discount_value',
        'price_xp', 'min_level', 'stock_type',
        'stock_qty', 'is_active', 'sort_order',
        'terms', 'expiry_days', 'usage_limit_per_user',
        'redemption_instructions',
    ];

    protected function casts(): array
    {
        return [
            'price_xp' => 'integer',
            'min_level' => 'integer',
            'stock_qty' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'expiry_days' => 'integer',
            'usage_limit_per_user' => 'integer',
            'discount_value' => 'decimal:2',
        ];
    }

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function codes(): HasMany
    {
        return $this->hasMany(ShopCode::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(UserPurchase::class);
    }

    public function availableCodes(): HasMany
    {
        return $this->hasMany(ShopCode::class)->where('is_used', false);
    }

    public function isInStock(): bool
    {
        return match ($this->stock_type) {
            'unlimited' => true,
            'limited' => $this->stock_qty > 0,
            'code_pool' => $this->availableCodes()->exists(),
        };
    }

    public function decrementStock(): void
    {
        if ($this->stock_type === 'limited' && $this->stock_qty > 0) {
            $this->decrement('stock_qty');
        }
    }

    public function incrementStock(): void
    {
        if ($this->stock_type === 'limited') {
            $this->increment('stock_qty');
        }
    }
}
