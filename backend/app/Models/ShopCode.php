<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShopCode extends Model
{
    protected $fillable = [
        'shop_item_id', 'code', 'is_used',
        'purchased_by', 'used_at',
        'booking_id', 'applied_at', 'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_used' => 'boolean',
            'used_at' => 'datetime',
            'applied_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function shopItem(): BelongsTo
    {
        return $this->belongsTo(ShopItem::class);
    }

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchased_by');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_used', false)->whereNull('booking_id')->whereNull('consumed_at');
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where('is_used', true)->where('purchased_by', $user->id)->whereNull('booking_id')->whereNull('consumed_at');
    }
}
