<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Payout;
use App\Services\ModeratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PayoutController extends Controller
{
    public function __construct(
        private ModeratorService $moderatorService,
    ) {}

    private function requireAdmin(Request $request): void
    {
        $user = Auth::user();
        if (!$user || !$user->isAdmin() && !$user->isModerator()) abort(403, 'Unauthorized');

        $routeName = $request->route()?->getName();
        if ($routeName) {
            $routePerms = Permission::where('route_name', $routeName)->get();
            if ($routePerms->isNotEmpty() && !$routePerms->contains(fn($p) => $user->hasPermission($p->name))) {
                abort(403, 'You do not have permission for this page.');
            }
        }
    }

    public function index(Request $request)
    {
        $this->requireAdmin($request);
        $status = $request->get('status');
        $query = Payout::with('partner');
        if ($status) $query->where('status', $status);
        $payouts = $query->orderByRaw("CASE WHEN status = 'pending' THEN 0 WHEN status = 'rejected' THEN 2 ELSE 1 END")
            ->orderBy('id', 'desc')
            ->paginate(20);

        $stats = [
            'pending' => Payout::where('status', 'pending')->count(),
            'pending_total' => (float) Payout::where('status', 'pending')->sum('amount'),
            'paid' => Payout::where('status', 'paid')->count(),
            'paid_total' => (float) Payout::where('status', 'paid')->sum('amount'),
            'rejected' => Payout::where('status', 'rejected')->count(),
        ];

        return view('admin.payouts', compact('payouts', 'status', 'stats'));
    }

    public function markPaid(Request $request, Payout $payout)
    {
        $this->requireAdmin($request);
        abort_if($payout->status !== 'pending', 422, 'Only pending payouts can be marked as paid.');

        $payout->update([
            'status' => 'paid',
            'processed_at' => now(),
            'processed_by' => Auth::id(),
            'admin_note' => $request->input('admin_note') ?: $payout->admin_note,
        ]);
        $this->moderatorService->log(
            Auth::user(),
            'payout.paid',
            'payout',
            $payout->id,
            'Payout paid: Rs. ' . number_format($payout->amount, 2) . ' to ' . ($payout->partner?->name ?? 'partner'),
        );

        return back()->with('success', 'Payout marked as paid.');
    }

    public function reject(Request $request, Payout $payout)
    {
        $this->requireAdmin($request);
        abort_if($payout->status !== 'pending', 422, 'Only pending payouts can be rejected.');

        $data = $request->validate(['admin_note' => 'required|string|max:1000']);

        $payout->update([
            'status' => 'rejected',
            'processed_at' => now(),
            'processed_by' => Auth::id(),
            'admin_note' => $data['admin_note'],
        ]);
        $this->moderatorService->log(
            Auth::user(),
            'payout.rejected',
            'payout',
            $payout->id,
            'Payout rejected: ' . $data['admin_note'],
        );

        return back()->with('success', 'Payout rejected.');
    }
}
