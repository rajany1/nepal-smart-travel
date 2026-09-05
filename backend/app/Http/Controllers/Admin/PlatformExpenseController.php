<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformExpense;
use App\Models\EmployeeSalary;
use App\Models\AdRevenueLedger;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PlatformExpenseController extends Controller
{
    private function requireAdmin(Request $request): void
    {
        $user = Auth::user();
        if (!$user || !$user->isAdmin() && !$user->isModerator()) abort(403, 'Unauthorized');
    }

    public function index(Request $request)
    {
        $this->requireAdmin($request);

        $filter = $request->get('filter', 'all');
        $category = $request->get('category');

        $query = PlatformExpense::withTrashed();

        if ($filter === 'active') $query->where('status', 'active');
        elseif ($filter === 'expired') $query->expired();
        elseif ($filter === 'renewal') $query->renewalSoon(7);
        elseif ($filter === 'inactive') $query->where('status', 'inactive');

        if ($category) $query->where('category', $category);

        $expenses = $query->orderBy('next_renewal_date', 'asc')->paginate(20);

        $totalMonthly = PlatformExpense::active()->get()->sum('monthly_equivalent');
        $renewalSoon = PlatformExpense::renewalSoon(7)->count();
        $expired = PlatformExpense::expired()->count();

        return view('admin.platform-expenses', compact('expenses', 'totalMonthly', 'renewalSoon', 'expired', 'filter', 'category'));
    }

    public function store(Request $request)
    {
        $this->requireAdmin($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|in:' . implode(',', array_keys(PlatformExpense::CATEGORIES)),
            'provider' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'billing_cycle' => 'required|string|in:' . implode(',', array_keys(PlatformExpense::BILLING_CYCLES)),
            'next_renewal_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'alert_days_before' => 'nullable|integer|min:1|max:90',
        ]);

        PlatformExpense::create($validated);

        return redirect()->back()->with('success', 'Expense added successfully.');
    }

    public function update(Request $request, PlatformExpense $expense)
    {
        $this->requireAdmin($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|in:' . implode(',', array_keys(PlatformExpense::CATEGORIES)),
            'provider' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'billing_cycle' => 'required|string|in:' . implode(',', array_keys(PlatformExpense::BILLING_CYCLES)),
            'next_renewal_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'status' => 'required|string|in:active,inactive,cancelled',
            'alert_days_before' => 'nullable|integer|min:1|max:90',
        ]);

        $expense->update($validated);

        return redirect()->back()->with('success', 'Expense updated successfully.');
    }

    public function destroy(PlatformExpense $expense)
    {
        $this->requireAdmin(request());

        $expense->delete();
        return redirect()->back()->with('success', 'Expense deleted.');
    }

    public function markPaid(PlatformExpense $expense)
    {
        $this->requireAdmin(request());

        $expense->markAsPaid();
        return redirect()->back()->with('success', 'Expense marked as paid. Next renewal date updated.');
    }

    public function employees(Request $request)
    {
        $this->requireAdmin($request);

        $month = $request->get('month', Carbon::now()->month);
        $year = $request->get('year', Carbon::now()->year);

        $salaries = EmployeeSalary::with('user')
            ->whereMonth('period_start', $month)
            ->whereYear('period_start', $year)
            ->orderBy('employee_name')
            ->get();

        $totalPending = EmployeeSalary::pending()
            ->whereMonth('period_start', $month)
            ->whereYear('period_start', $year)
            ->sum('net_salary');

        $totalPaid = EmployeeSalary::paid()
            ->whereMonth('period_start', $month)
            ->whereYear('period_start', $year)
            ->sum('net_salary');

        return view('admin.employee-salaries', compact('salaries', 'totalPending', 'totalPaid', 'month', 'year'));
    }

    public function storeSalary(Request $request)
    {
        $this->requireAdmin($request);

        $validated = $request->validate([
            'employee_name' => 'required|string|max:255',
            'position' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'base_salary' => 'required|numeric|min:0',
            'bonus' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|numeric|min:0',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'notes' => 'nullable|string',
        ]);

        $validated['net_salary'] = $validated['base_salary'] + ($validated['bonus'] ?? 0) - ($validated['deductions'] ?? 0);
        $validated['payment_status'] = 'pending';

        EmployeeSalary::create($validated);

        return redirect()->back()->with('success', 'Salary record added.');
    }

    public function updateSalary(Request $request, EmployeeSalary $salary)
    {
        $this->requireAdmin($request);

        $validated = $request->validate([
            'employee_name' => 'required|string|max:255',
            'position' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'base_salary' => 'required|numeric|min:0',
            'bonus' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|numeric|min:0',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'notes' => 'nullable|string',
        ]);

        $validated['net_salary'] = $validated['base_salary'] + ($validated['bonus'] ?? 0) - ($validated['deductions'] ?? 0);

        $salary->update($validated);

        return redirect()->back()->with('success', 'Salary record updated.');
    }

    public function deleteSalary(EmployeeSalary $salary)
    {
        $this->requireAdmin(request());

        $salary->delete();

        return redirect()->back()->with('success', 'Salary record deleted.');
    }

    public function markSalaryPaid(EmployeeSalary $salary)
    {
        $this->requireAdmin(request());

        $salary->markAsPaid();
        return redirect()->back()->with('success', 'Salary marked as paid.');
    }

    public function financialOverview(Request $request)
    {
        $this->requireAdmin($request);

        $month = (int) $request->get('month', Carbon::now()->month);
        $year = (int) $request->get('year', Carbon::now()->year);

        $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        // Revenue
        $adRevenue = AdRevenueLedger::whereBetween('created_at', [$startOfMonth, $endOfMonth])->sum('admin_share');
        $bookingRevenue = \App\Models\Booking::where('status', 'completed')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->sum('commission_earned');
        $subscriptionRevenue = \App\Models\UserSubscription::where('user_subscriptions.status', 'active')
            ->whereBetween('user_subscriptions.created_at', [$startOfMonth, $endOfMonth])
            ->join('subscription_plans', 'user_subscriptions.subscription_plan_id', '=', 'subscription_plans.id')
            ->sum('subscription_plans.price');

        $totalRevenue = $adRevenue + $bookingRevenue + $subscriptionRevenue;

        // Expenses
        $monthlyExpenses = PlatformExpense::active()->get()->sum('monthly_equivalent');
        $salaryExpenses = EmployeeSalary::forMonth($month, $year)->sum('net_salary');
        $totalExpenses = $monthlyExpenses + $salaryExpenses;

        // Rewards
        $coinSettings = \App\Models\CoinSetting::first();
        $coinToNpr = (float) ($coinSettings->coin_to_npr_rate ?? 1);

        $pointsIssued = \App\Models\CoinTransaction::whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->where('type', 'ad_impression')
            ->sum('amount');

        $pointsRedeemed = \App\Models\OriporiCoinWallet::sum('total_withdrawn');
        $outstandingPoints = \App\Models\OriporiCoinWallet::sum('total_earned') - $pointsRedeemed;
        $rewardCost = $pointsRedeemed * $coinToNpr;

        $netProfit = $totalRevenue - $totalExpenses - $rewardCost;

        // Upcoming renewals
        $upcomingRenewals = PlatformExpense::renewalSoon(30)
            ->orderBy('next_renewal_date')
            ->get();

        $data = compact(
            'month', 'year', 'adRevenue', 'bookingRevenue', 'subscriptionRevenue', 'totalRevenue',
            'monthlyExpenses', 'salaryExpenses', 'totalExpenses',
            'pointsIssued', 'pointsRedeemed', 'outstandingPoints', 'rewardCost',
            'netProfit', 'upcomingRenewals'
        );

        return view('admin.financial-overview', $data);
    }

    public function renewalAlerts()
    {
        $this->requireAdmin(request());

        $expired = PlatformExpense::expired()->get();
        $renewalIn7Days = PlatformExpense::renewalSoon(7)->get();

        $alerts = $expired->map(fn($e) => ['expense' => $e, 'type' => 'expired'])
            ->merge($renewalIn7Days->map(fn($e) => ['expense' => $e, 'type' => 'renewal']));

        return response()->json([
            'alerts' => $alerts,
            'expired_count' => $expired->count(),
            'renewal_count' => $renewalIn7Days->count(),
        ]);
    }
}
