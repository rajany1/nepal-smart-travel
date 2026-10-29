<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BadWord;
use App\Models\ContentViolation;
use App\Models\GameSetting;
use App\Models\ModerationStrike;
use App\Models\User;
use App\Services\AdminSafetyService;
use App\Services\ContentSafetyService;
use App\Services\ModeratorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SafetyController extends Controller
{
    public function __construct(
        private ModeratorService $moderatorService,
        private AdminSafetyService $safetyService,
    ) {}

    private function requireAdmin(Request $request): void
    {
        $user = Auth::user();
        if (! $user || (! $user->isAdmin() && ! $user->isModerator())) {
            abort(403, 'Unauthorized');
        }

        $routeName = $request->route()?->getName();
        if ($routeName) {
            $routePerms = \App\Models\Permission::where('route_name', $routeName)->get();
            if ($routePerms->isNotEmpty() && ! $routePerms->contains(fn ($p) => $user->hasPermission($p->name))) {
                abort(403, 'You do not have permission for this page.');
            }
        }
    }

    /**
     * Content-safety specific gate. Viewing the queue needs manage_moderation
     * (legacy) or manage_content_safety; wordlist edits and strikes use the
     * same gate so a plain moderator cannot act on safety cases.
     */
    private function requireSafety(Request $request): void
    {
        $this->requireAdmin($request);

        $user = Auth::user();
        if (! $user->hasPermission('manage_content_safety') && ! $user->hasPermission('manage_moderation')) {
            abort(403, 'You do not have permission to manage content safety.');
        }
    }

    private function canManageSafety(): bool
    {
        $user = Auth::user();

        return $user
            && ($user->hasPermission('manage_content_safety') || $user->hasPermission('manage_moderation'));
    }

    public function index(Request $request)
    {
        $this->requireAdmin($request);

        $tab = in_array($request->get('tab'), ['violations', 'users', 'words'], true)
            ? $request->get('tab')
            : 'violations';

        $perPage = (int) $request->get('per_page', 20);
        if (! in_array($perPage, [15, 20, 25, 50, 100], true)) {
            $perPage = 20;
        }

        $violations = null;
        $users = null;
        $words = null;

        // Only build the query for the active tab: the users tab runs two
        // correlated counts over ~1k accounts, no need to pay for it on the
        // violation log.
        if ($tab === 'users') {
            $users = $this->usersQuery($request, $perPage);
        } elseif ($tab === 'words') {
            $words = $this->wordsQuery($request, $perPage);
        } else {
            $violations = $this->violationsQuery($request, $perPage);
        }

        return view('admin.moderation', [
            'tab' => $tab,
            'violations' => $violations,
            'users' => $users,
            'words' => $words,
            'insights' => $this->safetyService->insights(),
            'canManageSafety' => $this->canManageSafety(),
        ]);
    }

    private function violationsQuery(Request $request, int $perPage)
    {
        $query = ContentViolation::with('user');

        $search = trim((string) $request->get('q', ''));
        if ($search !== '') {
            $query->where(function ($w) use ($search) {
                $w->where('original_text', 'like', "%{$search}%")
                    ->orWhere('censored_text', 'like', "%{$search}%")
                    ->orWhere('entity_type', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('entity') && array_key_exists($request->entity, AdminSafetyService::ENTITY_LABELS)) {
            $query->where('entity_type', $request->entity);
        }

        if (in_array($request->get('severity'), ['mild', 'moderate', 'severe'], true)) {
            $query->where('severity', $request->get('severity'));
        }

        if (in_array($request->get('source'), array_keys(AdminSafetyService::SOURCE_LABELS), true)) {
            $query->where('source', $request->get('source'));
        }

        $range = $request->get('date_range');
        if (in_array($range, ['today', '7d', '30d', '90d'], true)) {
            $query->where('created_at', '>=', match ($range) {
                'today' => now()->startOfDay(),
                '7d' => now()->subDays(6)->startOfDay(),
                '30d' => now()->subDays(29)->startOfDay(),
                '90d' => now()->subDays(89)->startOfDay(),
            });
        }

        $sort = $request->get('sort', 'newest');
        if ($sort === 'oldest') {
            $query->orderBy('created_at')->orderBy('id');
        } elseif ($sort === 'severity') {
            $query->orderByRaw("FIELD(severity, 'severe', 'moderate', 'mild')")
                ->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at')->orderByDesc('id');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    private function usersQuery(Request $request, int $perPage)
    {
        $windowDays = max(1, (int) GameSetting::getValue('safety_strike_window_days', 30));

        $query = User::query()
            ->withCount([
                'moderationStrikes as moderation_strikes_count' => fn ($q) => $q->where('created_at', '>=', now()->subDays($windowDays)),
                'contentViolations as content_violations_count',
            ])
            ->withMax('contentViolations', 'created_at');

        $search = trim((string) $request->get('q', ''));
        if ($search !== '') {
            $query->where(function ($w) use ($search) {
                $w->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (in_array($request->get('status'), ['active', 'suspended', 'banned'], true)) {
            $query->where('status', $request->get('status'));
        }

        switch ($request->get('sort', 'violations')) {
            case 'strikes':
                $query->orderByDesc('moderation_strikes_count')->orderByDesc('content_violations_count');
                break;
            case 'name':
                $query->orderBy('name');
                break;
            case 'recent':
                $query->orderByDesc('content_violations_max_created_at')->orderByDesc('content_violations_count');
                break;
            default:
                $query->orderByDesc('content_violations_count')->orderByDesc('content_violations_max_created_at');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    private function wordsQuery(Request $request, int $perPage)
    {
        $query = BadWord::query();

        $search = trim((string) $request->get('q', ''));
        if ($search !== '') {
            $query->where('word', 'like', "%{$search}%");
        }

        if (in_array($request->get('severity'), ['mild', 'moderate', 'severe'], true)) {
            $query->where('severity', $request->get('severity'));
        }

        if (in_array($request->get('status'), ['active', 'inactive'], true)) {
            $query->where('active', $request->get('status') === 'active');
        }

        switch ($request->get('sort', 'alpha')) {
            case 'newest':
                $query->orderByDesc('created_at');
                break;
            case 'severity':
                $query->orderByRaw("FIELD(severity, 'severe', 'moderate', 'mild')")->orderBy('word');
                break;
            default:
                $query->orderBy('word');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    public function showUser(Request $request, User $user)
    {
        $this->requireAdmin($request);

        $windowDays = max(1, (int) GameSetting::getValue('safety_strike_window_days', 30));
        $warnAt = (int) GameSetting::getValue('safety_warn_at_strikes', 1);
        $suspendAt = (int) GameSetting::getValue('safety_suspend_at_strikes', 2);
        $banAt = (int) GameSetting::getValue('safety_ban_at_strikes', 3);

        $strikes = ModerationStrike::where('user_id', $user->id)->orderByDesc('created_at')->get();
        $violations = ContentViolation::with('user')->where('user_id', $user->id)->orderByDesc('created_at')->paginate(25)->withQueryString();
        $totalViolations = ContentViolation::where('user_id', $user->id)->count();

        $activeStrikes = $strikes->where('created_at', '>=', now()->subDays($windowDays))->count();
        $nextResult = $activeStrikes + 1;

        $safety = [
            'window_days' => $windowDays,
            'warn_at' => $warnAt,
            'suspend_at' => $suspendAt,
            'ban_at' => $banAt,
            'active_strikes' => $activeStrikes,
            'auto_strikes' => $strikes->whereNull('issued_by')->count(),
            'manual_strikes' => $strikes->whereNotNull('issued_by')->count(),
            'next_action' => $nextResult >= $banAt ? 'Permanent block' : ($nextResult >= $suspendAt ? 'Suspension' : ($nextResult >= $warnAt ? 'Warning' : 'No escalation')),
            'violations_30d' => ContentViolation::where('user_id', $user->id)
                ->where('created_at', '>=', now()->subDays($windowDays))
                ->count(),
        ];

        return view('admin.moderation_user', compact('user', 'strikes', 'violations', 'totalViolations', 'safety'))
            ->with('canManageSafety', $this->canManageSafety());
    }

    public function strike(Request $request, User $user)
    {
        $this->requireSafety($request);

        if ((int) $user->id === (int) Auth::id()) {
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
        $this->requireSafety($request);

        if ($user->status === 'active') {
            return back()->with('error', 'User is already active.');
        }

        $previousStatus = $user->status;
        app(ContentSafetyService::class)->activate($user);
        $this->moderatorService->log(Auth::user(), 'user.activated', 'user', $user->id, "Activated {$user->name} (was {$previousStatus}).");

        return back()->with('success', "{$user->name} has been reactivated.");
    }

    /**
     * Add a word to the censor dictionary. ContentSafetyService reads the
     * active list per scan, so a new entry protects the very next submission.
     */
    public function storeWord(Request $request)
    {
        $this->requireSafety($request);

        $data = $request->validate([
            'word' => ['required', 'string', 'min:2', 'max:100'],
            'severity' => ['required', Rule::in(['mild', 'moderate', 'severe'])],
        ]);

        $word = mb_strtolower(trim($data['word']));

        if (BadWord::whereRaw('LOWER(word) = ?', [$word])->exists()) {
            return back()->with('error', "\"{$word}\" is already in the dictionary.");
        }

        BadWord::create([
            'word' => $word,
            'severity' => $data['severity'],
            'active' => true,
        ]);

        $this->moderatorService->log(Auth::user(), 'safety.word.created', 'bad_word', null, "Added dictionary word \"{$word}\" ({$data['severity']}).");

        return back()->with('success', "\"{$word}\" added to the content safety dictionary.");
    }

    public function updateWord(Request $request, BadWord $badWord)
    {
        $this->requireSafety($request);

        $data = $request->validate([
            'severity' => ['sometimes', Rule::in(['mild', 'moderate', 'severe'])],
            'active' => ['sometimes', 'boolean'],
        ]);

        $changes = [];
        if ($request->filled('severity')) {
            $changes['severity'] = $data['severity'];
        }
        if ($request->has('active')) {
            $changes['active'] = $request->boolean('active');
        }

        if ($changes === []) {
            return back()->with('error', 'No dictionary change submitted.');
        }

        $badWord->update($changes);

        $this->moderatorService->log(Auth::user(), 'safety.word.updated', 'bad_word', $badWord->id,
            "Updated dictionary word \"{$badWord->word}\": ".json_encode($changes));

        return back()->with('success', "\"{$badWord->word}\" updated.");
    }

    public function destroyWord(Request $request, BadWord $badWord)
    {
        $this->requireSafety($request);

        $word = $badWord->word;
        $badWord->delete();

        $this->moderatorService->log(Auth::user(), 'safety.word.deleted', 'bad_word', null, "Deleted dictionary word \"{$word}\".");

        return back()->with('success', "\"{$word}\" removed from the dictionary.");
    }
}
