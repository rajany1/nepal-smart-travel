<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\User;
use App\Services\AchievementService;
use App\Services\ModeratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AchievementController extends Controller
{
    public function __construct(
        private ModeratorService $moderatorService,
        private AchievementService $achievementService,
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

    public function index(Request $request)
    {
        $this->requireAdmin($request);

        $achievements = Achievement::withCount('users')
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->paginate(20);

        $categories = Achievement::select('category')
            ->distinct()
            ->pluck('category')
            ->toArray();

        return view('admin.achievements', compact('achievements', 'categories'));
    }

    public function store(Request $request)
    {
        $this->requireAdmin($request);

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:achievements,name',
            'display_name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'icon' => 'required|string|max:50',
            'category' => 'required|string|max:50',
            'criteria' => 'nullable|json',
            'xp_reward' => 'required|integer|min:0|max:10000',
            'sort_order' => 'required|integer|min:0',
        ]);

        if ($validated['criteria']) {
            $validated['criteria'] = json_decode($validated['criteria'], true);
        }

        Achievement::create($validated);

        $this->moderatorService->log(
            Auth::user(),
            'achievement.created',
            'achievement',
            null,
            'Created achievement: ' . $validated['display_name'],
        );

        return back()->with('success', 'Achievement created successfully.');
    }

    public function edit(Achievement $achievement)
    {
        $this->requireAdmin(request());
        return response()->json($achievement);
    }

    public function update(Request $request, Achievement $achievement)
    {
        $this->requireAdmin($request);

        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:achievements,name,' . $achievement->id,
            'display_name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'icon' => 'required|string|max:50',
            'category' => 'required|string|max:50',
            'criteria' => 'nullable|json',
            'xp_reward' => 'required|integer|min:0|max:10000',
            'sort_order' => 'required|integer|min:0',
        ]);

        if ($validated['criteria']) {
            $validated['criteria'] = json_decode($validated['criteria'], true);
        }

        $achievement->update($validated);

        $this->moderatorService->log(
            Auth::user(),
            'achievement.updated',
            'achievement',
            $achievement->id,
            'Updated achievement: ' . $validated['display_name'],
        );

        return back()->with('success', 'Achievement updated successfully.');
    }

    public function destroy(Request $request, Achievement $achievement)
    {
        $this->requireAdmin($request);

        if ($achievement->is_system) {
            return back()->with('error', 'System achievements cannot be deleted.');
        }

        if ($achievement->users()->count() > 0) {
            return back()->with('error', 'Cannot delete achievement that users have already unlocked.');
        }

        $achievement->delete();

        $this->moderatorService->log(
            Auth::user(),
            'achievement.deleted',
            'achievement',
            $achievement->id,
            'Deleted achievement: ' . $achievement->display_name,
        );

        return back()->with('success', 'Achievement deleted successfully.');
    }

    public function userProgress(Request $request, User $user)
    {
        $this->requireAdmin($request);

        $achievements = $this->achievementService->getUserAchievements($user);
        $xpHistory = $this->achievementService->getXpHistory($user, 30);
        $stats = $this->achievementService->getUserStats($user);
        $nextLevelXp = $this->achievementService->getNextLevelXp($user->current_level ?? 1);
        $levelProgress = $nextLevelXp > 0 ? min($user->total_xp / $nextLevelXp, 1.0) : 1.0;

        return view('admin.user_progress', compact(
            'user', 'achievements', 'xpHistory', 'stats',
            'nextLevelXp', 'levelProgress'
        ));
    }

    public function adjustXp(Request $request, User $user)
    {
        $this->requireAdmin($request);

        $validated = $request->validate([
            'amount' => 'required|integer',
            'reason' => 'required|string|max:500',
        ]);

        if ($validated['amount'] > 0) {
            $this->achievementService->awardXp(
                $user, $validated['amount'], 'manual_adjust',
                "Admin adjustment: {$validated['reason']}"
            );
        } elseif ($validated['amount'] < 0) {
            $this->achievementService->deductXp(
                $user, abs($validated['amount']), 'manual_adjust',
                "Admin adjustment: {$validated['reason']}"
            );
        }

        $this->moderatorService->log(
            Auth::user(),
            'xp.manual_adjust',
            'user',
            $user->id,
            "Adjusted XP by {$validated['amount']} for {$user->name}: {$validated['reason']}",
        );

        return back()->with('success', "XP adjusted by {$validated['amount']} for {$user->name}.");
    }

    public function recalculateLevel(Request $request, User $user)
    {
        $this->requireAdmin($request);

        $this->achievementService->recalculateLevelForUser($user);

        $this->moderatorService->log(
            Auth::user(),
            'xp.recalculate_level',
            'user',
            $user->id,
            "Recalculated level for {$user->name}",
        );

        return back()->with('success', "Level recalculated for {$user->name}.");
    }

    public function flagAchievement(Request $request, $userAchievementId)
    {
        $this->requireAdmin($request);

        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $this->achievementService->flagAchievement($userAchievementId, $validated['reason'], Auth::id());

        $ua = \App\Models\UserAchievement::with('achievement', 'user')->findOrFail($userAchievementId);

        $this->moderatorService->log(
            Auth::user(),
            'achievement.flagged',
            'user_achievement',
            $userAchievementId,
            "Flagged achievement '{$ua->achievement->display_name}' for {$ua->user->name}: {$validated['reason']}",
        );

        return back()->with('success', 'Achievement flagged as suspicious.');
    }

    public function clearSuspicious(Request $request, $userAchievementId)
    {
        $this->requireAdmin($request);

        $this->achievementService->clearSuspicious($userAchievementId, Auth::id());

        $ua = \App\Models\UserAchievement::with('achievement', 'user')->findOrFail($userAchievementId);

        $this->moderatorService->log(
            Auth::user(),
            'achievement.suspicious_cleared',
            'user_achievement',
            $userAchievementId,
            "Cleared suspicious flag on achievement '{$ua->achievement->display_name}' for {$ua->user->name}",
        );

        return back()->with('success', 'Suspicious flag cleared on achievement.');
    }
}
