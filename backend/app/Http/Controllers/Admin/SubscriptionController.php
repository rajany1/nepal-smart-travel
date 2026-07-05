<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
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
        SubscriptionPlan::create($data);
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
        $subscriptionPlan->update($data);
        return redirect()->route('admin.subscription.plans')->with('success', 'Plan updated.');
    }

    public function users(Request $request)
    {
        $this->requireAdmin($request);
        $subscriptions = UserSubscription::with(['user', 'plan'])
            ->orderBy('created_at', 'desc')->paginate(20);
        $plans = SubscriptionPlan::active()->orderBy('name')->get();
        return view('admin.user_subscriptions', compact('subscriptions', 'plans'));
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

        UserSubscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => $data['status'],
            'starts_at' => now(),
            'ends_at' => now()->addMonths((int) $data['duration_months']),
            'trial_ends_at' => $data['status'] === 'trialing' ? now()->addDays(7) : null,
        ]);

        return redirect()->route('admin.subscription.users')->with('success', 'Subscription assigned.');
    }

    public function planDestroy(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        $this->requireAdmin($request);
        if ($subscriptionPlan->userSubscriptions()->exists()) {
            return back()->withErrors(['Cannot delete a plan that has active subscribers.']);
        }
        $subscriptionPlan->delete();
        return redirect()->route('admin.subscription.plans')->with('success', 'Plan deleted.');
    }

    public function planToggleActive(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        $this->requireAdmin($request);
        $subscriptionPlan->update(['is_active' => !$subscriptionPlan->is_active]);
        $status = $subscriptionPlan->is_active ? 'visible' : 'hidden';
        return redirect()->route('admin.subscription.plans')->with('success', "Plan is now {$status} to users.");
    }

    public function cancelSubscription(Request $request, UserSubscription $userSubscription)
    {
        $this->requireAdmin($request);
        $userSubscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'ends_at' => now(),
        ]);
        return redirect()->route('admin.subscription.users')->with('success', 'Subscription cancelled.');
    }
}
