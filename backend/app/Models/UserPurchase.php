<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPurchase extends Model
{
    protected $fillable = [
        'user_id', 'shop_item_id', 'xp_spent', 'status',
        'fulfillment_note', 'fulfilled_by', 'fulfilled_at',
        'cancelled_at', 'cancellation_reason', 'shop_code_id',
    ];

    protected function casts(): array
    {
        return [
            'xp_spent' => 'integer',
            'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shopItem(): BelongsTo
    {
        return $this->belongsTo(ShopItem::class);
    }

    public function fulfiller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfilled_by');
    }

    public function shopCode(): BelongsTo
    {
        return $this->belongsTo(ShopCode::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }
}
