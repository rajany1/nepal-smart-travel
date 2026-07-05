<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShopItem;
use App\Models\Sponsor;
use App\Models\UserPurchase;
use App\Services\ShopService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StoreController extends Controller
{
    public function __construct(
        private ShopService $shopService,
    ) {}

    private function requireAdmin(Request $request): void
    {
        $user = Auth::user();
        if (!$user || !$user->isAdmin() && !$user->isModerator()) {
            abort(403, 'Unauthorized');
        }

        $routeName = $request->route()?->getName();
        if ($routeName) {
            $routePerms = \App\Models\Permission::where('route_name', $routeName)->get();
            if ($routePerms->isNotEmpty() && !$routePerms->contains(fn($p) => $user->hasPermission($p->name))) {
                abort(403, 'You do not have permission for this page.');
            }
        }
    }

    public function items(Request $request)
    {
        $this->requireAdmin($request);
        $items = $this->shopService->getItems(includeInactive: true);
        $sponsors = Sponsor::active()->orderBy('sort_order')->orderBy('name')->get();
        $itemsJson = $items->map(fn($i) => [
            'id' => $i->id,
            'name' => $i->name,
            'icon' => $i->icon,
            'description' => $i->description,
            'sponsor_id' => $i->sponsor_id,
            'reward_type' => $i->reward_type,
            'price_xp' => $i->price_xp,
            'min_level' => $i->min_level,
            'stock_type' => $i->stock_type,
            'stock_qty' => $i->stock_qty,
            'is_active' => $i->is_active,
            'sort_order' => $i->sort_order,
            'terms' => $i->terms,
            'expiry_days' => $i->expiry_days,
            'usage_limit_per_user' => $i->usage_limit_per_user,
        ])->values();
        return view('admin.store_items', compact('items', 'sponsors', 'itemsJson'));
    }

    public function store(Request $request)
    {
        $this->requireAdmin($request);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'required|string|max:50',
            'sponsor_id' => 'nullable|exists:sponsors,id',
            'reward_type' => 'required|in:discount,free_item,voucher,special_offer',
            'price_xp' => 'required|integer|min:1',
            'min_level' => 'required|integer|min:1',
            'stock_type' => 'required|in:unlimited,limited,code_pool',
            'stock_qty' => 'required|integer|min:0',
            'terms' => 'nullable|string',
            'expiry_days' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'nullable|integer|min:1',
            'redemption_instructions' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'required|integer|min:0',
        ]);

        ShopItem::create($data);

        return redirect()->route('admin.store.items')
            ->with('success', 'Shop item created successfully.');
    }

    public function update(Request $request, ShopItem $shopItem)
    {
        $this->requireAdmin($request);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'required|string|max:50',
            'sponsor_id' => 'nullable|exists:sponsors,id',
            'reward_type' => 'required|in:discount,free_item,voucher,special_offer',
            'price_xp' => 'required|integer|min:1',
            'min_level' => 'required|integer|min:1',
            'stock_type' => 'required|in:unlimited,limited,code_pool',
            'stock_qty' => 'required|integer|min:0',
            'terms' => 'nullable|string',
            'expiry_days' => 'nullable|integer|min:1',
            'usage_limit_per_user' => 'nullable|integer|min:1',
            'redemption_instructions' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'required|integer|min:0',
        ]);

        $shopItem->update($data);

        return redirect()->route('admin.store.items')
            ->with('success', 'Shop item updated successfully.');
    }

    public function uploadCodes(Request $request, ShopItem $shopItem)
    {
        $this->requireAdmin($request);

        $request->validate([
            'codes' => 'required|string',
        ]);

        $codes = explode("\n", $request->input('codes'));
        $count = $this->shopService->uploadCodes($shopItem, $codes);

        return redirect()->route('admin.store.items')
            ->with('success', "{$count} codes uploaded successfully.");
    }

    public function orders(Request $request)
    {
        $this->requireAdmin($request);

        $status = $request->get('status');
        $purchases = $this->shopService->getAllPurchases($status);

        return view('admin.store_orders', compact('purchases', 'status'));
    }

    public function fulfill(Request $request, UserPurchase $userPurchase)
    {
        $this->requireAdmin($request);
        $admin = Auth::user();

        try {
            $this->shopService->fulfill($userPurchase, $admin, $request->input('note'));
            return redirect()->route('admin.store.orders')
                ->with('success', 'Purchase fulfilled successfully.');
        } catch (\RuntimeException $e) {
            return redirect()->route('admin.store.orders')
                ->with('error', $e->getMessage());
        }
    }

    public function cancel(Request $request, UserPurchase $userPurchase)
    {
        $this->requireAdmin($request);
        $admin = Auth::user();

        $request->validate(['reason' => 'required|string']);

        try {
            $this->shopService->cancel($userPurchase, $admin, $request->input('reason'));
            return redirect()->route('admin.store.orders')
                ->with('success', 'Purchase cancelled and XP refunded.');
        } catch (\RuntimeException $e) {
            return redirect()->route('admin.store.orders')
                ->with('error', $e->getMessage());
        }
    }

    public function refund(Request $request, UserPurchase $userPurchase)
    {
        $this->requireAdmin($request);
        $admin = Auth::user();

        $request->validate(['reason' => 'required|string']);

        try {
            $this->shopService->refund($userPurchase, $admin, $request->input('reason'));
            return redirect()->route('admin.store.orders')
                ->with('success', 'Purchase refunded and XP returned, code recycled.');
        } catch (\RuntimeException $e) {
            return redirect()->route('admin.store.orders')
                ->with('error', $e->getMessage());
        }
    }
}
