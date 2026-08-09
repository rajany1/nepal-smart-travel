<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentViolation;
use App\Models\ModerationStrike;
use App\Models\User;
use App\Services\ContentSafetyService;
use App\Services\ModeratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SafetyController extends Controller
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
            $routePerms = \App\Models\Permission::where('route_name', $routeName)->get();
            if ($routePerms->isNotEmpty() && !$routePerms->contains(fn($p) => $user->hasPermission($p->name))) {
                abort(403, 'You do not have permission for this page.');
            }
        }
    }

    public function index(Request $request)
    {
        $this->requireAdmin($request);

        $tab = $request->get('tab', 'violations');

        $violations = ContentViolation::with('user')
            ->when($request->get('q'), fn($q, $term) => $q->whereHas('user', fn($uq) => $uq->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
            ->when($request->get('entity'), fn($q, $e) => $q->where('entity_type', $e))
            ->when($request->get('severity'), fn($q, $s) => $q->where('severity', $s))
            ->orderByDesc('created_at')
            ->paginate(20);

        $users = User::withCount([
            'moderationStrikes' => fn ($q) => $q->where('created_at', '>=', now()->subDays((int) \App\Models\GameSetting::getValue('safety_strike_window_days', 30))),
            'contentViolations',
        ])
            ->when($request->get('q'), fn ($q, $term) => $q->where(function ($wq) use ($term) {
                $wq->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%");
            }))
            ->orderByDesc('content_violations_count')
            ->paginate(20);

        $stats = [
            'violations_total' => ContentViolation::count(),
            'violations_today' => ContentViolation::whereDate('created_at', today())->count(),
            'unique_users' => ContentViolation::distinct('user_id')->count('user_id'),
            'censored' => ContentViolation::where('action', 'censored')->count(),
            'warnings' => ModerationStrike::where('level', 'warning')->count(),
            'suspensions' => ModerationStrike::where('level', 'suspend')->count(),
            'blocks' => ModerationStrike::where('level', 'block')->count(),
            'suspended_now' => User::where('status', 'suspended')->where(function ($q) {
                $q->whereNull('suspended_until')->orWhere('suspended_until', '>', now());
            })->count(),
            'banned_now' => User::where('status', 'banned')->count(),
        ];

        return view('admin.moderation', compact('tab', 'violations', 'users', 'stats'));
    }

    public function showUser(Request $request, User $user)
    {
        $this->requireAdmin($request);

        $strikes = ModerationStrike::where('user_id', $user->id)->orderByDesc('created_at')->get();
        $violations = ContentViolation::with('user')->where('user_id', $user->id)->orderByDesc('created_at')->paginate(25);
        $totalViolations = ContentViolation::where('user_id', $user->id)->count();

        return view('admin.moderation_user', compact('user', 'strikes', 'violations', 'totalViolations'));
    }

    public function strike(Request $request, User $user)
    {
        $this->requireAdmin($request);

        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot punish your own account.');
        }
        if ($user->isAdmin()) {
            return back()->with('error', 'Cannot apply a strike to an admin user.');
        }

        $data = $request->validate([
            'level' => 'required|in:warning,suspend,block',
            'reason' => 'required|string|min:3|max:1000',
        ]);

        app(ContentSafetyService::class)->manualStrike($user, $data['level'], $data['reason'], Auth::user());

        $this->moderatorService->log(Auth::user(), 'user.strike', 'user', $user->id,
            "Manual {$data['level']} on {$user->name}: {$data['reason']}");

        $message = match ($data['level']) {
            'warning' => "Warning issued to {$user->name}.",
            'suspend' => "{$user->name} has been suspended.",
            'block' => "{$user->name} has been blocked.",
        };

        return back()->with('success', $message);
    }

    public function activate(Request $request, User $user)
    {
        $this->requireAdmin($request);

        if ($user->status === 'active') {
            return back()->with('error', 'User is already active.');
        }

        app(ContentSafetyService::class)->activate($user);
        $this->moderatorService->log(Auth::user(), 'user.activated', 'user', $user->id, "Activated {$user->name} (was {$user->status}).");

        return back()->with('success', "{$user->name} has been reactivated.");
    }
}