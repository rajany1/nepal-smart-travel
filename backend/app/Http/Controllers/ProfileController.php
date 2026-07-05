<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Report;
use App\Models\Alert;
use App\Models\ReportComment;
use App\Models\PlaceReview;
use App\Models\GameSetting;
use App\Services\AchievementService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class ProfileController extends Controller
{
    /**
     * Get full profile data with all stats, badges, achievements
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Calculate dynamic stats
        $totalReports = Report::where('user_id', $user->id)->count();
        $approvedReports = Report::where('user_id', $user->id)->where('status', 'approved')->count();
        $rejectedReports = Report::where('user_id', $user->id)->where('status', 'rejected')->count();
        $pendingReports = Report::where('user_id', $user->id)->where('status', 'pending')->count();
        $hasAlertCreator = Schema::hasColumn('alerts', 'created_by');
        $totalAlerts = $hasAlertCreator ? Alert::where('created_by', $user->id)->count() : 0;
        $totalComments = ReportComment::where('user_id', $user->id)->count();
        $totalReviews = PlaceReview::where('user_id', $user->id)->count();
        
        // Approval rate
        $approvalRate = $totalReports > 0 ? round(($approvedReports / $totalReports) * 100, 1) : 0;
        
        // Calculate rank (position among all users by XP)
        $rank = User::where('total_xp', '>', $user->total_xp)->count() + 1;
        
        // Recent activity
        $recentReports = Report::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($report) {
                return [
                    'type' => 'report',
                    'title' => $report->title ?? 'Report',
                    'status' => $report->status,
                    'created_at' => $report->created_at,
                ];
            });
        
        if ($hasAlertCreator) {
            $recentAlerts = Alert::where('created_by', $user->id)
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get()
                ->map(function ($alert) {
                    return [
                        'type' => 'alert',
                        'title' => $alert->title ?? 'Alert',
                        'severity' => $alert->severity,
                        'created_at' => $alert->created_at,
                    ];
                });
        } else {
            $recentAlerts = collect();
        }
        
        $recentReviews = PlaceReview::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function ($review) {
                return [
                    'type' => 'review',
                    'title' => $review->title ?? 'Review',
                    'rating' => $review->rating,
                    'created_at' => $review->created_at,
                ];
            });
        
        // Merge and sort activity by date
        $activity = collect()
            ->merge($recentReports)
            ->merge($recentAlerts)
            ->merge($recentReviews)
            ->sortByDesc('created_at')
            ->take(20)
            ->values();
        
        $achievementService = app(AchievementService::class);

        $nextLevelXp = $achievementService->getNextLevelXp($user->current_level);
        $currentLevelName = $achievementService->getLevelName($user->current_level);
        $nextLevelName = $achievementService->getNextLevelName($user->current_level);
        $levelProgress = $achievementService->getLevelProgress($user);

        $badges = $achievementService->getUserAchievements($user);
        
        // Member since
        $memberSince = $user->created_at ? $user->created_at->diffInDays(now()) : 0;
        
        return response()->json([
            'success' => true,
            'data' => [
                // User basic info
                'user_id' => (string)$user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar,
                'bio' => $user->bio,
                'role' => $user->roleName ?? 'user',
                'status' => $user->status ?? 'active',
                'gender' => $user->gender ?? null,
                'interest' => $user->interest ?? null,
                'profile_completed' => (bool)($user->profile_completed ?? false),
                'created_at' => $user->created_at,
                'member_since_days' => $memberSince,
                
                // XP & Level
                'total_xp' => (int)($user->total_xp ?? 0),
                'total_points' => (int)($user->total_xp ?? 0),
                'current_level' => (int)($user->current_level ?? 1),
                'level_name' => $currentLevelName,
                'next_level_name' => $nextLevelName,
                'next_level_xp' => $nextLevelXp,
                'level_progress' => $levelProgress,
                'rank' => $rank,
                
                // Stats
                'total_reports' => $totalReports,
                'approved_reports' => $approvedReports,
                'rejected_reports' => $rejectedReports,
                'pending_reports' => $pendingReports,
                'approval_rate' => $approvalRate,
                'total_alerts' => $totalAlerts,
                'total_comments' => $totalComments,
                'total_reviews' => $totalReviews,
                
                // Verification
                'verification_tick' => $user->verification_tick ?? 'none',
                
                // Badges & Achievements
                'badges' => $badges,
                'expertise_regions' => $user->expertise_regions ?? [],
                
                // Activity
                'recent_activity' => $activity,
                
                // Last contribution
                'last_contribution_at' => $user->last_contribution_at,
            ]
        ]);
    }
    
    /**
     * Update profile
     */
    public function update(Request $request)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'bio' => 'nullable|string|max:500',
            'gender' => 'nullable|string|in:Male,Female,Other',
            'interest' => 'nullable|string',
            'expertise_regions' => 'nullable|array',
            'expertise_regions.*' => 'string',
        ]);
        
        $user->update($validated);
        
        // Recalculate stats after update
        $updatedUser = $user->fresh();
        
        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'user_id' => (string)$updatedUser->id,
                'name' => $updatedUser->name,
                'email' => $updatedUser->email,
                'phone' => $updatedUser->phone,
                'avatar_url' => $updatedUser->avatar,
                'bio' => $updatedUser->bio,
                'gender' => $updatedUser->gender,
                'interest' => $updatedUser->interest,
                'profile_completed' => (bool)($updatedUser->profile_completed ?? false),
            ]
        ]);
    }
    
    /**
     * Upload/update avatar
     */
    public function updateAvatar(Request $request)
    {
        $user = $request->user();
        
        $request->validate([
            'avatar' => 'required|string', // Base64 or URL
        ]);
        
        $user->update([
            'avatar' => $request->avatar,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Avatar updated successfully',
            'data' => [
                'avatar_url' => $user->fresh()->avatar,
            ]
        ]);
    }
    
    /**
     * Get detailed stats breakdown
     */
    public function stats(Request $request)
    {
        $user = $request->user();
        
        // Reports by status
        $reportsByStatus = [
            'pending' => Report::where('user_id', $user->id)->where('status', 'pending')->count(),
            'approved' => Report::where('user_id', $user->id)->where('status', 'approved')->count(),
            'rejected' => Report::where('user_id', $user->id)->where('status', 'rejected')->count(),
        ];
        
        // Reports by category
        $reportsByCategory = Report::where('user_id', $user->id)
            ->selectRaw('category_id, count(*) as count')
            ->groupBy('category_id')
            ->get()
            ->pluck('count', 'category_id');
        
        $hasAlertCreator = Schema::hasColumn('alerts', 'created_by');

        // Alerts by severity
        $alertsBySeverity = [
            'critical' => $hasAlertCreator ? Alert::where('created_by', $user->id)->where('severity', 'critical')->count() : 0,
            'high' => $hasAlertCreator ? Alert::where('created_by', $user->id)->where('severity', 'high')->count() : 0,
            'medium' => $hasAlertCreator ? Alert::where('created_by', $user->id)->where('severity', 'medium')->count() : 0,
            'low' => $hasAlertCreator ? Alert::where('created_by', $user->id)->where('severity', 'low')->count() : 0,
        ];
        
        // XP breakdown
        $reportApprovalXp = GameSetting::getValue('report_approval_xp', 10);
        $alertPostXp = GameSetting::getValue('alert_post_xp', 5);
        $reviewXp = GameSetting::getValue('review_xp', 3);
        $xpBreakdown = [
            'from_reports' => $reportsByStatus['approved'] * $reportApprovalXp,
            'from_alerts' => ($hasAlertCreator ? Alert::where('created_by', $user->id)->count() : 0) * $alertPostXp,
            'from_reviews' => PlaceReview::where('user_id', $user->id)->count() * $reviewXp,
            'rates' => [
                'report_approval_xp' => $reportApprovalXp,
                'alert_post_xp' => $alertPostXp,
                'review_xp' => $reviewXp,
            ],
        ];
        
        // Monthly activity (last 6 months)
        $monthlyActivity = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $startOfMonth = $month->copy()->startOfMonth();
            $endOfMonth = $month->copy()->endOfMonth();
            
            $monthlyActivity[] = [
                'month' => $month->format('M'),
                'year' => $month->format('Y'),
                'reports' => Report::where('user_id', $user->id)
                    ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                    ->count(),
                'alerts' => ($hasAlertCreator ? Alert::where('created_by', $user->id)
                    ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
                    ->count() : 0),
            ];
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'reports_by_status' => $reportsByStatus,
                'reports_by_category' => $reportsByCategory,
                'alerts_by_severity' => $alertsBySeverity,
                'xp_breakdown' => $xpBreakdown,
                'monthly_activity' => $monthlyActivity,
            ]
        ]);
    }
    
    /**
     * Get all badges with unlock conditions
     */
    public function badges(Request $request)
    {
        $badges = app(AchievementService::class)->getUserAchievements($request->user());

        return response()->json([
            'success' => true,
            'data' => $badges,
        ]);
    }
    
    /**
     * Get recent activity timeline
     */
    public function activity(Request $request)
    {
        $user = $request->user();
        $limit = $request->input('limit', 20);
        
        $hasAlertCreator = Schema::hasColumn('alerts', 'created_by');

        $recentReports = Report::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get()
            ->map(function ($report) {
                return [
                    'id' => (string)$report->id,
                    'type' => 'report',
                    'action' => 'submitted_report',
                    'title' => $report->title ?? 'Untitled Report',
                    'description' => $report->description ?? '',
                    'status' => $report->status,
                    'created_at' => $report->created_at,
                ];
            });
        
        if ($hasAlertCreator) {
            $recentAlerts = Alert::where('created_by', $user->id)
                ->orderBy('created_at', 'desc')
                ->take($limit)
                ->get()
                ->map(function ($alert) {
                    return [
                        'id' => (string)$alert->id,
                        'type' => 'alert',
                        'action' => 'posted_alert',
                        'title' => $alert->title ?? 'Untitled Alert',
                        'description' => $alert->description ?? '',
                        'severity' => $alert->severity,
                        'created_at' => $alert->created_at,
                    ];
                });
        } else {
            $recentAlerts = collect();
        }
        
        $recentReviews = PlaceReview::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->take($limit)
            ->get()
            ->map(function ($review) {
                return [
                    'id' => (string)$review->id,
                    'type' => 'review',
                    'action' => 'wrote_review',
                    'title' => $review->title ?? 'Review',
                    'rating' => $review->rating,
                    'description' => $review->description ?? '',
                    'created_at' => $review->created_at,
                ];
            });
        
        $activity = collect()
            ->merge($recentReports)
            ->merge($recentAlerts)
            ->merge($recentReviews)
            ->sortByDesc('created_at')
            ->take($limit)
            ->values();
        
        return response()->json([
            'success' => true,
            'data' => $activity,
        ]);
    }
    
    /**
     * Update user settings (notifications, preferences)
     */
    public function updateSettings(Request $request)
    {
        $user = $request->user();
        
        $validated = $request->validate([
            'notifications_enabled' => 'boolean',
            'email_notifications' => 'boolean',
            'push_notifications' => 'boolean',
            'language' => 'string|in:en,ne',
            'theme' => 'string|in:light,dark',
            'show_on_map' => 'boolean',
        ]);
        
        // Get existing settings or use defaults
        $settings = $user->settings ?? [];
        foreach ($validated as $key => $value) {
            $settings[$key] = $value;
        }
        $user->settings = $settings;
        $user->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
            'data' => $validated,
        ]);
    }
    
    /**
     * Get user settings
     */
    public function getSettings(Request $request)
    {
        $user = $request->user();
        
        // Default settings merged with user's stored settings
        $defaults = [
            'notifications_enabled' => true,
            'email_notifications' => true,
            'push_notifications' => true,
            'language' => 'en',
            'theme' => 'light',
            'show_on_map' => true,
        ];
        
        $stored = $user->settings ?? [];
        $settings = array_merge($defaults, $stored);
        
        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }
    
    /**
     * Get available profile field options (for dropdowns and multi-selects)
     */
    public function fieldOptions(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'genders' => [
                    ['value' => 'Male', 'label' => 'Male'],
                    ['value' => 'Female', 'label' => 'Female'],
                    ['value' => 'Other', 'label' => 'Other'],
                ],
                'interests' => [
                    ['value' => 'Adventure', 'label' => 'Adventure'],
                    ['value' => 'Culture', 'label' => 'Culture'],
                    ['value' => 'Nature', 'label' => 'Nature'],
                    ['value' => 'Photography', 'label' => 'Photography'],
                    ['value' => 'Food', 'label' => 'Food & Cuisine'],
                    ['value' => 'History', 'label' => 'History'],
                    ['value' => 'Hiking', 'label' => 'Hiking'],
                    ['value' => 'Wildlife', 'label' => 'Wildlife'],
                    ['value' => 'Trekking', 'label' => 'Trekking'],
                    ['value' => 'Yoga', 'label' => 'Yoga & Wellness'],
                    ['value' => 'Meditation', 'label' => 'Meditation'],
                    ['value' => 'Shopping', 'label' => 'Shopping'],
                    ['value' => 'Festivals', 'label' => 'Festivals'],
                ],
                'expertise_regions' => [
                    ['value' => 'Kathmandu Valley', 'label' => 'Kathmandu Valley'],
                    ['value' => 'Pokhara', 'label' => 'Pokhara'],
                    ['value' => 'Chitwan', 'label' => 'Chitwan'],
                    ['value' => 'Lumbini', 'label' => 'Lumbini'],
                    ['value' => 'Everest Region', 'label' => 'Everest Region'],
                    ['value' => 'Annapurna Region', 'label' => 'Annapurna Region'],
                    ['value' => 'Mustang', 'label' => 'Mustang'],
                    ['value' => 'Janakpur', 'label' => 'Janakpur'],
                    ['value' => 'Bardia', 'label' => 'Bardia'],
                    ['value' => 'Ilam', 'label' => 'Ilam'],
                    ['value' => 'Bandipur', 'label' => 'Bandipur'],
                    ['value' => 'Nagarkot', 'label' => 'Nagarkot'],
                ]
            ]
        ]);
    }
    
    /**
     * Get profile field definitions (schema for form building)
     */
    public function fieldDefinitions(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'fields' => [
                    [
                        'name' => 'name',
                        'label' => 'Full Name',
                        'type' => 'text',
                        'required' => true,
                        'validation' => 'min:3,max:255',
                        'placeholder' => 'Enter your full name',
                        'icon' => 'person',
                    ],
                    [
                        'name' => 'email',
                        'label' => 'Email Address',
                        'type' => 'email',
                        'required' => true,
                        'validation' => 'email',
                        'placeholder' => 'your.email@example.com',
                        'icon' => 'email',
                        'readonly' => true,
                    ],
                    [
                        'name' => 'phone',
                        'label' => 'Phone Number',
                        'type' => 'phone',
                        'required' => false,
                        'validation' => 'min:7,max:20',
                        'placeholder' => '+977 XXXXXXXXXX',
                        'icon' => 'phone',
                    ],
                    [
                        'name' => 'bio',
                        'label' => 'Bio',
                        'type' => 'textarea',
                        'required' => false,
                        'validation' => 'max:500',
                        'placeholder' => 'Tell us about yourself...',
                        'icon' => 'description',
                        'rows' => 4,
                    ],
                    [
                        'name' => 'gender',
                        'label' => 'Gender',
                        'type' => 'select',
                        'required' => false,
                        'options_key' => 'genders',
                        'placeholder' => 'Select your gender',
                        'icon' => 'wc',
                    ],
                    [
                        'name' => 'interest',
                        'label' => 'Primary Interest',
                        'type' => 'select',
                        'required' => false,
                        'options_key' => 'interests',
                        'placeholder' => 'What interests you most?',
                        'icon' => 'favorite',
                    ],
                    [
                        'name' => 'expertise_regions',
                        'label' => 'Expertise Regions',
                        'type' => 'multiselect',
                        'required' => false,
                        'options_key' => 'expertise_regions',
                        'placeholder' => 'Select regions you know well',
                        'icon' => 'location_on',
                        'max_items' => 5,
                    ],
                ]
            ]
        ]);
    }
    
    /**
     * Get profile display sections configuration
     * Defines which sections should be shown and in what order on the profile display screen
     */
    public function profileSections(Request $request)
    {
        $user = $request->user();
        
        // Calculate stats for conditional section visibility
        $totalReports = Report::where('user_id', $user->id)->count();
        $allBadges = app(AchievementService::class)->getUserAchievements($user);
        $totalBadges = count($allBadges);
        $unlockedBadges = collect($allBadges)->where('unlocked', true)->count();
        
        $sections = [
            [
                'id' => 'header',
                'type' => 'profile_header',
                'title' => 'Profile',
                'visible' => true,
                'order' => 1,
                'description' => 'User profile header with avatar and basic info'
            ],
            [
                'id' => 'stats',
                'type' => 'stats_card',
                'title' => 'Statistics',
                'visible' => true,
                'order' => 2,
                'description' => 'Key stats: XP, Reports, Approvals, Alerts',
                'fields' => [
                    ['key' => 'total_xp', 'label' => 'Total XP', 'icon' => 'emoji_events'],
                    ['key' => 'total_reports', 'label' => 'Reports', 'icon' => 'assignment'],
                    ['key' => 'approved_reports', 'label' => 'Approved', 'icon' => 'check_circle'],
                    ['key' => 'approval_rate', 'label' => 'Rate', 'icon' => 'trending_up', 'unit' => '%'],
                    ['key' => 'total_alerts', 'label' => 'Alerts', 'icon' => 'warning_amber'],
                    ['key' => 'total_reviews', 'label' => 'Reviews', 'icon' => 'rate_review'],
                    ['key' => 'total_comments', 'label' => 'Comments', 'icon' => 'comment'],
                    ['key' => 'rejected_reports', 'label' => 'Rejected', 'icon' => 'cancel'],
                ]
            ],
            [
                'id' => 'xp_progress',
                'type' => 'xp_progress',
                'title' => 'Level Progress',
                'visible' => true,
                'order' => 3,
                'description' => 'XP progress towards next level'
            ],
            [
                'id' => 'verification',
                'type' => 'verification_tick',
                'title' => 'Verification Status',
                'visible' => true,
                'order' => 4,
                'description' => 'User verification tier and credentials'
            ],
            [
                'id' => 'badges',
                'type' => 'badges_section',
                'title' => 'Badges & Achievements',
                'visible' => $totalBadges > 0,
                'order' => 5,
                'description' => 'Unlocked and locked badges',
                'stats' => [
                    'unlocked' => $unlockedBadges,
                    'total' => $totalBadges,
                ]
            ],
            [
                'id' => 'activity',
                'type' => 'recent_activity',
                'title' => 'Recent Activity',
                'visible' => $totalReports > 0,
                'order' => 6,
                'description' => 'Recent reports, alerts, and reviews',
                'limit' => 5,
                'show_see_more' => true,
            ],
        ];
        
        return response()->json([
            'success' => true,
            'data' => $sections,
        ]);
    }
    
    // ============ Helper Methods ============
    
    private function getLevelName(int $level): string
    {
        if ($level <= 5) return 'Explorer';
        if ($level <= 15) return 'Contributor';
        if ($level <= 30) return 'Trusted Local';
        if ($level <= 50) return 'Regional Guide';
        if ($level <= 100) return 'Community Expert';
        return 'Legendary Hero';
    }
    
    private function getNextLevelName(int $level): string
    {
        if ($level <= 5) return 'Contributor';
        if ($level <= 15) return 'Trusted Local';
        if ($level <= 30) return 'Regional Guide';
        if ($level <= 50) return 'Community Expert';
        if ($level <= 100) return 'Legendary Hero';
        return 'Max Level';
    }
    
    private function getNextLevelXp(int $level): int
    {
        if ($level <= 5) return 50;
        if ($level <= 15) return 150;
        if ($level <= 30) return 300;
        if ($level <= 50) return 500;
        if ($level <= 100) return 1000;
        return 0;
    }
    
    private function getBadgesWithInfo($user): array
    {
        $totalReports = Report::where('user_id', $user->id)->count();
        $approvedReports = Report::where('user_id', $user->id)->where('status', 'approved')->count();
        $hasAlertCreator = Schema::hasColumn('alerts', 'created_by');
        $totalAlerts = $hasAlertCreator ? Alert::where('created_by', $user->id)->count() : 0;
        $totalReviews = PlaceReview::where('user_id', $user->id)->count();
        $totalComments = ReportComment::where('user_id', $user->id)->count();
        $criticalAlerts = $hasAlertCreator ? Alert::where('created_by', $user->id)->where('severity', 'critical')->count() : 0;
        
        $badgeDefinitions = [
            ['id' => 'first_report', 'name' => 'First Report', 'description' => 'Submit your first report', 'icon' => 'description', 'unlocked' => $totalReports >= 1],
            ['id' => 'report_master', 'name' => 'Report Master', 'description' => 'Submit 10 reports', 'icon' => 'assignment', 'unlocked' => $totalReports >= 10],
            ['id' => 'quality_contributor', 'name' => 'Quality Contributor', 'description' => 'Get 5 reports approved', 'icon' => 'verified', 'unlocked' => $approvedReports >= 5],
            ['id' => 'top_reporter', 'name' => 'Top Reporter', 'description' => 'Get 20 reports approved', 'icon' => 'star', 'unlocked' => $approvedReports >= 20],
            ['id' => 'alert_hero', 'name' => 'Alert Hero', 'description' => 'Post 5 alerts', 'icon' => 'warning', 'unlocked' => $totalAlerts >= 5],
            ['id' => 'reviewer', 'name' => 'Place Reviewer', 'description' => 'Review 3 places', 'icon' => 'rate_review', 'unlocked' => $totalReviews >= 3],
            ['id' => 'explorer', 'name' => 'Explorer', 'description' => 'Reach level 5', 'icon' => 'explore', 'unlocked' => ($user->current_level ?? 1) >= 5],
            ['id' => 'contributor', 'name' => 'Contributor', 'description' => 'Reach level 15', 'icon' => 'trending_up', 'unlocked' => ($user->current_level ?? 1) >= 15],
            ['id' => 'trusted_local', 'name' => 'Trusted Local', 'description' => 'Reach level 30', 'icon' => 'groups', 'unlocked' => ($user->current_level ?? 1) >= 30],
            ['id' => 'regional_guide', 'name' => 'Regional Guide', 'description' => 'Reach level 50', 'icon' => 'map', 'unlocked' => ($user->current_level ?? 1) >= 50],
            ['id' => 'community_expert', 'name' => 'Community Expert', 'description' => 'Reach level 100', 'icon' => 'psychology', 'unlocked' => ($user->current_level ?? 1) >= 100],
            ['id' => 'helper', 'name' => 'Community Helper', 'description' => 'Leave 10 comments', 'icon' => 'comment', 'unlocked' => $totalComments >= 10],
            ['id' => 'emergency_responder', 'name' => 'Emergency Responder', 'description' => 'Post a critical alert', 'icon' => 'emergency', 'unlocked' => $criticalAlerts >= 1],
        ];
        
        return $badgeDefinitions;
    }
}