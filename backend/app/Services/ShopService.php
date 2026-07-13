<?php

namespace App\Services;

use App\Models\ShopItem;
use App\Models\ShopCode;
use App\Models\UserPurchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShopService
{
    public function __construct(
        private AchievementService $achievementService,
    ) {}

    public function purchase(User $user, ShopItem $item): UserPurchase
    {
        if (!$item->is_active) {
            throw new \RuntimeException('This item is not available for purchase.');
        }

        if (!$item->isInStock()) {
            throw new \RuntimeException('This item is currently out of stock.');
        }

        if ($user->current_level < $item->min_level) {
            throw new \RuntimeException("You need level {$item->min_level} to purchase this item.");
        }

        if ($user->total_xp < $item->price_xp) {
            throw new \RuntimeException('Insufficient XP.');
        }

        if ($item->usage_limit_per_user) {
            $count = UserPurchase::where('user_id', $user->id)
                ->where('shop_item_id', $item->id)
                ->whereIn('status', ['completed', 'pending'])
                ->count();
            if ($count >= $item->usage_limit_per_user) {
                throw new \RuntimeException("You can only purchase this item {$item->usage_limit_per_user} time(s).");
            }
        }

        return DB::transaction(function () use ($user, $item) {
            $this->achievementService->deductXp(
                $user,
                $item->price_xp,
                'shop_purchase',
                "Purchased: {$item->name}"
            );

            // Try to assign a pre-uploaded code (code_pool), else auto-generate one
            $shopCode = null;

            if ($item->stock_type === 'code_pool') {
                $shopCode = $item->availableCodes()->lockForUpdate()->first();
            }

            if (!$shopCode) {
                $item->decrementStock();
                $codeStr = 'RWD-' . Str::upper(Str::random(8));
                while (ShopCode::where('code', $codeStr)->exists()) {
                    $codeStr = 'RWD-' . Str::upper(Str::random(8));
                }
                $shopCode = ShopCode::create([
                    'shop_item_id' => $item->id,
                    'code' => $codeStr,
                ]);
            }

            $shopCode->update([
                'is_used' => true,
                'purchased_by' => $user->id,
                'used_at' => now(),
            ]);

            $purchase = UserPurchase::create([
                'user_id' => $user->id,
                'shop_item_id' => $item->id,
                'xp_spent' => $item->price_xp,
                'status' => 'completed',
                'shop_code_id' => $shopCode->id,
                'fulfilled_at' => now(),
            ]);

            return $purchase;
        });
    }

    public function fulfill(UserPurchase $purchase, User $admin, ?string $note = null): UserPurchase
    {
        if (!$purchase->isPending()) {
            throw new \RuntimeException('Only pending purchases can be fulfilled.');
        }

        $purchase->update([
            'status' => 'completed',
            'fulfilled_by' => $admin->id,
            'fulfillment_note' => $note,
            'fulfilled_at' => now(),
        ]);

        return $purchase;
    }

    public function cancel(UserPurchase $purchase, User $admin, string $reason): UserPurchase
    {
        if (!$purchase->isPending()) {
            throw new \RuntimeException('Only pending purchases can be cancelled.');
        }

        DB::transaction(function () use ($purchase, $reason) {
            $purchase->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            $item = $purchase->shopItem;
            if ($item) {
                $item->incrementStock();
            }

            $user = $purchase->user;
            $this->achievementService->awardXp(
                $user,
                $purchase->xp_spent,
                'shop_refund',
                "Refund for cancelled purchase: {$purchase->shopItem->name}",
                $purchase
            );
        });

        return $purchase;
    }

    public function refund(UserPurchase $purchase, User $admin, string $reason): UserPurchase
    {
        if (!$purchase->isCompleted()) {
            throw new \RuntimeException('Only completed purchases can be refunded.');
        }

        DB::transaction(function () use ($purchase, $reason) {
            $purchase->update([
                'status' => 'refunded',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);

            if ($purchase->shop_code_id) {
                $purchase->shopCode->update([
                    'is_used' => false,
                    'purchased_by' => null,
                    'used_at' => null,
                ]);
            }

            $item = $purchase->shopItem;
            if ($item) {
                $item->incrementStock();
            }

            $user = $purchase->user;
            $this->achievementService->awardXp(
                $user,
                $purchase->xp_spent,
                'shop_refund',
                "Refund for completed purchase: {$purchase->shopItem->name}",
                $purchase
            );
        });

        return $purchase;
    }

    public function getItems(bool $includeInactive = false)
    {
        $query = ShopItem::with('sponsor')->orderBy('sort_order')->orderBy('name');
        if (!$includeInactive) {
            $query->where('is_active', true);
        }
        return $query->get();
    }

    public function getUserPurchases(User $user, ?string $status = null)
    {
        $query = UserPurchase::where('user_id', $user->id)
            ->with('shopItem.sponsor', 'shopCode')
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->get();
    }

    public function getUserAvailableCodes(User $user)
    {
        return ShopCode::forUser($user)->with('shopItem')->get();
    }

    public function applyToBooking(User $user, ShopCode $code, Booking $booking): void
    {
        if ($code->purchased_by !== $user->id) {
            throw new \RuntimeException('This code does not belong to you.');
        }
        if ($code->booking_id || $code->consumed_at) {
            throw new \RuntimeException('This code has already been applied or consumed.');
        }

        $item = $code->shopItem;
        $discount = 0;
        if ($item && $item->discount_type && $item->discount_value) {
            if ($item->discount_type === 'percentage') {
                $discount = $booking->amount * $item->discount_value / 100;
            } elseif ($item->discount_type === 'fixed') {
                $discount = $item->discount_value;
            }
            $discount = min($discount, $booking->amount);
        }

        $code->update([
            'booking_id' => $booking->id,
            'applied_at' => now(),
        ]);

        if ($discount > 0) {
            $booking->update([
                'discount_amount' => $discount,
                'amount' => $booking->amount - $discount,
            ]);
        }
    }

    public function releaseFromBooking(ShopCode $code): void
    {
        $booking = $code->booking;
        if ($booking && $booking->discount_amount > 0) {
            $booking->update([
                'amount' => $booking->amount + $booking->discount_amount,
                'discount_amount' => 0,
            ]);
        }

        $code->update([
            'booking_id' => null,
            'applied_at' => null,
        ]);
    }

    public function getAllPurchases(?string $status = null)
    {
        $query = UserPurchase::with(['user', 'shopItem', 'fulfiller'])
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate(20);
    }

    public function uploadCodes(ShopItem $item, array $codes): int
    {
        $created = 0;
        foreach ($codes as $code) {
            $code = trim($code);
            if (!empty($code)) {
                ShopCode::create([
                    'shop_item_id' => $item->id,
                    'code' => $code,
                ]);
                $created++;
            }
        }
        return $created;
    }
}
