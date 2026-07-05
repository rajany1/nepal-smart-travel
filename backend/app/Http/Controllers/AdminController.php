<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Models\User;
use App\Models\Report;
use App\Models\Alert;
use App\Models\Place;
use App\Models\ReportCategorie;
use App\Models\PlaceCategories;
use App\Models\ModerationQueue;
use App\Models\AuditLog;
use App\Services\AchievementService;
use App\Services\PushNotificationService;
use App\Services\ModeratorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\GameSetting;

class AdminController extends Controller
{
    private ModeratorService $moderatorService;

    public function __construct(ModeratorService $moderatorService)
    {
        $this->moderatorService = $moderatorService;
    }

    private function requireAdmin(Request $request): void
    {
        $user = Auth::user();

        if (!$user || (!$user->isAdmin() && !$user->isModerator())) {
            $this->moderatorService->logSecurity(
                'security.unauthorized-access',
                ($user ? "Regular user '{$user->email}'" : 'Unauthenticated visitor') . " tried to access admin page: {$request->path()}",
                $user
            );
            if ($request->expectsJson()) {
                abort(403, 'Unauthorized');
            }
            throw new HttpResponseException(
                redirect('/admin/login')->with('error', 'Please log in to access the admin panel.')
            );
        }

        $routeName = $request->route()?->getName();
        if ($routeName) {
            $routePerms = \App\Models\Permission::where('route_name', $routeName)->get();
            if ($routePerms->isNotEmpty() && !$routePerms->contains(fn($p) => $user->hasPermission($p->name))) {
                $this->moderatorService->logSecurity(
                    'security.permission-denied',
                    "User '{$user->email}' lacks any required permission for route: {$routeName}",
                    $user
                );
                if ($request->expectsJson()) {
                    abort(403, 'Unauthorized');
                }
                throw new HttpResponseException(
                    redirect('/admin/dashboard')->with('error', 'You do not have the required permission for this page.')
                );
            }
        }
    }

    private function requirePermission(string $permission): void
    {
        $user = Auth::user();
        if (!$user || !$this->moderatorService->userHasPermission($user, $permission)) {
            $this->moderatorService->logSecurity(
                'security.permission-denied',
                ($user ? "User '{$user->email}'" : 'Unknown') . " was denied permission '{$permission}' on " . request()->path(),
                $user
            );
            abort(403, 'You do not have permission to perform this action.');
        }
    }

    private function logAction(string $action, ?string $resourceType = null, ?int $resourceId = null, ?string $description = null, ?array $metadata = null): void
    {
        $this->moderatorService->log(
            Auth::user(),
            $action,
            $resourceType,
            $resourceId,
            $description,
            $metadata,
        );
    }

    private function getAdminPageKey(Request $request): string
    {
        $routeName = $request->route()?->getName() ?? '';
        $map = [
            'admin.dashboard' => 'dashboard',
            'admin.reports' => 'reports',
            'admin.reports.approve' => 'reports',
            'admin.reports.reject' => 'reports',
            'admin.reports.delete' => 'reports',
            'admin.reports.view' => 'reports',
            'admin.users' => 'users',
            'admin.users.toggle-status' => 'users',
            'admin.users.make-admin' => 'users',
            'admin.users.remove-admin' => 'users',
            'admin.users.make-moderator' => 'users',
            'admin.users.remove-moderator' => 'users',
            'admin.alerts' => 'alerts',
            'admin.alerts.create' => 'alerts',
            'admin.alerts.delete' => 'alerts',
            'admin.places' => 'places',
            'admin.places.create' => 'places',
            'admin.places.delete' => 'places',
            'admin.places.feature' => 'places',
            'admin.settings' => 'settings',
            'admin.settings.update' => 'settings',
            'admin.audit-logs' => 'audit-logs',
            'admin.achievements' => 'achievements',
            'admin.achievements.store' => 'achievements',
            'admin.achievements.edit' => 'achievements',
            'admin.achievements.update' => 'achievements',
            'admin.achievements.destroy' => 'achievements',
            'admin.users.progress' => 'users',
            'admin.users.adjust-xp' => 'users',
            'admin.users.recalculate-level' => 'users',
            'admin.users.assign-role' => 'users',
            'admin.user-achievements.flag' => 'users',
            'admin.user-achievements.clear' => 'users',
            'admin.roles' => 'roles',
            'admin.roles.store' => 'roles',
            'admin.roles.edit' => 'roles',
            'admin.roles.update' => 'roles',
            'admin.roles.destroy' => 'roles',
            'admin.permissions' => 'permissions',
            'admin.permissions.store' => 'permissions',
            'admin.permissions.edit' => 'permissions',
            'admin.permissions.update' => 'permissions',
            'admin.permissions.destroy' => 'permissions',
        ];

        return $map[$routeName] ?? 'dashboard';
    }

    private function calculatePercentageChange(int $current, int $previous): int
    {
        if ($previous === 0) {
            return $current === 0 ? 0 : 100;
        }

        return (int) round((($current - $previous) / max(1, $previous)) * 100);
    }

    public function dashboard(Request $request)
    {
        $this->requireAdmin($request);

        $user = Auth::user();
        $isModerator = $user->isModerator();

        $totalUsers = User::count();
        $totalReports = Report::count();
        $pendingReports = Report::where('status', 'pending')->count();
        $approvedReports = Report::where('status', 'approved')->count();
        $rejectedReports = Report::where('status', 'rejected')->count();
        $totalAlerts = Alert::count();
        $totalPlaces = Place::count();

        $pendingQueue = ModerationQueue::pending()->count();

        $periodEnd = Carbon::now()->endOfDay();
        $periodStart = Carbon::now()->subDays(6)->startOfDay();
        $previousPeriodStart = Carbon::now()->subDays(13)->startOfDay();
        $previousPeriodEnd = Carbon::now()->subDays(7)->endOfDay();

        $usersThisWeek = User::where('created_at', '>=', $periodStart)->count();
        $usersLastWeek = User::whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd])->count();

        $reportsThisWeek = Report::where('created_at', '>=', $periodStart)->count();
        $reportsLastWeek = Report::whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd])->count();

        $pendingThisWeek = Report::where('status', 'pending')->where('created_at', '>=', $periodStart)->count();
        $pendingLastWeek = Report::where('status', 'pending')->whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd])->count();

        $alertsThisWeek = Alert::where('created_at', '>=', $periodStart)->count();
        $alertsLastWeek = Alert::whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd])->count();

        $weeklyReportData = Report::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', $periodStart)
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $chartLabels = [];
        $chartValues = [];
        for ($day = 6; $day >= 0; $day--) {
            $date = Carbon::now()->subDays($day);
            $key = $date->toDateString();
            $chartLabels[] = $date->format('M j');
            $chartValues[] = $weeklyReportData[$key]->count ?? 0;
        }

        $opsEfficiency = $totalReports > 0
            ? round((($approvedReports + $rejectedReports) / $totalReports) * 100)
            : 0;

        $usersChangePct = $this->calculatePercentageChange($usersThisWeek, $usersLastWeek);
        $reportsChangePct = $this->calculatePercentageChange($reportsThisWeek, $reportsLastWeek);
        $pendingChangePct = $this->calculatePercentageChange($pendingThisWeek, $pendingLastWeek);
        $alertsChangePct = $this->calculatePercentageChange($alertsThisWeek, $alertsLastWeek);

        $analyticsScore = min(100, max(45, 60 + round($totalReports / max(1, $totalUsers) * 15)));

        $healthScore = max(
            40,
            min(100, round(100 - ($pendingReports / max(1, $totalReports)) * 50 - min($totalAlerts, 10) * 2))
        );
        $healthStatus = $healthScore >= 80 ? 'Excellent' : ($healthScore >= 60 ? 'Stable' : 'Needs attention');

        $stats = [
            'total_users' => $totalUsers,
            'total_reports' => $totalReports,
            'pending_reports' => $pendingReports,
            'approved_reports' => $approvedReports,
            'rejected_reports' => $rejectedReports,
            'total_alerts' => $totalAlerts,
            'total_places' => $totalPlaces,
            'total_xp' => User::sum('total_xp'),
            'total_points' => User::sum('total_xp'),
            'operations_efficiency' => $opsEfficiency,
            'analytics_score' => $analyticsScore,
            'ads_income' => GameSetting::getValue('ads_income_monthly', 2150),
            'system_health_score' => $healthScore,
            'system_health_status' => $healthStatus,
            'users_change_pct' => $usersChangePct,
            'reports_change_pct' => $reportsChangePct,
            'pending_change_pct' => $pendingChangePct,
            'alerts_change_pct' => $alertsChangePct,
            'chart_labels' => $chartLabels,
            'chart_values' => $chartValues,
            'pending_queue' => $pendingQueue,
            'xp_rates' => [
                'report_approval_xp' => GameSetting::getValue('report_approval_xp', 10),
                'alert_post_xp' => GameSetting::getValue('alert_post_xp', 5),
                'review_xp' => GameSetting::getValue('review_xp', 3),
            ],
            'recent_reports' => Report::with('user', 'category')->latest()->take(5)->get(),
            'recent_users' => User::latest()->take(5)->get(),
            'is_moderator' => $isModerator,
            'moderator_permissions' => $isModerator ? $this->moderatorService->getPermissions($user) : [],
            'recent_audit_logs' => AuditLog::with('user')->latest()->take(5)->get(),
        ];

        return view('admin.dashboard', compact('stats'));
    }

    public function auditLogs(Request $request)
    {
        $this->requireAdmin($request);

        $query = AuditLog::with('user');

        // Filter by action
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        // Filter by user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Search in description
        if ($request->filled('search')) {
            $query->where('description', 'like', '%'.$request->search.'%');
        }

        // Date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Suspicious only
        if ($request->boolean('suspicious')) {
            $query->where('metadata->suspicious', true);
        }

        $logs = $query->latest()->paginate(30)->withQueryString();
        $actions = AuditLog::select('action')->distinct()->orderBy('action')->pluck('action');
        $users = User::whereIn('id', AuditLog::select('user_id')->distinct())->orderBy('name')->get();

        return view('admin.audit_logs', compact('logs', 'actions', 'users'));
    }

    public function reports(Request $request)
    {
        $this->requireAdmin($request);
        $query = Report::with('user', 'category');
        $status = $request->input('status', 'pending');
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        $reports = $query->latest()->paginate(15);
        $categories = ReportCategorie::all();
        $queueCounts = [
            'pending' => ModerationQueue::pending()->byType('report')->count(),
            'approved' => ModerationQueue::where('status', 'approved')->byType('report')->count(),
            'rejected' => ModerationQueue::where('status', 'rejected')->byType('report')->count(),
        ];
        return view('admin.reports', compact('reports', 'status', 'categories', 'queueCounts'));
    }

    public function users(Request $request)
    {
        $this->requireAdmin($request);
        $this->requirePermission('manage_users');
        $query = User::with('role');
        $role = $request->input('role', 'all');
        if ($role !== 'all') {
            $roleId = \App\Models\Role::where('name', $role)->value('id');
            if ($roleId) {
                $query->where('role_id', $roleId);
            }
        }
        $status = $request->input('status', 'all');
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        $search = $request->input('search');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        $users = $query->latest()->paginate(15);
        $roles = \App\Models\Role::orderBy('name')->get();
        $moderatorRoleId = \App\Models\Role::where('name', 'moderator')->value('id');
        $moderators = $moderatorRoleId
            ? User::where('role_id', $moderatorRoleId)->with('role.permissions')->get()
            : collect();
        return view('admin.users', compact('users', 'role', 'status', 'roles', 'moderators'));
    }

    public function alerts(Request $request)
    {
        $this->requireAdmin($request);
        $query = Alert::query();
        $severity = $request->input('severity', 'all');
        if ($severity !== 'all') {
            $query->where('severity', $severity);
        }
        $alerts = $query->latest()->paginate(15);
        return view('admin.alerts', compact('alerts', 'severity'));
    }

    public function places(Request $request)
    {
        $this->requireAdmin($request);
        $query = Place::with('images', 'category');

        // Category filter
        $categoryId = $request->input('category_id', 'all');
        if ($categoryId !== 'all') {
            $query->where('category_id', $categoryId);
        }

        // Search
        $search = $request->input('search');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('district', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhereHas('category', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // Sort
        $sort = $request->input('sort', 'created_at');
        $direction = $request->input('direction', 'desc');
        $allowedSorts = ['name', 'district', 'average_rating', 'total_reviews', 'is_featured', 'created_at', 'id'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'created_at';
        }
        $direction = $direction === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $direction);

        $places = $query->paginate(15)->withQueryString();
        $categories = PlaceCategories::all();
        return view('admin.places', compact('places', 'categories', 'categoryId', 'search', 'sort', 'direction'));
    }

    /**
     * Show live OSM places across all Nepal via Overpass API
     */
    public function placesOsm(Request $request)
    {
        $this->requireAdmin($request);
        $search = $request->input('search');
        $cacheKey = 'osm_nepal_all_' . md5($search ?? 'all');

        $osmError = null;
        $osmPlaces = [];

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            $osmPlaces = $cached;
        } else {
            $south = 26.0; $west = 79.5; $north = 31.0; $east = 89.0;

            $amenityPatt = 'restaurant|cafe|fast_food|pub|bar|hotel|motel|hostel|guest_house|hospital|clinic|pharmacy|doctors|bank|atm|fuel|taxi|police|fire_station|embassy|marketplace|theatre|cinema|community_centre|bus_station|ferry_terminal|parking|post_office|library|place_of_worship|school|university|college';
            $tourismPatt = 'attraction|hotel|motel|hostel|guest_house|information|museum|viewpoint|picnic_site|camp_site|caravan_site|wilderness_hut|alpine_hut|artwork|gallery|theme_park|zoo';
            $shopPatt = 'supermarket|convenience|mall|department_store|clothes|electronics|gift|souvenir';
            $naturalPatt = 'peak|volcano|bay|cape|beach';

            $nameFilter = '';
            if ($search) {
                $safe = preg_replace('/[^\p{L}0-9\s\-_\']/u', '', $search);
                $safe = trim($safe);
                if (strlen($safe) >= 2) {
                    $nameFilter = '[~"name"~"' . $safe . '", i]';
                }
            }

            $queries = [
                "node{$nameFilter}[\"amenity\"~\"{$amenityPatt}\"]({$south},{$west},{$north},{$east});",
                "node{$nameFilter}[\"tourism\"~\"{$tourismPatt}\"]({$south},{$west},{$north},{$east});",
                "node{$nameFilter}[\"shop\"~\"{$shopPatt}\"]({$south},{$west},{$north},{$east});",
                "node{$nameFilter}[\"leisure\"]({$south},{$west},{$north},{$east});",
                "node{$nameFilter}[\"historic\"]({$south},{$west},{$north},{$east});",
                "node{$nameFilter}[\"natural\"~\"{$naturalPatt}\"]({$south},{$west},{$north},{$east});",
            ];

            $overpassQuery = "[out:json][timeout:25];(" . implode('', $queries) . ");out body 500;";

            try {
                $opts = [
                    'http' => [
                        'method' => 'POST',
                        'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\nUser-Agent: NepalSmartTravelAdmin/1.0",
                        'content' => 'data=' . urlencode($overpassQuery),
                        'timeout' => 30,
                        'ignore_errors' => true,
                    ],
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                    ],
                ];
                $context = stream_context_create($opts);
                $responseBody = @file_get_contents('https://overpass-api.de/api/interpreter', false, $context);
                if ($responseBody === false) {
                    $osmError = 'Could not connect to Overpass API. Network or SSL issue.';
                    \Log::warning('Admin OSM: file_get_contents failed');
                } else {
                    $httpCode = 200;
                    if (isset($http_response_header[0]) && preg_match('/\d{3}/', $http_response_header[0], $m)) {
                        $httpCode = (int)$m[0];
                    }

                    if ($httpCode === 429) {
                        $osmError = 'Overpass API rate limit exceeded. Try again in a minute.';
                    } elseif ($httpCode === 504) {
                        $osmError = 'Overpass API server is busy (504 timeout). Try again later.';
                    } elseif ($httpCode !== 200) {
                        $osmError = "Overpass API returned HTTP {$httpCode}.";
                    } else {
                        $data = json_decode($responseBody, true);
                        $elements = $data['elements'] ?? [];

                        $places = [];
                        $seen = [];

                        foreach ($elements as $element) {
                            $tags = $element['tags'] ?? [];
                            $elemLat = $element['lat'] ?? null;
                            $elemLng = $element['lon'] ?? null;
                            if (!$elemLat || !$elemLng) continue;

                            $name = $tags['name'] ?? $tags['name:en'] ?? null;
                            if (!$name) continue;

                            $osmId = $element['type'] . '/' . $element['id'];
                            if (isset($seen[$osmId])) continue;
                            $seen[$osmId] = true;

                            $category = $this->osmToCategory($tags);

                            $address = implode(', ', array_filter([
                                $tags['addr:street'] ?? null,
                                $tags['addr:city'] ?? null,
                            ]));

                            $places[] = [
                                'id' => 'osm_' . $osmId,
                                'name' => $name,
                                'description' => $tags['description'] ?? $tags['note'] ?? null,
                                'address' => $address ?: null,
                                'district' => $tags['addr:city'] ?? $tags['addr:district'] ?? null,
                                'latitude' => $elemLat,
                                'longitude' => $elemLng,
                                'phone' => $tags['phone'] ?? $tags['contact:phone'] ?? null,
                                'category' => $category,
                            ];
                        }

                        usort($places, fn($a, $b) => strcmp($a['name'], $b['name']));
                        $osmPlaces = $places;
                        Cache::put($cacheKey, $osmPlaces, 600);
                    }
                }
            } catch (\Exception $e) {
                $osmError = 'Error: ' . $e->getMessage();
                \Log::error('Admin places OSM error: ' . $e->getMessage());
            }
        }

        $categories = PlaceCategories::all();
        return view('admin.places_osm', compact('osmPlaces', 'search', 'categories', 'osmError'));
    }

    public function showPlace(Request $request, $id)
    {
        $this->requireAdmin($request);
        $place = Place::with(['category', 'images', 'reviews.user', 'creator'])->findOrFail($id);

        // Rating distribution
        $ratingDist = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($place->reviews as $review) {
            $r = (int) $review->rating;
            if (isset($ratingDist[$r])) $ratingDist[$r]++;
        }

        return view('admin.place_detail', compact('place', 'ratingDist'));
    }

    // ============ REPORT ACTIONS ============

    public function approveReport(Request $request, $id)
    {
        $this->requireAdmin($request);
        $this->requirePermission('approve_reports');

        $report = Report::findOrFail($id);
        $report->update([
            'status' => 'approved',
            'verified_by' => Auth::id(),
            'verified_at' => now(),
        ]);

        ModerationQueue::where('content_type', 'report')
            ->where('content_id', $report->id)
            ->update(['status' => 'approved', 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);

        $reporter = $report->user;
        if ($reporter) {
            $rewardXp = GameSetting::getValue('report_approval_xp', 10);
            app(AchievementService::class)->awardXp(
                $reporter, $rewardXp, 'report_approved',
                "Report approved: {$report->title}", $report
            );
            $reporter->increment('approved_reports');
        }

        if ($report->latitude && $report->longitude) {
            try {
                PushNotificationService::notifyNearbyUsers(
                    title: '⚠️ ' . $report->title,
                    message: str($report->description)->limit(100),
                    latitude: (float) $report->latitude,
                    longitude: (float) $report->longitude,
                    radiusKm: 20,
                    data: ['type' => 'report', 'id' => $report->id],
                );
            } catch (\Throwable $e) {
            }
        }

        $this->logAction('report.approved', 'report', $report->id, "Approved report #{$report->id}: {$report->title}");

        return back()->with('success', 'Report approved');
    }

    public function rejectReport(Request $request, $id)
    {
        $this->requireAdmin($request);
        $this->requirePermission('approve_reports');

        $report = Report::findOrFail($id);
        $report->update(['status' => 'rejected']);

        ModerationQueue::where('content_type', 'report')
            ->where('content_id', $report->id)
            ->update(['status' => 'rejected', 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);

        $this->logAction('report.rejected', 'report', $report->id, "Rejected report #{$report->id}: {$report->title}");

        return back()->with('success', 'Report rejected');
    }

    public function deleteReport(Request $request, $id)
    {
        $this->requireAdmin($request);
        $this->requirePermission('delete_reports');

        $report = Report::findOrFail($id);
        $report->delete();

        ModerationQueue::where('content_type', 'report')
            ->where('content_id', $id)
            ->delete();

        $this->logAction('report.deleted', 'report', $id, "Deleted report #{$id}");

        return back()->with('success', 'Report deleted');
    }

    public function reportDetails(Request $request, $id)
    {
        $this->requireAdmin($request);
        $report = Report::with(['user', 'category', 'media'])->findOrFail($id);
        $queueItem = ModerationQueue::where('content_type', 'report')
            ->where('content_id', $report->id)
            ->first();
        return view('admin.report_details', compact('report', 'queueItem'));
    }

    // ============ USER ACTIONS ============

    public function toggleUserStatus(Request $request, $id)
    {
        $this->requireAdmin($request);
        $this->requirePermission('manage_users');

        $user = User::findOrFail($id);
        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot change your own status.');
        }
        if ($user->isAdmin()) {
            return back()->with('error', 'Cannot change status of an admin user.');
        }

        $newStatus = match ($user->status) {
            'active' => 'suspended',
            'suspended' => 'banned',
            'banned' => 'active',
            default => 'active',
        };
        $user->update(['status' => $newStatus]);
        $user->tokens()->delete();

        $this->logAction('user.toggle-status', 'user', $user->id, "Changed user #{$user->id} ({$user->name}) status from previous to {$newStatus}");

        return back()->with('success', "User status changed to {$newStatus}.");
    }

    public function makeAdmin(Request $request, $id)
    {
        $this->requireAdmin($request);
        $this->requirePermission('assign_moderator');

        $user = User::findOrFail($id);
        if ($user->isAdmin()) {
            return back()->with('error', 'User is already an admin.');
        }
        if ($user->status !== 'active') {
            return back()->with('error', 'Cannot promote a non-active user to admin.');
        }
        $user->promoteToAdmin();

        $this->logAction('user.make-admin', 'user', $user->id, "Promoted user #{$user->id} ({$user->name}) to admin");

        return back()->with('success', 'User promoted to admin.');
    }

    public function removeAdmin(Request $request, $id)
    {
        $this->requireAdmin($request);
        $this->requirePermission('assign_moderator');

        $user = User::findOrFail($id);
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Cannot remove your own admin status.');
        }
        if (!$user->isAdmin()) {
            return back()->with('error', 'User is not an admin.');
        }
        $user->demoteToUser();

        $this->logAction('user.remove-admin', 'user', $user->id, "Demoted admin #{$user->id} ({$user->name}) to user");

        return back()->with('success', 'Admin privileges removed.');
    }

    public function makeModerator(Request $request, $id)
    {
        $this->requireAdmin($request);
        $this->requirePermission('assign_moderator');

        $user = User::findOrFail($id);
        if ($user->isAdmin()) {
            return back()->with('error', 'Cannot promote an admin user to moderator.');
        }
        if ($user->isModerator()) {
            return back()->with('error', 'User is already a moderator.');
        }
        if ($user->status !== 'active') {
            return back()->with('error', 'Cannot promote a non-active user to moderator.');
        }
        $user->promoteToModerator();

        $modRole = \App\Models\Role::where('name', 'moderator')->first();
        if ($modRole) {
            $defaultPerms = ['approve_reports', 'delete_reports', 'manage_places', 'manage_alerts', 'manage_users', 'view_analytics'];
            $permIds = \App\Models\Permission::whereIn('name', $defaultPerms)->pluck('id');
            $modRole->permissions()->syncWithoutDetaching($permIds);
        }

        $this->logAction('user.make-moderator', 'user', $user->id, "Promoted user #{$user->id} ({$user->name}) to moderator");

        return back()->with('success', 'User promoted to moderator with default permissions.');
    }

    public function removeModerator(Request $request, $id)
    {
        $this->requireAdmin($request);
        $this->requirePermission('assign_moderator');

        $user = User::findOrFail($id);
        if ($user->id === Auth::id()) {
            return back()->with('error', 'You cannot remove your own moderator status.');
        }
        if (!$user->isModerator()) {
            return back()->with('error', 'User is not a moderator.');
        }
        $user->demoteToUser();

        $this->logAction('user.remove-moderator', 'user', $user->id, "Demoted moderator #{$user->id} ({$user->name}) to user");

        return back()->with('success', 'Moderator privileges removed.');
    }

    public function assignUserRole(Request $request, $id)
    {
        $this->requireAdmin($request);
        $this->requirePermission('assign_moderator');

        $user = User::findOrFail($id);
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Cannot change your own role.');
        }
        if ($user->isAdmin()) {
            return back()->with('error', 'Cannot change the role of an admin user.');
        }

        $roleId = $request->input('role_id');
        $role = \App\Models\Role::find($roleId);
        if (!$role) {
            return back()->with('error', 'Invalid role.');
        }

        $user->role_id = $role->id;
        $user->save();

        $this->logAction('user.assign-role', 'user', $user->id, "Assigned role '{$role->display_name}' to user #{$user->id} ({$user->name})");

        return back()->with('success', "User role changed to '{$role->display_name}'.");
    }

    // ============ ALERT ACTIONS ============

    public function deleteAlert(Request $request, $id)
    {
        $this->requireAdmin($request);
        $this->requirePermission('manage_alerts');

        $alert = Alert::findOrFail($id);
        $alert->delete();

        $this->logAction('alert.deleted', 'alert', $id, "Deleted alert #{$id}: {$alert->title}");

        return back()->with('success', 'Alert deleted');
    }

    // ============ PLACE ACTIONS ============

    public function deletePlace(Request $request, $id)
    {
        $this->requireAdmin($request);
        $this->requirePermission('manage_places');

        $place = Place::findOrFail($id);
        $place->delete();

        $this->logAction('place.deleted', 'place', $id, "Deleted place #{$id}: {$place->name}");

        return back()->with('success', 'Place deleted');
    }

    public function featurePlace(Request $request, $id)
    {
        $this->requireAdmin($request);
        $this->requirePermission('manage_places');

        $place = Place::findOrFail($id);
        $place->update(['is_featured' => !$place->is_featured]);
        $status = $place->is_featured ? 'featured' : 'unfeatured';

        $this->logAction('place.feature', 'place', $place->id, "{$status} place #{$place->id}: {$place->name}");

        return back()->with('success', "Place {$status}");
    }

    public function createAlert(Request $request)
    {
        $this->requireAdmin($request);
        $this->requirePermission('manage_alerts');

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'alert_type' => 'required|string',
            'severity' => 'required|in:info,low,medium,high,critical',
            'affected_district' => 'required|string',
        ]);
        $validated['uuid'] = (string) Str::uuid();
        $validated['created_by'] = Auth::id();
        $alert = Alert::create($validated);

        $this->logAction('alert.created', 'alert', $alert->id, "Created alert: {$validated['title']}");

        return back()->with('success', 'Alert created');
    }

    public function settings(Request $request)
    {
        $this->requireAdmin($request);
        if (!Auth::user()->isAdmin()) {
            abort(403, 'Only administrators can access settings.');
        }

        $settings = [
            'report_approval_xp' => GameSetting::getValue('report_approval_xp', 10),
            'alert_post_xp' => GameSetting::getValue('alert_post_xp', 5),
            'review_xp' => GameSetting::getValue('review_xp', 3),
        ];

        return view('admin.settings', compact('settings'));
    }

    public function updateSettings(Request $request)
    {
        $this->requireAdmin($request);
        if (!Auth::user()->isAdmin()) {
            abort(403, 'Only administrators can update settings.');
        }

        $validated = $request->validate([
            'report_approval_xp' => 'required|integer|min:0|max:1000',
            'alert_post_xp' => 'required|integer|min:0|max:1000',
            'review_xp' => 'required|integer|min:0|max:1000',
        ]);

        GameSetting::setValue('report_approval_xp', $validated['report_approval_xp']);
        GameSetting::setValue('alert_post_xp', $validated['alert_post_xp']);
        GameSetting::setValue('review_xp', $validated['review_xp']);

        $this->logAction('settings.updated', 'settings', null, 'Updated XP rates');

        return back()->with('success', 'XP rate settings updated successfully');
    }

    public function createPlace(Request $request)
    {
        $this->requireAdmin($request);
        $this->requirePermission('manage_places');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:place_categories,id',
            'address' => 'nullable|string',
            'district' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
        ]);
        $validated['uuid'] = (string) Str::uuid();
        $validated['created_by'] = Auth::id();
        $place = Place::create($validated);

        // Handle image uploads
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('places/' . $place->id, 'public');
                PlaceImage::create([
                    'place_id' => $place->id,
                    'image_url' => $path,
                ]);
            }
        }

        $this->logAction('place.created', 'place', $place->id, "Created place: {$validated['name']}");

        return back()->with('success', 'Place created');
    }

    public function updatePlace(Request $request, $id)
    {
        $this->requireAdmin($request);
        $this->requirePermission('manage_places');

        $place = Place::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'required|exists:place_categories,id',
            'address' => 'nullable|string',
            'district' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'phone' => 'nullable|string',
            'email' => 'nullable|email',
            'website' => 'nullable|url',
            'is_verified' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);

        $place->update($validated);

        // Handle image uploads
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('places/' . $place->id, 'public');
                PlaceImage::create([
                    'place_id' => $place->id,
                    'image_url' => $path,
                ]);
            }
        }

        $this->logAction('place.updated', 'place', $place->id, "Updated place #{$place->id}: {$validated['name']}");

        return back()->with('success', 'Place updated');
    }

    public function deletePlaceImage(Request $request, $id)
    {
        $this->requireAdmin($request);
        $this->requirePermission('manage_places');

        $image = PlaceImage::findOrFail($id);
        $image->delete();

        return back()->with('success', 'Image deleted');
    }

    public function importOsmPlaces(Request $request)
    {
        set_time_limit(300);

        $this->requireAdmin($request);
        $this->requirePermission('manage_places');

        $city = $request->input('city');
        $radius = $request->input('radius', 10);

        try {
            $exitCode = \Illuminate\Support\Facades\Artisan::call('places:import-osm', [
                '--radius' => $radius,
                '--city' => $city,
                '--limit' => 200,
            ]);
            $output = \Illuminate\Support\Facades\Artisan::output();
            return back()->with('success', "OSM import complete. Output: " . nl2br(e($output)));
        } catch (\Exception $e) {
            return back()->with('error', 'OSM import failed: ' . $e->getMessage());
        }
    }

    public function manageCategories(Request $request)
    {
        $this->requireAdmin($request);
        $this->requirePermission('manage_places');

        if ($request->isMethod('post')) {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:place_categories,name',
                'icon' => 'nullable|string|max:100',
            ]);
            PlaceCategories::create($validated);
            return back()->with('success', 'Category created');
        }

        if ($request->isMethod('put')) {
            $cat = PlaceCategories::findOrFail($request->id);
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:place_categories,name,' . $cat->id,
                'icon' => 'nullable|string|max:100',
            ]);
            $cat->update($validated);
            return back()->with('success', 'Category updated');
        }

        if ($request->isMethod('delete')) {
            $cat = PlaceCategories::findOrFail($request->id);
            if ($cat->places()->count() > 0) {
                return back()->with('error', 'Cannot delete category with existing places');
            }
            $cat->delete();
            return back()->with('success', 'Category deleted');
        }

        return back();
    }

    public function bulkDeletePlaces(Request $request)
    {
        $this->requireAdmin($request);
        $this->requirePermission('manage_places');

        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return back()->with('error', 'No places selected');
        }

        $deleted = Place::whereIn('id', $ids)->delete();
        $this->logAction('place.bulk-delete', 'place', null, "Bulk deleted {$deleted} places");

        return back()->with('success', "{$deleted} places deleted");
    }

    public function bulkUpdatePlaces(Request $request)
    {
        $this->requireAdmin($request);
        $this->requirePermission('manage_places');

        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return back()->with('error', 'No places selected');
        }

        $updates = [];
        if ($request->filled('category_id')) {
            $updates['category_id'] = $request->category_id;
        }
        if ($request->has('is_verified')) {
            $updates['is_verified'] = $request->boolean('is_verified');
        }
        if ($request->has('is_active')) {
            $updates['is_active'] = $request->boolean('is_active');
        }
        if ($request->has('is_featured')) {
            $updates['is_featured'] = $request->boolean('is_featured');
        }

        if (empty($updates)) {
            return back()->with('error', 'No updates specified');
        }

        $updated = Place::whereIn('id', $ids)->update($updates);
        $this->logAction('place.bulk-update', 'place', null, "Bulk updated {$updated} places");

        return back()->with('success', "{$updated} places updated");
    }

    public function liveMap()
    {
        $places = Place::with('category', 'images')
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(function ($p) {
                $firstImg = $p->images->first();
                return [
                    'id' => $p->id,
                    'type' => 'place',
                    'name' => $p->name,
                    'description' => $p->description,
                    'latitude' => (float) $p->latitude,
                    'longitude' => (float) $p->longitude,
                    'category' => $p->category?->name ?? 'Uncategorized',
                    'icon' => $p->category?->icon ?? 'map-marker-alt',
                    'color' => $p->category?->color ?? '#6366f1',
                    'status' => $p->is_verified ? 'verified' : 'unverified',
                    'image' => $firstImg ? asset('storage/' . $firstImg->image_url) : null,
                    'rating' => (float) ($p->average_rating ?? 0),
                    'reviews_count' => $p->total_reviews ?? 0,
                    'url' => route('admin.places.view', $p->id),
                ];
            });

        $reports = Report::whereIn('status', ['approved', 'pending'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'type' => 'report',
                    'name' => $r->title,
                    'description' => Str::limit($r->description, 100),
                    'latitude' => (float) $r->latitude,
                    'longitude' => (float) $r->longitude,
                    'category' => $r->category?->name ?? 'Report',
                    'color' => $r->status === 'approved' ? '#f97316' : '#ef4444',
                    'status' => $r->status,
                    'url' => route('admin.reports.view', $r->id),
                ];
            });

        $alerts = Alert::where('expires_at', '>=', now())
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->map(function ($a) {
                return [
                    'id' => $a->id,
                    'type' => 'alert',
                    'name' => $a->title,
                    'description' => Str::limit($a->description, 100),
                    'latitude' => (float) $a->latitude,
                    'longitude' => (float) $a->longitude,
                    'category' => $a->severity ?? 'info',
                    'color' => match ($a->severity) {
                        'critical' => '#dc2626',
                        'warning' => '#f59e0b',
                        'info' => '#3b82f6',
                        default => '#6b7280',
                    },
                    'status' => $a->severity ?? 'info',
                    'url' => '#',
                ];
            });

        return view('admin.live_map', [
            'resources' => json_encode([
                'places' => $places,
                'reports' => $reports,
                'alerts' => $alerts,
            ]),
            'counts' => [
                'places' => $places->count(),
                'reports' => $reports->count(),
                'alerts' => $alerts->count(),
            ],
        ]);
    }

}
