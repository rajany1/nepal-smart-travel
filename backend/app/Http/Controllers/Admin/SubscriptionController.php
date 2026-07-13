<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Models\User;
use App\Services\ModeratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    public function __construct(
        private ModeratorService $moderatorService,
    ) {}

    private function requireAdmin(Request $request): void
    {
        $user = Auth::user();
        if (!$user || !$user->isAdmin()) abort(403, 'Unauthorized');
    }

    public function plans(Request $request)
    {
        $this->requireAdmin($request);
        $plans = SubscriptionPlan::orderBy('sort_order')->orderBy('price')->paginate(20);
        return view('admin.subscription_plans', compact('plans'));
    }

    public function planStore(Request $request)
    {
        $this->requireAdmin($request);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'billing_interval' => 'required|in:monthly,yearly',
            'features' => 'nullable|json',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'required|integer|min:0',
        ]);
        $baseSlug = Str::slug($data['name']);
        $slug = $baseSlug;
        $counter = 1;
        while (SubscriptionPlan::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }
        $data['slug'] = $slug;
        if (isset($data['features']) && is_string($data['features'])) {
            $decoded = json_decode($data['features'], true);
            $data['features'] = is_array($decoded) ? $decoded : [];
        }
        $plan = SubscriptionPlan::create($data);
        $this->moderatorService->log(Auth::user(), 'subscription-plan.created', 'subscription_plan', $plan->id, 'Created plan: ' . $plan->name);
        return redirect()->route('admin.subscription.plans')->with('success', 'Plan created.');
    }

    public function planUpdate(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        $this->requireAdmin($request);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'billing_interval' => 'required|in:monthly,yearly',
            'features' => 'nullable|json',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'required|integer|min:0',
        ]);
        $baseSlug = Str::slug($data['name']);
        $slug = $baseSlug;
        $counter = 1;
        while (SubscriptionPlan::where('slug', $slug)->where('id', '!=', $subscriptionPlan->id)->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }
        $data['slug'] = $slug;
        if (isset($data['features']) && is_string($data['features'])) {
            $decoded = json_decode($data['features'], true);
            $data['features'] = is_array($decoded) ? $decoded : [];
        }
        $subscriptionPlan->update($data);
        $this->moderatorService->log(Auth::user(), 'subscription-plan.updated', 'subscription_plan', $subscriptionPlan->id, 'Updated plan: ' . $subscriptionPlan->name);
        return redirect()->route('admin.subscription.plans')->with('success', 'Plan updated.');
    }

    public function users(Request $request)
    {
        $this->requireAdmin($request);
        $filter = $request->get('filter', 'active');
        $query = UserSubscription::with(['user', 'plan']);

        if ($filter === 'active') {
            $query->whereIn('status', ['active', 'trialing']);
        } elseif ($filter === 'cancelled') {
            $query->where('status', 'cancelled');
        }

        $subscriptions = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();
        $plans = SubscriptionPlan::active()->orderBy('name')->get();
        return view('admin.user_subscriptions', compact('subscriptions', 'plans', 'filter'));
    }

    public function assignSubscription(Request $request)
    {
        $this->requireAdmin($request);
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'subscription_plan_id' => 'required|exists:subscription_plans,id',
            'status' => 'required|in:active,trialing',
            'duration_months' => 'required|integer|min:1',
        ]);

        $user = User::findOrFail($data['user_id']);
        $plan = SubscriptionPlan::findOrFail($data['subscription_plan_id']);

        UserSubscription::where('user_id', $user->id)
            ->whereIn('status', ['active', 'trialing'])
            ->update(['status' => 'cancelled', 'cancelled_at' => now(), 'ends_at' => now()]);

        UserSubscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => $data['status'],
            'starts_at' => now(),
            'ends_at' => $plan->slug === 'free' ? null : now()->addMonths((int) $data['duration_months']),
            'trial_ends_at' => $data['status'] === 'trialing' ? now()->addDays(7) : null,
        ]);

        $this->moderatorService->log(Auth::user(), 'subscription.assigned', 'user', $user->id, "Assigned '{$plan->name}' subscription to {$user->name} ({$user->email})");
        return redirect()->route('admin.subscription.users')->with('success', 'Subscription assigned.');
    }

    public function planDestroy(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        $this->requireAdmin($request);
        if ($subscriptionPlan->userSubscriptions()->exists()) {
            return back()->withErrors(['Cannot delete a plan that has active subscribers.']);
        }
        $name = $subscriptionPlan->name;
        $subscriptionPlan->delete();
        $this->moderatorService->log(Auth::user(), 'subscription-plan.deleted', 'subscription_plan', $subscriptionPlan->id, 'Deleted plan: ' . $name);
        return redirect()->route('admin.subscription.plans')->with('success', 'Plan deleted.');
    }

    public function planToggleActive(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        $this->requireAdmin($request);
        $subscriptionPlan->update(['is_active' => !$subscriptionPlan->is_active]);
        $status = $subscriptionPlan->is_active ? 'visible' : 'hidden';
        $this->moderatorService->log(Auth::user(), 'subscription-plan.toggled', 'subscription_plan', $subscriptionPlan->id, 'Toggled plan visibility: ' . $subscriptionPlan->name . " → {$status}");
        return redirect()->route('admin.subscription.plans')->with('success', "Plan is now {$status} to users.");
    }

    public function cancelSubscription(Request $request, UserSubscription $userSubscription)
    {
        $this->requireAdmin($request);
        $planName = $userSubscription->plan?->name ?? 'N/A';
        $user = $userSubscription->user;

        if ($userSubscription->plan?->slug === 'free') {
            return back()->with('error', 'Free plan cannot be cancelled.');
        }

        $userSubscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'ends_at' => now(),
        ]);

        $hasFree = UserSubscription::where('user_id', $user->id)
            ->whereHas('plan', fn($q) => $q->where('slug', 'free'))
            ->where('status', 'active')
            ->exists();

        if (!$hasFree) {
            $freePlan = SubscriptionPlan::where('slug', 'free')->first();
            if ($freePlan) {
                UserSubscription::create([
                    'user_id' => $user->id,
                    'subscription_plan_id' => $freePlan->id,
                    'status' => 'active',
                    'starts_at' => now(),
                ]);
            }
        }

        $userName = $user?->name ?? '#' . $userSubscription->user_id;
        $this->moderatorService->log(Auth::user(), 'subscription.cancelled', 'user', $user->id, "Cancelled '{$planName}' subscription for {$userName}, reverted to Free plan");
        return redirect()->route('admin.subscription.users')->with('success', 'Subscription cancelled. User reverted to Free plan.');
    }
}
