<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\GameSetting;
use App\Models\Report;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Builds the admin dashboard payload.
 *
 * Everything the dashboard renders (KPIs, charts, queues, recent lists) comes
 * from one array so the initial page render and the live JSON refresh stay
 * perfectly in sync.
 */
class AdminDashboardService
{
    public const RANGES = ['today', '7d', '30d', '90d', '12m', 'custom'];

    public function build(Request $request, User $user): array
    {
        $range = $this->resolveRange($request);
        $canFinance = $user->isAdmin() || $user->hasPermission('view_financial_overview');

        // Keep the cache per-user: moderator permissions are per-user and
        // determine which queues/links are visible.
        $cacheKey = 'admin_dashboard:v1:'.md5(implode('|', [
            'u'.$user->id,
            $range['key'],
            $range['start']->format('Y-m-d H:i'),
            $range['end']->format('Y-m-d H:i'),
            $canFinance ? 'finance' : 'ops',
        ]));

        try {
            return Cache::remember($cacheKey, 20, fn () => $this->buildFresh($range, $user, $canFinance));
        } catch (Throwable $e) {
            return $this->buildFresh($range, $user, $canFinance);
        }
    }

    private function buildFresh(array $range, User $user, bool $canFinance): array
    {
        $start = $range['start'];
        $end = $range['end'];
        $prevStart = $range['previous_start'];
        $prevEnd = $range['previous_end'];
        $bucket = $range['bucket'];
        $keys = $range['bucket_keys'];
        $labels = $range['bucket_labels'];

        // -----------------------------------------------------------------
        // Users
        // -----------------------------------------------------------------
        $newUsers = DB::table('users')->where('is_system', 0)->whereBetween('created_at', [$start, $end])->count();
        $previousUsers = DB::table('users')->where('is_system', 0)->whereBetween('created_at', [$prevStart, $prevEnd])->count();
        $totalUsers = DB::table('users')->where('is_system', 0)->count();
        $activeUsers = DB::table('users')->where('is_system', 0)->where('status', 'active')->count();
        $suspendedUsers = DB::table('users')->where('is_system', 0)->whereIn('status', ['suspended', 'banned'])->count();
        $usersSeries = $this->fillSeries($keys, $this->countSeries(
            'users', $start, $end, $bucket, fn ($q) => $q->where('is_system', 0)
        ));

        // -----------------------------------------------------------------
        // Reports + moderation
        // -----------------------------------------------------------------
        $newReports = DB::table('reports')->whereBetween('created_at', [$start, $end])->count();
        $previousReports = DB::table('reports')->whereBetween('created_at', [$prevStart, $prevEnd])->count();
        $totalReports = DB::table('reports')->count();
        $pendingReports = DB::table('reports')->where('status', 'pending')->count();
        $approvedReports = DB::table('reports')->whereBetween('created_at', [$start, $end])->where('status', 'approved')->count();
        $rejectedReports = DB::table('reports')->whereBetween('created_at', [$start, $end])->where('status', 'rejected')->count();
        $pendingInRange = DB::table('reports')->whereBetween('created_at', [$start, $end])->where('status', 'pending')->count();
        $highPriorityReports = DB::table('reports')->whereBetween('created_at', [$start, $end])->whereIn('priority', ['high', 'critical'])->count();
        $oldestPendingAt = DB::table('reports')->where('status', 'pending')->min('created_at');

        $reportsSeries = $this->fillSeries($keys, $this->countSeries('reports', $start, $end, $bucket));
        $approvedSeries = $this->fillSeries($keys, $this->countSeries(
            'reports', $start, $end, $bucket, fn ($q) => $q->where('status', 'approved')
        ));
        $rejectedSeries = $this->fillSeries($keys, $this->countSeries(
            'reports', $start, $end, $bucket, fn ($q) => $q->where('status', 'rejected')
        ));

        $pendingQueue = DB::table('moderation_queues')->where('status', 'pending')->count();
        $pendingCorrections = DB::table('place_corrections')->where('status', 'pending')->count();
        $contentViolationsToday = DB::table('content_violations')->whereDate('created_at', today())->count();

        // -----------------------------------------------------------------
        // Alerts / SOS
        // -----------------------------------------------------------------
        $activeAlerts = DB::table('alerts')->where(function ($q) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        })->count();
        $newAlerts = DB::table('alerts')->whereBetween('created_at', [$start, $end])->count();
        $previousAlerts = DB::table('alerts')->whereBetween('created_at', [$prevStart, $prevEnd])->count();
        $criticalAlerts = DB::table('alerts')->whereBetween('created_at', [$start, $end])->whereIn('severity', ['high', 'critical'])->count();
        $alertsSeries = $this->fillSeries($keys, $this->countSeries('alerts', $start, $end, $bucket));
        $activeSos = DB::table('sos_alerts')->where('status', 'active')->count();
        $sosToday = DB::table('sos_alerts')->whereDate('created_at', today())->count();

        // -----------------------------------------------------------------
        // Places / reviews
        // -----------------------------------------------------------------
        $totalPlaces = DB::table('places')->count();
        $activePlaces = DB::table('places')->where('is_active', 1)->count();
        $newPlaces = DB::table('places')->whereBetween('created_at', [$start, $end])->count();
        $previousPlaces = DB::table('places')->whereBetween('created_at', [$prevStart, $prevEnd])->count();
        $placesSeries = $this->fillSeries($keys, $this->countSeries('places', $start, $end, $bucket));
        $sourceCounts = DB::table('places')->selectRaw('source, COUNT(*) as total')->groupBy('source')->pluck('total', 'source')->all();
        $osmPlaces = (int) ($sourceCounts['osm'] ?? 0);
        $featuredPlaces = DB::table('places')->where('is_featured', 1)->count();
        $newReviews = DB::table('place_reviews')->whereBetween('created_at', [$start, $end])->count();

        // -----------------------------------------------------------------
        // Partners
        // -----------------------------------------------------------------
        $totalPartners = DB::table('travel_partners')->count();
        $pendingPartners = DB::table('travel_partners')->where('verification_status', 'pending')->count();
        $verifiedPartners = DB::table('travel_partners')->where('verification_status', 'verified')->count();
        $rejectedPartners = DB::table('travel_partners')->where('verification_status', 'rejected')->count();
        $newPartners = DB::table('travel_partners')->whereBetween('created_at', [$start, $end])->count();

        // -----------------------------------------------------------------
        // Revenue (finance permission only) + bookings + subscriptions
        // -----------------------------------------------------------------
        $adRevenue = $previousAdRevenue = 0.0;
        $bookingRevenue = $previousBookingRevenue = 0.0;
        $offerRevenue = $previousOfferRevenue = 0.0;
        $subscriptionRevenue = $previousSubscriptionRevenue = 0.0;

        if ($canFinance) {
            $adRevenue = (float) DB::table('ad_revenue_ledger')->whereBetween('created_at', [$start, $end])->sum('admin_share');
            $previousAdRevenue = (float) DB::table('ad_revenue_ledger')->whereBetween('created_at', [$prevStart, $prevEnd])->sum('admin_share');

            $bookingRevenue = (float) DB::table('bookings')->where('status', 'completed')->whereBetween('created_at', [$start, $end])->sum('commission_earned');
            $previousBookingRevenue = (float) DB::table('bookings')->where('status', 'completed')->whereBetween('created_at', [$prevStart, $prevEnd])->sum('commission_earned');

            $offerRevenue = (float) DB::table('offer_redemptions')->where('status', 'used')->whereBetween('used_at', [$start, $end])->sum('admin_commission');
            $previousOfferRevenue = (float) DB::table('offer_redemptions')->where('status', 'used')->whereBetween('used_at', [$prevStart, $prevEnd])->sum('admin_commission');

            $subscriptionRevenue = (float) DB::table('subscription_payments')->whereIn('status', ['success', 'completed'])->whereBetween('created_at', [$start, $end])->sum('amount');
            $previousSubscriptionRevenue = (float) DB::table('subscription_payments')->whereIn('status', ['success', 'completed'])->whereBetween('created_at', [$prevStart, $prevEnd])->sum('amount');
        }

        $totalRevenue = $adRevenue + $bookingRevenue + $offerRevenue + $subscriptionRevenue;
        $previousRevenue = $previousAdRevenue + $previousBookingRevenue + $previousOfferRevenue + $previousSubscriptionRevenue;

        if ($canFinance) {
            $revenueSeries = [
                'ads' => $this->fillSeries($keys, $this->sumSeries('ad_revenue_ledger', 'created_at', 'admin_share', $start, $end, $bucket)),
                'bookings' => $this->fillSeries($keys, $this->sumSeries(
                    'bookings', 'created_at', 'commission_earned', $start, $end, $bucket,
                    fn ($q) => $q->where('status', 'completed')
                )),
                'offers' => $this->fillSeries($keys, $this->sumSeries(
                    'offer_redemptions', 'used_at', 'admin_commission', $start, $end, $bucket,
                    fn ($q) => $q->where('status', 'used')
                )),
                'subscriptions' => $this->fillSeries($keys, $this->sumSeries(
                    'subscription_payments', 'created_at', 'amount', $start, $end, $bucket,
                    fn ($q) => $q->whereIn('status', ['success', 'completed'])
                )),
            ];
        } else {
            $emptySeries = array_fill(0, count($keys), 0);
            $revenueSeries = [
                'ads' => $emptySeries,
                'bookings' => $emptySeries,
                'offers' => $emptySeries,
                'subscriptions' => $emptySeries,
            ];
        }

        $newBookings = DB::table('bookings')->whereBetween('created_at', [$start, $end])->count();
        $completedBookings = DB::table('bookings')->whereBetween('created_at', [$start, $end])->where('status', 'completed')->count();
        $activeSubscriptions = DB::table('user_subscriptions')->where('status', 'active')->count();
        $newSubscriptions = DB::table('user_subscriptions')->whereBetween('created_at', [$start, $end])->count();

        // -----------------------------------------------------------------
        // Engagement
        // -----------------------------------------------------------------
        $xpAwarded = (int) DB::table('xp_transactions')->whereBetween('created_at', [$start, $end])->where('amount', '>', 0)->sum('amount');
        $totalXp = (int) DB::table('users')->where('is_system', 0)->sum('total_xp');
        $pendingWithdrawals = DB::table('withdrawals')->where('status', 'pending')->count();
        $pendingPayouts = DB::table('payouts')->where('status', 'pending')->count();
        $awaitingSupport = DB::table('support_conversations')->whereIn('status', ['open', 'awaiting_human'])->count();

        // -----------------------------------------------------------------
        // Automation (scheduler + queue workers)
        // -----------------------------------------------------------------
        $automation = $this->automationPayload($user);

        // -----------------------------------------------------------------
        // System health
        // -----------------------------------------------------------------
        $queueBacklog = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();
        $cacheOk = true;
        try {
            Cache::put('admin_dashboard:health', 'ok', 30);
            $cacheOk = Cache::get('admin_dashboard:health') === 'ok';
        } catch (Throwable $e) {
            $cacheOk = false;
        }

        $lastReportAt = DB::table('reports')->max('created_at');
        $lastUserAt = DB::table('users')->where('is_system', 0)->max('created_at');
        $lastAuditAt = DB::table('audit_logs')->max('created_at');
        $lastActivityAt = collect([$lastReportAt, $lastUserAt, $lastAuditAt])->filter()->max();

        $healthScore = 100;
        if ($totalReports > 0) {
            $healthScore -= (int) round(min(30, ($pendingReports / $totalReports) * 100));
        }
        $healthScore -= min(20, $failedJobs * 10);
        $healthScore -= $queueBacklog > 100 ? 10 : ($queueBacklog > 20 ? 5 : 0);
        $healthScore -= $activeSos > 0 ? 10 : 0;
        $healthScore -= min(15, (int) round(($pendingPartners + $pendingCorrections) / 20));
        if (!$cacheOk) {
            $healthScore -= 10;
        }
        if (($automation['scheduler_status'] ?? 'offline') !== 'online') {
            $healthScore -= 8;
        }
        if (in_array($automation['worker_status'] ?? 'never', ['offline', 'never'], true) && $queueBacklog > 0) {
            $healthScore -= 7;
        }
        $healthScore = max(20, min(100, $healthScore));
        $healthStatus = $healthScore >= 90 ? 'Excellent' : ($healthScore >= 75 ? 'Healthy' : ($healthScore >= 55 ? 'Needs attention' : 'Critical'));
        $healthColor = $healthScore >= 90 ? '#059669' : ($healthScore >= 75 ? '#0d9488' : ($healthScore >= 55 ? '#d97706' : '#e11d48'));

        // -----------------------------------------------------------------
        // Top lists
        // -----------------------------------------------------------------
        $topCategories = DB::table('reports')
            ->join('report_categories', 'reports.category_id', '=', 'report_categories.id')
            ->whereBetween('reports.created_at', [$start, $end])
            ->selectRaw('report_categories.name as label, COUNT(*) as total')
            ->groupBy('report_categories.name')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn ($row) => ['label' => trim((string) $row->label) ?: 'Uncategorized', 'value' => (int) $row->total])
            ->all();

        $topDistricts = DB::table('reports')
            ->whereBetween('created_at', [$start, $end])
            ->whereNotNull('district')
            ->where('district', '<>', '')
            ->selectRaw('district as label, COUNT(*) as total')
            ->groupBy('district')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'value' => (int) $row->total])
            ->all();

        $topContributors = DB::table('reports')
            ->join('users', 'reports.user_id', '=', 'users.id')
            ->where('reports.status', 'approved')
            ->whereBetween('reports.created_at', [$start, $end])
            ->selectRaw('users.id, users.name, COUNT(*) as approved, MAX(users.total_xp) as xp')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('approved')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'reports' => (int) $row->approved,
                'xp' => (int) $row->xp,
            ])
            ->all();

        // -----------------------------------------------------------------
        // Recent activity lists
        // -----------------------------------------------------------------
        $recentReports = Report::with(['user:id,name', 'category:id,name'])
            ->latest()
            ->take(6)
            ->get()
            ->map(fn (Report $report) => [
                'id' => $report->id,
                'title' => $report->title,
                'reporter' => $report->user?->name ?: 'Anonymous',
                'category' => $report->category?->name ?: 'Uncategorized',
                'status' => $report->status,
                'priority' => $report->priority,
                'time' => optional($report->created_at)->diffForHumans() ?: '',
                'url' => route('admin.reports.view', $report->id),
            ])
            ->all();

        $recentUsers = User::with('role:id,name')
            ->where('is_system', 0)
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (User $member) => [
                'name' => $member->name,
                'email' => $member->email,
                'initial' => strtoupper(substr((string) $member->name, 0, 1)),
                'role' => $member->role?->name ?: 'user',
                'xp' => (int) $member->total_xp,
                'time' => optional($member->created_at)->diffForHumans() ?: '',
                'url' => route('admin.users'),
            ])
            ->all();

        $recentPartners = DB::table('travel_partners')
            ->where('verification_status', 'pending')
            ->latest('created_at')
            ->limit(4)
            ->get(['name', 'type', 'type_label', 'created_at'])
            ->map(fn ($partner) => [
                'name' => $partner->name,
                'type' => $partner->type_label ?: $partner->type ?: 'Business',
                'time' => $partner->created_at ? Carbon::parse($partner->created_at)->diffForHumans() : '',
                'url' => route('admin.travel-partners'),
            ])
            ->all();

        $canAudit = $user->isAdmin() || $user->hasPermission('view_audit_logs');
        $recentActivity = [];
        if ($canAudit) {
            $recentActivity = AuditLog::with('user:id,name')
                ->latest()
                ->take(8)
                ->get()
                ->map(fn (AuditLog $log) => [
                    'user' => $log->user?->name ?: 'System',
                    'initial' => strtoupper(substr((string) ($log->user?->name ?: 'S'), 0, 1)),
                    'action' => $log->action,
                    'description' => $log->description ?: $log->action,
                    'time' => optional($log->created_at)->diffForHumans() ?: '',
                    'color' => $this->auditColor($log->action),
                ])
                ->all();
        }

        // -----------------------------------------------------------------
        // Queues + attention items
        // -----------------------------------------------------------------
        $queues = [];
        $addQueue = function (string $key, string $label, int $count, ?string $url, string $color, string $icon) use (&$queues) {
            $queues[] = [
                'key' => $key,
                'label' => $label,
                'count' => $count,
                'route' => $url,
                'color' => $color,
                'icon' => $icon,
            ];
        };

        if ($user->hasPermission('approve_reports')) {
            $addQueue('reports', 'Reports awaiting review', $pendingReports, route('admin.reports', ['status' => 'pending']), 'amber', 'flag');
        }
        if ($user->isAdmin() || $user->hasPermission('manage_content_safety')) {
            $addQueue('moderation', 'Moderation queue', $pendingQueue, route('admin.moderation'), 'rose', 'shield-alt');
        }
        if ($user->hasPermission('manage_places')) {
            $addQueue('corrections', 'Place corrections', $pendingCorrections, route('admin.places.corrections'), 'sky', 'map-marker-alt');
        }
        if ($user->hasPermission('verify_businesses')) {
            $addQueue('partners', 'Partner verifications', $pendingPartners, route('admin.travel-partners'), 'violet', 'handshake');
        }
        if ($user->hasPermission('manage_withdrawals')) {
            $addQueue('withdrawals', 'Withdrawals', $pendingWithdrawals, route('admin.withdrawals', ['status' => 'pending']), 'emerald', 'wallet');
        }
        if ($user->hasPermission('manage_payouts')) {
            $addQueue('payouts', 'Payouts', $pendingPayouts, route('admin.payouts', ['status' => 'pending']), 'indigo', 'money-bill-wave');
        }
        if ($user->hasPermission('manage_support')) {
            $addQueue('support', 'Support conversations', $awaitingSupport, route('admin.support'), 'cyan', 'headset');
        }
        $addQueue('sos', 'Active SOS alerts', $activeSos, route('admin.sos'), 'red', 'exclamation-triangle');

        $maxQueue = max(1, ...array_map(fn ($queue) => $queue['count'], $queues ?: [['count' => 0]]));
        foreach ($queues as &$queue) {
            $queue['percent'] = $queue['count'] > 0 ? max(4, (int) round(($queue['count'] / $maxQueue) * 100)) : 0;
        }
        unset($queue);

        $attentionItems = collect($queues)
            ->where('count', '>', 0)
            ->sortByDesc('count')
            ->take(8)
            ->map(fn ($queue) => [
                'label' => $queue['label'],
                'count' => $queue['count'],
                'route' => $queue['route'],
                'color' => $queue['color'],
                'icon' => $queue['icon'],
            ])
            ->values()
            ->all();

        // -----------------------------------------------------------------
        // KPI cards
        // -----------------------------------------------------------------
        $money = fn (float $value) => 'Rs. '.number_format($value, $value > 0 && $value < 100 ? 2 : 0);
        $delta = fn (float|int $current, float|int $previous) => $this->percentageChange($current, $previous);

        $kpis = [
            [
                'key' => 'users',
                'label' => 'New travelers',
                'value' => $newUsers,
                'display' => number_format($newUsers),
                'delta' => $delta($newUsers, $previousUsers),
                'delta_invert' => false,
                'sub' => number_format($totalUsers).' total travelers',
                'series' => $usersSeries,
                'spark_points' => $this->sparkPoints($usersSeries),
                'color' => 'primary',
                'icon' => 'user-plus',
                'route' => route('admin.users'),
            ],
            [
                'key' => 'reports',
                'label' => 'Reports submitted',
                'value' => $newReports,
                'display' => number_format($newReports),
                'delta' => $delta($newReports, $previousReports),
                'delta_invert' => false,
                'sub' => number_format($pendingReports).' awaiting review',
                'series' => $reportsSeries,
                'spark_points' => $this->sparkPoints($reportsSeries),
                'color' => 'sky',
                'icon' => 'flag',
                'route' => route('admin.reports'),
            ],
            [
                'key' => 'review_queue',
                'label' => 'Review queue',
                'value' => $pendingReports,
                'display' => number_format($pendingReports),
                'delta' => null,
                'delta_invert' => false,
                'sub' => number_format($pendingQueue).' in moderation queue',
                'series' => [],
                'spark_points' => '',
                'color' => 'amber',
                'icon' => 'hourglass-half',
                'route' => route('admin.reports', ['status' => 'pending']),
            ],
        ];

        if ($canFinance) {
            $kpis[] = [
                'key' => 'revenue',
                'label' => 'Platform revenue',
                'value' => round($totalRevenue, 2),
                'display' => $money($totalRevenue),
                'delta' => $delta($totalRevenue, $previousRevenue),
                'delta_invert' => false,
                'sub' => 'Ads '.$money($adRevenue).' | Offers '.$money($offerRevenue),
                'series' => $this->combineSeries($revenueSeries),
                'spark_points' => $this->sparkPoints($this->combineSeries($revenueSeries)),
                'color' => 'emerald',
                'icon' => 'coins',
                'route' => route('admin.financial-overview'),
            ];
        }

        $kpis[] = [
            'key' => 'alerts',
            'label' => 'Active alerts',
            'value' => $activeAlerts,
            'display' => number_format($activeAlerts),
            'delta' => $delta($newAlerts, $previousAlerts),
            'delta_invert' => true,
            'sub' => number_format($newAlerts).' new in period | '.number_format($criticalAlerts).' high/critical',
            'series' => $alertsSeries,
            'spark_points' => $this->sparkPoints($alertsSeries),
            'color' => 'rose',
            'icon' => 'bell',
            'route' => route('admin.alerts'),
        ];

        $kpis[] = [
            'key' => 'partners',
            'label' => 'Pending partners',
            'value' => $pendingPartners,
            'display' => number_format($pendingPartners),
            'delta' => null,
            'delta_invert' => false,
            'sub' => number_format($verifiedPartners).' verified of '.number_format($totalPartners),
            'series' => [],
            'spark_points' => '',
            'color' => 'violet',
            'icon' => 'handshake',
            'route' => route('admin.travel-partners'),
        ];

        $kpis[] = [
            'key' => 'places',
            'label' => 'Places added',
            'value' => $newPlaces,
            'display' => number_format($newPlaces),
            'delta' => $delta($newPlaces, $previousPlaces),
            'delta_invert' => false,
            'sub' => number_format($totalPlaces).' catalogued | '.number_format($osmPlaces).' OSM',
            'series' => $placesSeries,
            'spark_points' => $this->sparkPoints($placesSeries),
            'color' => 'indigo',
            'icon' => 'map-marked-alt',
            'route' => route('admin.places'),
        ];

        $kpis[] = [
            'key' => 'sos',
            'label' => 'Active SOS',
            'value' => $activeSos,
            'display' => number_format($activeSos),
            'delta' => null,
            'delta_invert' => false,
            'sub' => $activeSos > 0 ? 'Emergency response required' : 'No active emergencies',
            'series' => [],
            'spark_points' => '',
            'color' => 'red',
            'icon' => 'exclamation-triangle',
            'route' => route('admin.sos'),
        ];

        // -----------------------------------------------------------------
        // Chart data
        // -----------------------------------------------------------------
        $charts = [
            'activity' => [
                'labels' => $labels,
                'users' => $usersSeries,
                'reports' => $reportsSeries,
                'alerts' => $alertsSeries,
            ],
            'revenue' => [
                'labels' => $labels,
                'ads' => $revenueSeries['ads'],
                'bookings' => $revenueSeries['bookings'],
                'offers' => $revenueSeries['offers'],
                'subscriptions' => $revenueSeries['subscriptions'],
            ],
            'report_status' => [
                'labels' => ['Pending', 'Approved', 'Rejected'],
                'values' => [$pendingInRange, $approvedReports, $rejectedReports],
                'colors' => ['#d97706', '#059669', '#e11d48'],
            ],
        ];

        $breakdowns = [
            'categories' => $this->percentages($topCategories),
            'districts' => $this->percentages($topDistricts),
        ];

        return [
            'generated_at' => now()->toIso8601String(),
            'range' => $this->rangeMeta($range, $labels),
            'permissions' => [
                'finance' => $canFinance,
                'audit' => $canAudit,
                'partners' => $user->hasPermission('verify_businesses'),
            ],
            'kpis' => $kpis,
            'charts' => $charts,
            'revenue' => [
                'total' => round($totalRevenue, 2),
                'display' => $money($totalRevenue),
                'previous' => round($previousRevenue, 2),
                'delta' => $delta($totalRevenue, $previousRevenue),
                'delta_invert' => false,
                'breakdown' => [
                    ['key' => 'ads', 'label' => 'Ad revenue', 'value' => round($adRevenue, 2), 'display' => $money($adRevenue), 'color' => 'emerald'],
                    ['key' => 'bookings', 'label' => 'Booking commission', 'value' => round($bookingRevenue, 2), 'display' => $money($bookingRevenue), 'color' => 'sky'],
                    ['key' => 'offers', 'label' => 'Offer commission', 'value' => round($offerRevenue, 2), 'display' => $money($offerRevenue), 'color' => 'violet'],
                    ['key' => 'subscriptions', 'label' => 'Subscriptions', 'value' => round($subscriptionRevenue, 2), 'display' => $money($subscriptionRevenue), 'color' => 'amber'],
                ],
            ],
            'queues' => $queues,
            'automation' => $automation,
            'attention_items' => $attentionItems,
            'breakdowns' => $breakdowns,
            'top_contributors' => $topContributors,
            'recent_reports' => $recentReports,
            'recent_users' => $recentUsers,
            'recent_partners' => $recentPartners,
            'recent_activity' => $recentActivity,
            'system' => [
                'health_score' => $healthScore,
                'health_status' => $healthStatus,
                'health_color' => $healthColor,
                'failed_jobs' => $failedJobs,
                'queue_backlog' => $queueBacklog,
                'cache_ok' => $cacheOk,
                'last_report_human' => $lastReportAt ? Carbon::parse($lastReportAt)->diffForHumans() : 'No data',
                'last_user_human' => $lastUserAt ? Carbon::parse($lastUserAt)->diffForHumans() : 'No data',
                'last_activity_human' => $lastActivityAt ? Carbon::parse($lastActivityAt)->diffForHumans() : 'No data',
            ],
            'snapshot' => [
                'users' => $totalUsers,
                'active_users' => $activeUsers,
                'suspended_users' => $suspendedUsers,
                'active_subscriptions' => $activeSubscriptions,
                'new_subscriptions' => $newSubscriptions,
                'verified_partners' => $verifiedPartners,
                'rejected_partners' => $rejectedPartners,
                'new_partners' => $newPartners,
                'reviews' => $newReviews,
                'active_alerts' => $activeAlerts,
                'new_alerts' => $newAlerts,
                'active_places' => $activePlaces,
                'featured_places' => $featuredPlaces,
                'osm_places' => $osmPlaces,
                'bookings' => $newBookings,
                'completed_bookings' => $completedBookings,
                'sos_today' => $sosToday,
                'total_reports' => $totalReports,
                'high_priority_reports' => $highPriorityReports,
                'content_violations_today' => $contentViolationsToday,
                'xp_awarded' => $xpAwarded,
                'total_xp' => $totalXp,
                'oldest_pending_human' => $oldestPendingAt ? Carbon::parse($oldestPendingAt)->diffForHumans() : null,
            ],
            'xp' => [
                'awarded' => $xpAwarded,
                'total' => $totalXp,
                'rates' => [
                    'report_approval_xp' => (int) GameSetting::getValue('report_approval_xp', 10),
                    'alert_post_xp' => (int) GameSetting::getValue('alert_post_xp', 5),
                    'review_xp' => (int) GameSetting::getValue('review_xp', 3),
                    'place_submit_xp' => (int) GameSetting::getValue('place_submit_xp', 1),
                ],
            ],
        ];
    }

    /** Resolve the requested date range into start/end/previous/bucket metadata. */
    public function resolveRange(Request $request): array
    {
        $now = Carbon::now();
        $key = (string) $request->query('range', '30d');
        if (!in_array($key, self::RANGES, true)) {
            $key = '30d';
        }

        if ($key === 'custom') {
            $from = $this->parseDate($request->query('from'));
            $to = $this->parseDate($request->query('to'));

            if (!$from || !$to) {
                $key = '30d';
            } else {
                $start = $from->copy()->startOfDay();
                $end = $to->copy()->endOfDay();
                if ($start->gt($end)) {
                    [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
                }
                if ($start->diffInDays($end) > 366) {
                    $start = $end->copy()->subDays(366)->startOfDay();
                }
                if ($end->gt($now)) {
                    $end = $now->copy();
                }
                if ($start->gt($end)) {
                    $start = $end->copy()->startOfDay();
                }

                $days = (int) $start->diffInDays($end) + 1;
                $bucket = $days <= 2 ? 'hour' : ($days <= 62 ? 'day' : 'month');

                return $this->rangePayload('custom', 'Custom range', $start, $end, $bucket, 'previous period');
            }
        }

        if ($key === 'today') {
            $start = $now->copy()->startOfDay();
            $end = $now->copy();
            return $this->rangePayload($key, 'Today', $start, $end, 'hour', 'previous day', $start->copy()->subDay(), $end->copy()->subDay());
        }

        if ($key === '12m') {
            $start = $now->copy()->subMonths(11)->startOfMonth();
            $end = $now->copy();
            return $this->rangePayload($key, 'Last 12 months', $start, $end, 'month', 'previous 12 months', $start->copy()->subMonths(12), $end->copy()->subMonths(12));
        }

        $days = ['7d' => 7, '30d' => 30, '90d' => 90][$key] ?? 30;
        $label = ['7d' => 'Last 7 days', '30d' => 'Last 30 days', '90d' => 'Last 90 days'][$key] ?? 'Last 30 days';
        $start = $now->copy()->subDays($days - 1)->startOfDay();
        $end = $now->copy();

        return $this->rangePayload($key, $label, $start, $end, 'day', 'previous '.$days.' days', $start->copy()->subDays($days), $end->copy()->subDays($days));
    }

    private function rangePayload(string $key, string $label, Carbon $start, Carbon $end, string $bucket, string $comparison, ?Carbon $previousStart = null, ?Carbon $previousEnd = null): array
    {
        if (!$previousStart || !$previousEnd) {
            $seconds = max(1, $start->diffInSeconds($end) + 1);
            $previousEnd = $start->copy()->subSecond();
            $previousStart = $previousEnd->copy()->subSeconds($seconds - 1);
        }

        $buckets = $this->bucketKeys($start, $end, $bucket);

        return [
            'key' => $key,
            'label' => $label,
            'comparison' => $comparison,
            'bucket' => $bucket,
            'start' => $start,
            'end' => $end,
            'previous_start' => $previousStart,
            'previous_end' => $previousEnd,
            'bucket_keys' => $buckets['keys'],
            'bucket_labels' => $buckets['labels'],
        ];
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Compact automation block for the dashboard's "System health" card.
     */
    private function automationPayload(User $user): array
    {
        try {
            $overview = app(AutomationService::class)->overview();
        } catch (Throwable $e) {
            $overview = [];
        }

        $queue = $overview['queue'] ?? [];
        $summary = $overview['summary'] ?? [];
        $scheduler = $overview['scheduler'] ?? [];
        $worker = $overview['worker'] ?? [];

        return [
            'can_manage' => $user->isAdmin() || $user->hasPermission('manage_automation'),
            'url' => route('admin.automation'),
            'scheduler_status' => $scheduler['status'] ?? 'never',
            'scheduler_label' => $scheduler['label'] ?? 'Not running',
            'scheduler_tone' => $scheduler['tone'] ?? 'rose',
            'scheduler_human' => $scheduler['human'] ?? 'Never',
            'worker_status' => $worker['status'] ?? 'never',
            'worker_label' => $worker['label'] ?? 'Not detected',
            'worker_tone' => $worker['tone'] ?? 'amber',
            'worker_human' => $worker['human'] ?? 'Never',
            'pending_jobs' => (int) ($queue['pending_jobs'] ?? 0),
            'processing_jobs' => (int) ($queue['processing_jobs'] ?? 0),
            'failed_jobs' => (int) ($queue['failed_jobs'] ?? 0),
            'oldest_human' => $queue['oldest_human'] ?? 'None waiting',
            'tasks_total' => (int) ($summary['tasks_total'] ?? 0),
            'tasks_enabled' => (int) ($summary['tasks_enabled'] ?? 0),
            'tasks_disabled' => (int) ($summary['tasks_disabled'] ?? 0),
            'tasks_failed' => (int) ($summary['tasks_failed'] ?? 0),
            'next_task' => $summary['next_task'] ?? null,
            'next_run_human' => $summary['next_run_human'] ?? 'No enabled task',
            'last_task' => $summary['last_task'] ?? null,
            'last_run_human' => $summary['last_run_human'] ?? 'Never',
        ];
    }

    private function rangeMeta(array $range, array $labels): array
    {
        $start = $range['start'];
        $end = $range['end'];
        $human = $start->isSameDay($end)
            ? $start->format('M j, Y')
            : $start->format('M j, Y').' - '.$end->format('M j, Y');

        return [
            'key' => $range['key'],
            'label' => $range['label'],
            'human' => $human,
            'comparison' => $range['comparison'],
            'bucket' => $range['bucket'],
            'buckets' => count($labels),
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'start_iso' => $start->toIso8601String(),
            'end_iso' => $end->toIso8601String(),
        ];
    }

    /** @return array{keys: array<int, string>, labels: array<int, string>} */
    private function bucketKeys(Carbon $start, Carbon $end, string $bucket): array
    {
        $keys = [];
        $labels = [];
        $sameDay = $start->isSameDay($end);

        if ($bucket === 'hour') {
            $cursor = $start->copy()->startOfHour();
            $last = $end->copy()->startOfHour();
            while ($cursor->lte($last)) {
                $keys[] = $cursor->format('Y-m-d H:00:00');
                $labels[] = $sameDay ? $cursor->format('ga') : $cursor->format('M j, ga');
                $cursor->addHour();
            }
        } elseif ($bucket === 'month') {
            $cursor = $start->copy()->startOfMonth();
            $last = $end->copy()->startOfMonth();
            while ($cursor->lte($last)) {
                $keys[] = $cursor->format('Y-m');
                $labels[] = $cursor->format('M Y');
                $cursor->addMonth();
            }
        } else {
            $cursor = $start->copy()->startOfDay();
            $last = $end->copy()->startOfDay();
            while ($cursor->lte($last)) {
                $keys[] = $cursor->format('Y-m-d');
                $labels[] = $cursor->format('M j');
                $cursor->addDay();
            }
        }

        return ['keys' => $keys, 'labels' => $labels];
    }

    private function bucketExpression(string $column, string $bucket): string
    {
        return match ($bucket) {
            'hour' => "DATE_FORMAT({$column}, '%Y-%m-%d %H:00:00')",
            'month' => "DATE_FORMAT({$column}, '%Y-%m')",
            default => "DATE({$column})",
        };
    }

    private function countSeries(string $table, Carbon $start, Carbon $end, string $bucket, ?callable $constrain = null, string $column = 'created_at'): array
    {
        $expr = $this->bucketExpression($column, $bucket);
        $query = DB::table($table)->whereBetween($column, [$start, $end]);

        if ($constrain) {
            $constrain($query);
        }

        return $query
            ->selectRaw("{$expr} as bucket, COUNT(*) as aggregate")
            ->groupBy('bucket')
            ->pluck('aggregate', 'bucket')
            ->all();
    }

    private function sumSeries(string $table, string $timeColumn, string $sumColumn, Carbon $start, Carbon $end, string $bucket, ?callable $constrain = null): array
    {
        $expr = $this->bucketExpression($timeColumn, $bucket);
        $query = DB::table($table)->whereBetween($timeColumn, [$start, $end]);

        if ($constrain) {
            $constrain($query);
        }

        return $query
            ->selectRaw("{$expr} as bucket, COALESCE(SUM({$sumColumn}), 0) as aggregate")
            ->groupBy('bucket')
            ->pluck('aggregate', 'bucket')
            ->all();
    }

    private function fillSeries(array $keys, array $rows): array
    {
        return array_map(fn ($key) => $rows[$key] ?? 0, $keys);
    }

    private function combineSeries(array $series): array
    {
        $length = count(reset($series) ?: []);

        return array_map(function ($index) use ($series, $length) {
            $total = 0;
            foreach ($series as $values) {
                $total += (float) ($values[$index] ?? 0);
            }
            return $total;
        }, $length > 0 ? range(0, $length - 1) : []);
    }

    private function percentages(array $rows): array
    {
        $max = max(1, ...array_map(fn ($row) => (int) $row['value'], $rows ?: [['value' => 0]]));

        return array_map(function ($row) use ($max) {
            $row['percent'] = (int) round(($row['value'] / $max) * 100);
            return $row;
        }, $rows);
    }

    private function percentageChange(float|int $current, float|int $previous): int
    {
        if ($previous == 0) {
            return $current == 0 ? 0 : 100;
        }

        return (int) round((($current - $previous) / max(0.0001, abs($previous))) * 100);
    }

    private function sparkPoints(array $values, int $width = 104, int $height = 30): string
    {
        $values = array_values($values);
        if ($values === []) {
            return '0,'.$height.' '.$width.','.$height;
        }

        $max = max($values);
        $min = min($values);
        $span = max(1, $max - $min);
        $step = count($values) > 1 ? $width / (count($values) - 1) : $width;
        $points = [];

        foreach ($values as $index => $value) {
            $x = round($index * $step, 1);
            $y = round($height - (($value - $min) / $span) * ($height - 4) - 2, 1);
            $points[] = $x.','.$y;
        }

        return implode(' ', $points);
    }

    private function auditColor(?string $action): string
    {
        $action = (string) $action;

        if (str_contains($action, 'security') || str_contains($action, 'denied')) {
            return 'rose';
        }
        if (str_contains($action, 'approve') || str_contains($action, 'paid') || str_contains($action, 'verify')) {
            return 'emerald';
        }
        if (str_contains($action, 'delete') || str_contains($action, 'reject') || str_contains($action, 'suspend')) {
            return 'amber';
        }

        return 'slate';
    }
}
