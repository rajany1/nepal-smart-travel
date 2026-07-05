<?php

namespace App\Http\Controllers;

use App\Models\ShopItem;
use App\Services\ShopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StoreController extends Controller
{
    public function __construct(
        private ShopService $shopService,
    ) {}

    public function items(): JsonResponse
    {
        $items = $this->shopService->getItems();
        return response()->json(['data' => $items]);
    }

    public function purchase(Request $request, ShopItem $shopItem): JsonResponse
    {
        $user = Auth::user();

        try {
            $purchase = $this->shopService->purchase($user, $shopItem);
            return response()->json([
                'message' => $purchase->status === 'completed'
                    ? 'Purchase successful! Check your code below.'
                    : 'Purchase submitted. An admin will process it shortly.',
                'data' => $purchase->load('shopItem.sponsor', 'shopCode'),
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function myPurchases(): JsonResponse
    {
        $purchases = $this->shopService->getUserPurchases(Auth::user());
        return response()->json(['data' => $purchases]);
    }
}
