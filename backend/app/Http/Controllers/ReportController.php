<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Report;
use App\Models\ReportCategorie;
use App\Models\ReportComment;
use App\Models\ReportReaction;
use App\Models\Place;
use App\Models\GameSetting;
use App\Services\AchievementService;
use App\Services\ExifGpsVerificationService;
use App\Services\ModeratorService;
use App\Helpers\GeoHelper;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ReportController extends Controller
{
    /**
     * Get all report categories (dynamic, from DB)
     */
    public function categories(Request $request)
    {
        $categories = ReportCategorie::all()->map(fn($cat) => [
            'id' => $cat->id,
            'name' => $cat->name,
            'icon' => $cat->icon,
        ]);

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Get dynamic form configuration for the report submission form.
     * This makes the mobile form fully data-driven from the backend.
     */
    public function formConfig(Request $request)
    {
        $config = [
            'fields' => [
                [
                    'name' => 'title',
                    'label' => 'Report Title',
                    'type' => 'text',
                    'required' => true,
                    'validation' => 'required|string|max:255',
                    'placeholder' => 'What do you want to report?',
                    'icon' => 'title',
                    'order' => 1,
                ],
                [
                    'name' => 'category_id',
                    'label' => 'Category',
                    'type' => 'select',
                    'required' => true,
                    'validation' => 'required',
                    'placeholder' => 'Select a category',
                    'icon' => 'category',
                    'order' => 2,
                    'options_source' => 'categories',  // fetches from /reports/categories
                ],
                [
                    'name' => 'priority',
                    'label' => 'Priority',
                    'type' => 'select',
                    'required' => false,
                    'validation' => 'nullable|string|in:low,medium,high,critical',
                    'placeholder' => 'Select priority',
                    'icon' => 'flag',
                    'order' => 3,
                    'options' => [
                        ['value' => 'low', 'label' => 'Low', 'icon' => 'info_outline', 'color' => '#6B7280'],
                        ['value' => 'medium', 'label' => 'Medium', 'icon' => 'info_outline', 'color' => '#F59E0B'],
                        ['value' => 'high', 'label' => 'High', 'icon' => 'warning', 'color' => '#EF4444'],
                        ['value' => 'critical', 'label' => 'Critical', 'icon' => 'warning', 'color' => '#DC2626'],
                    ],
                ],
                [
                    'name' => 'description',
                    'label' => 'Description',
                    'type' => 'textarea',
                    'required' => true,
                    'validation' => 'required|string',
                    'placeholder' => 'Provide detailed information...',
                    'icon' => 'description',
                    'order' => 4,
                ],
            ],
            'submit_button_text' => 'Submit Report',
            'notice' => 'Your report will be reviewed by moderators before being published.',
        ];

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }

    /**
     * List reports with filters
     */
    public function index(Request $request)
    {
        // Try to detect authenticated user from bearer token even on public route
        $user = $request->user() ?? Auth::guard('sanctum')->user();

        $query = Report::with(['user', 'category', 'media', 'reactions']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            // Default: only show approved reports for public,
            // but include own reports regardless of status
            $user = $request->user();
            if (!$user || !($user->isAdmin() || $user->isModerator())) {
                $query->where(function($q) use ($user) {
                    $q->where('status', 'approved');
                    if ($user) {
                        $q->orWhere('user_id', $user->id);
                    }
                });
            }
        }

        // Filter by category
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by district
        if ($request->filled('district')) {
            $query->where('district', $request->district);
        }

        // Filter by priority
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        // Location-based filter (nearby)
        if ($request->filled('lat') && $request->filled('lng')) {
            $lat = (float) $request->lat;
            $lng = (float) $request->lng;
            $radiusKm = (float) ($request->input('radius_km', 5));

            // Approximate degree-to-km conversion
            $latDelta = $radiusKm / 111.0;
            $lngDelta = $radiusKm / (111.0 * cos(deg2rad($lat)));

            $query->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
                  ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta]);
        }

        // Sort
        $sortBy = $request->input('sort_by', 'latest');
        match ($sortBy) {
            'oldest' => $query->oldest(),
            'priority' => $query->orderByRaw("FIELD(priority, 'critical', 'high', 'medium', 'low')"),
            'helpful' => $query->orderByDesc('helpful_count'),
            default => $query->latest(),
        };

        $limit = (int) $request->input('limit', 20);
        $offset = (int) $request->input('offset', 0);

        $total = $query->count();
        $reports = $query->skip($offset)->take($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $reports->map(fn($report) => $this->formatReport($report)),
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total,
            ],
        ]);
    }

    /**
     * Get reports submitted by the authenticated user
     */
    public function myReports(Request $request)
    {
        // Manually authenticate from token since route is public
        // (public routes don't run auth:sanctum middleware)
        $user = $request->user();
        if (!$user) {
            // Try manual authentication from bearer token
            $user = Auth::guard('sanctum')->user();
        }
        if (!$user) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => [
                    'total' => 0,
                    'limit' => (int) $request->input('limit', 20),
                    'offset' => 0,
                    'has_more' => false,
                ],
            ]);
        }

        $query = Report::with(['user', 'category', 'media', 'reactions'])->where('user_id', $user->id);

        $limit = (int) $request->input('limit', 20);
        $offset = (int) $request->input('offset', 0);
        $total = $query->count();
        $reports = $query->latest()->skip($offset)->take($limit)->get();

        return response()->json([
            'success' => true,
            'data' => $reports->map(fn($report) => $this->formatReport($report)),
            'meta' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total,
            ],
        ]);
    }

    /**
     * Get single report details
     */
    public function show(Request $request, $id)
    {
        $report = Report::with(['user', 'category', 'comments.user', 'comments.parentComment.user', 'media'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $this->formatReport($report, true),
        ]);
    }

    /**
     * Submit a new report
     * 
     * Anti-fake-report protection:
     * 1. EXIF GPS verification - validates photo GPS matches report location
     * 2. Only in-app camera captures are accepted (no gallery uploads)
     */
    public function store(Request $request)
    {
        // Coerce common boolean-ish values sent from mobile multipart/form-data
        $rawIsLive = $request->input('is_live_capture');
        $coercedIsLive = filter_var($rawIsLive, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($coercedIsLive === null) {
            // Accept '1'/'0' and numeric values too
            if ($rawIsLive === '1' || $rawIsLive === 1 || $rawIsLive === 'true') {
                $coercedIsLive = true;
            } else {
                $coercedIsLive = false;
            }
        }
        $request->merge(['is_live_capture' => $coercedIsLive]);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:10000',
            'category_id' => 'required|exists:report_categories,id',
            'priority' => 'nullable|string|in:low,medium,high,critical',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'district' => 'nullable|string|max:100',
            'image' => 'required|image|max:5120', // 5MB max, now REQUIRED
            'is_live_capture' => 'required|boolean', // Must be true - in-app camera only
            'photo_captured_at' => 'nullable|date',
        ]);

        // Layer 1: Enforce in-app camera capture (no gallery uploads)
        if (!$request->boolean('is_live_capture')) {
            return response()->json([
                'success' => false,
                'message' => 'Only in-app camera captures are allowed. Gallery uploads are not permitted.',
            ], 422);
        }

        $validated['user_id'] = $request->user()->id;
        $validated['status'] = 'pending';
        $validated['priority'] = $validated['priority'] ?? 'medium';
        // Only include `is_live_capture` if the column exists in DB (some setups may not have run migrations)
        if (Schema::hasColumn('reports', 'is_live_capture')) {
            $validated['is_live_capture'] = true;
        } else {
            unset($validated['is_live_capture']);
        }

        // Attempt to map report to an existing Place if it's very close to one
        try {
            $radiusMeters = (int) GameSetting::getValue('report_place_match_radius_meters', 50);
            if ($radiusMeters > 0 && isset($validated['latitude']) && isset($validated['longitude'])) {
                $lat = (float) $validated['latitude'];
                $lng = (float) $validated['longitude'];

                // Rough bounding box to limit DB scan
                $deltaLat = $radiusMeters / 111000.0; // meters to degrees
                $deltaLng = $radiusMeters / (111000.0 * cos(deg2rad($lat)));

                $candidates = Place::whereBetween('latitude', [$lat - $deltaLat, $lat + $deltaLat])
                    ->whereBetween('longitude', [$lng - $deltaLng, $lng + $deltaLng])
                    ->get();

                $closest = null;
                $closestDist = INF;
                foreach ($candidates as $p) {
                    $d = $this->haversineDistanceMeters($lat, $lng, (float) $p->latitude, (float) $p->longitude);
                    if ($d < $closestDist) {
                        $closestDist = $d;
                        $closest = $p;
                    }
                }

                if ($closest && $closestDist <= $radiusMeters) {
                    $validated['place_id'] = $closest->id;
                }
            }
        } catch (\Throwable $e) {
            // Non-fatal: if GameSetting table missing or any error, just skip place matching
        }

        $gpsVerificationResult = null;

        // Handle image upload
        if ($request->hasFile('image')) {
            $file = $request->file('image');

            // Layer 2: EXIF GPS verification
            $gpsService = app(ExifGpsVerificationService::class);
            $gpsVerificationResult = $gpsService->verifyPhotoLocation(
                $file,
                (float) $validated['latitude'],
                (float) $validated['longitude']
            );

            // Store GPS verification results
            $validated['photo_gps_lat'] = $gpsVerificationResult['photo_lat'];
            $validated['photo_gps_lng'] = $gpsVerificationResult['photo_lng'];
            $validated['gps_distance_km'] = $gpsVerificationResult['distance_km'];

            if ($gpsVerificationResult['verified']) {
                $validated['gps_verification_status'] = 'verified';
            } elseif ($gpsVerificationResult['photo_lat'] === null && $gpsVerificationResult['photo_lng'] === null) {
                $validated['gps_verification_status'] = 'no_gps_data';
            } else {
                $validated['gps_verification_status'] = 'mismatched';
            }

            // Store the image
            $path = $file->store('report-images', 'public');
        }

        // If photo has no GPS data or GPS mismatched, still allow submission
        // but flag it for admin review. Moderators can reject based on this.
        $report = Report::create($validated);

        app(AchievementService::class)->checkAndAwardAchievements($request->user());

        if (isset($path)) {
            $report->media()->create([
                'media_url' => $path,
                'type' => 'image',
            ]);
        }

        // Add to moderation queue
        try {
            $priority = match ($report->priority) {
                'critical' => 'high',
                'high' => 'high',
                default => 'medium',
            };
            app(ModeratorService::class)->addToModerationQueue(
                contentType: 'report',
                contentId: $report->id,
                submittedBy: $report->user_id,
                priority: $priority,
            );
        } catch (\Throwable $e) {
            // Non-fatal: queue failure shouldn't block report creation
        }

        // Send push notification for emergency/critical reports within 20km
        if (in_array($report->priority, ['high', 'critical']) && $report->latitude && $report->longitude) {
            try {
                \App\Services\PushNotificationService::notifyNearbyUsers(
                    title: ($report->priority === 'critical' ? '🚨' : '⚠️') . ' ' . $report->title,
                    message: str($report->description)->limit(100),
                    latitude: (float) $report->latitude,
                    longitude: (float) $report->longitude,
                    radiusKm: 20,
                    data: ['type' => 'report', 'id' => $report->id],
                );
            } catch (\Throwable $e) {
                // Non-fatal: push failure shouldn't block report creation
            }
        }

        $responseData = $this->formatReport($report->fresh()->load(['user', 'category', 'media']));
        $responseData['gps_verification'] = $gpsVerificationResult;

        $message = 'Report submitted successfully. It will be reviewed by moderators.';
        if ($gpsVerificationResult && !$gpsVerificationResult['verified']) {
            $message = 'Report submitted. Your photo could not be GPS-verified. It will be manually reviewed.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $responseData,
        ], 201);
    }

    /**
     * Update an existing report (only by owner or admin)
     */
    public function update(Request $request, $id)
    {
        $report = Report::findOrFail($id);
        $user = $request->user();

        // Only owner or admin can update
        if ($report->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this report.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'category_id' => 'sometimes|exists:report_categories,id',
            'priority' => 'nullable|string|in:low,medium,high,critical',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'district' => 'nullable|string|max:100',
            'status' => 'sometimes|string|in:pending,approved,rejected',
            'verified_by' => 'sometimes|exists:users,id',
        ]);

        // Only admin can change status or verification
        if (isset($validated['status']) && !$user->isAdmin()) {
            unset($validated['status']);
        }
        if (isset($validated['verified_by']) && !$user->isAdmin()) {
            unset($validated['verified_by']);
        }

        if (isset($validated['status']) && $validated['status'] === 'approved') {
            $validated['verified_by'] = $user->id;
            $validated['verified_at'] = now();
        }

        $report->update($validated);

        // Notify nearby users when report is approved
        if (isset($validated['status']) && $validated['status'] === 'approved') {
            if ($report->latitude && $report->longitude) {
                try {
                    \App\Services\PushNotificationService::notifyNearbyUsers(
                        title: '⚠️ ' . $report->title,
                        message: str($report->description)->limit(100),
                        latitude: (float) $report->latitude,
                        longitude: (float) $report->longitude,
                        radiusKm: 20,
                        data: ['type' => 'report', 'id' => $report->id],
                    );
                } catch (\Throwable $e) {
                    // Non-fatal: push failure shouldn't block update
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Report updated successfully.',
            'data' => $this->formatReport($report->fresh()->load(['user', 'category'])),
        ]);
    }

    /**
     * Delete a report (only by owner or admin)
     */
    public function destroy(Request $request, $id)
    {
        $report = Report::findOrFail($id);
        $user = $request->user();

        if ($report->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this report.',
            ], 403);
        }

        $report->delete();

        return response()->json([
            'success' => true,
            'message' => 'Report deleted successfully.',
        ]);
    }

    /**
     * Toggle a reaction (like/dislike) on a report
     */
    public function toggleReaction(Request $request, $id)
    {
        $request->validate([
            'reaction_type' => 'required|string|in:helpful,unhelpful',
        ]);

        $report = Report::findOrFail($id);
        $user = $request->user();

        // Check existing reaction before modifying
        $existing = ReportReaction::where('report_id', $report->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing && $existing->reaction_type === $request->reaction_type) {
            // Same reaction - toggle off
            $existing->delete();
            $this->updateReactionCounts($report);
            $report->refresh();
            $message = 'Reaction removed';
            $userReaction = null;
        } elseif ($existing) {
            // Different reaction - update
            $existing->update(['reaction_type' => $request->reaction_type]);
            $this->updateReactionCounts($report);
            $report->refresh();
            $message = 'Reaction changed to ' . $request->reaction_type;
            $userReaction = $request->reaction_type;
        } else {
            // New reaction
            ReportReaction::create([
                'report_id' => $report->id,
                'user_id' => $user->id,
                'reaction_type' => $request->reaction_type,
            ]);
            $this->updateReactionCounts($report);
            $report->refresh();
            $message = 'Reaction added';
            $userReaction = $request->reaction_type;
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'user_reaction' => $userReaction,
            'helpful_count' => (int) $report->helpful_count,
            'unhelpful_count' => (int) $report->unhelpful_count,
        ]);
    }

    /**
     * Remove user's reaction from a report
     */
    public function removeReaction(Request $request, $id)
    {
        $report = Report::findOrFail($id);
        $user = $request->user();

        ReportReaction::where('report_id', $report->id)
            ->where('user_id', $user->id)
            ->delete();

        $this->updateReactionCounts($report);

        return response()->json([
            'success' => true,
            'message' => 'Reaction removed',
        ]);
    }

    /**
     * Add a comment to a report
     */
    public function addComment(Request $request, $id)
    {
        $request->validate([
            'content' => 'required|string|max:1000',
            'parent_comment_id' => 'nullable|exists:report_comments,id',
        ]);

        $report = Report::findOrFail($id);
        $user = $request->user();

        $comment = ReportComment::create([
            'report_id' => $report->id,
            'user_id' => $user->id,
            'content' => $request->content,
            'parent_comment_id' => $request->parent_comment_id,
        ]);

        $report->increment('comments_count');

        app(AchievementService::class)->checkAndAwardAchievements($user);

        return response()->json([
            'success' => true,
            'message' => 'Comment added',
            'data' => [
                'id' => (string) $comment->id,
                'content' => $comment->content,
                'user_name' => $user->name,
                'user_avatar' => $user->avatar,
                'user_id' => (string) $user->id,
                'report_user_id' => (string) $report->user_id,
                'created_at' => $comment->created_at,
                'time_ago' => $comment->created_at->diffForHumans(),
            ],
        ]);
    }

    /**
     * Delete a comment
     */
    public function deleteComment(Request $request, $id, $commentId)
    {
        $report = Report::findOrFail($id);
        $comment = ReportComment::where('report_id', $report->id)
            ->where('id', $commentId)
            ->firstOrFail();

        $user = $request->user();
        if ($comment->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $comment->delete();
        $report->decrement('comments_count');

        return response()->json([
            'success' => true,
            'message' => 'Comment deleted',
        ]);
    }

    /**
     * Update reaction counts (helpful/unhelpful) on the report
     */
    private function updateReactionCounts($report)
    {
        $helpful = ReportReaction::where('report_id', $report->id)
            ->where('reaction_type', 'helpful')
            ->count();
        $unhelpful = ReportReaction::where('report_id', $report->id)
            ->where('reaction_type', 'unhelpful')
            ->count();

        $report->update([
            'helpful_count' => $helpful,
            'unhelpful_count' => $unhelpful,
        ]);
    }

    /**
     * Helper: format a report for API response
     */
    private function formatReport($report, bool $includeComments = false): array
    {
        // Try to detect user for user_reaction field
        $user = request()->user() ?? Auth::guard('sanctum')->user();

        $mediaUrls = [];
        if ($report->relationLoaded('media')) {
            $mediaUrls = $report->media->filter(fn($item) => $item->type === 'image')
                ->map(fn($item) => asset('storage/'.$item->media_url))
                ->values()
                ->all();
        }

        // Get reaction counts
        $helpfulCount = $report->helpful_count;
        $unhelpfulCount = $report->unhelpful_count ?? 0;

        // If relations loaded, count from relation
        if ($report->relationLoaded('reactions')) {
            $helpfulCount = $report->reactions->where('reaction_type', 'helpful')->count();
            $unhelpfulCount = $report->reactions->where('reaction_type', 'unhelpful')->count();
        }

        $data = [
            'id' => (string) $report->id,
            'uuid' => $report->uuid,
            'title' => $report->title,
            'description' => $report->description,
            'category_id' => $report->category_id,
            'category_name' => $report->category?->name ?? 'Unknown',
            'category_icon' => $report->category?->icon,
            'priority' => $report->priority,
            'status' => $report->status,
            'latitude' => (float) $report->latitude,
            'longitude' => (float) $report->longitude,
            'district' => $report->district,
            'helpful_count' => $helpfulCount,
            'unhelpful_count' => $unhelpfulCount,
            'comments_count' => (int) $report->comments_count,
            'reporter_name' => $report->user?->name ?? 'Anonymous',
            'reporter_avatar' => $report->user?->avatar,
            'reporter_id' => (string) $report->user_id,
            'image_urls' => $mediaUrls,
            'image_url' => count($mediaUrls) ? $mediaUrls[0] : null,
            'created_at' => $report->created_at,
            'updated_at' => $report->updated_at,
            'time_ago' => $report->created_at?->diffForHumans(),
            'user_reaction' => null, // Will be filled if user is authenticated
        ];

        // Add user's reaction if authenticated
        if ($user && $report->relationLoaded('reactions')) {
            $userReaction = $report->reactions
                ->where('user_id', $user->id)
                ->first();
            if ($userReaction) {
                $data['user_reaction'] = $userReaction->reaction_type;
            }
        }

        if ($includeComments) {
            // Build parent/child comment tree
            $allComments = $report->comments?->map(fn($comment) => [
                'id' => (string) $comment->id,
                'content' => $comment->content,
                'user_name' => $comment->user?->name ?? 'Anonymous',
                'user_avatar' => $comment->user?->avatar,
                'user_id' => (string) $comment->user_id,
                'report_user_id' => (string) $report->user_id,
                'parent_comment_id' => $comment->parent_comment_id ? (string) $comment->parent_comment_id : null,
                'reply_to_name' => $comment->parentComment?->user?->name,
                'created_at' => $comment->created_at,
                'time_ago' => $comment->created_at?->diffForHumans(),
                'replies' => [],
            ]) ?? [];

            $commentMap = [];

            foreach ($allComments as &$c) {
                $commentMap[$c['id']] = &$c;
            }
            unset($c);

            $commentTree = [];
            foreach (array_keys($commentMap) as $id) {
                $parentId = $commentMap[$id]['parent_comment_id'];
                if ($parentId && isset($commentMap[$parentId])) {
                    $commentMap[$parentId]['replies'][] = &$commentMap[$id];
                } else {
                    $commentTree[] = &$commentMap[$id];
                }
            }
            unset($id, $parentId);

            $data['comments'] = $commentTree;
        }

        return $data;
    }

    /**
     * Haversine distance between two lat/lng points in meters
     */
    public function assistantChat(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'context.lat' => 'nullable|numeric',
            'context.lng' => 'nullable|numeric',
        ]);

        $message = $request->input('message');
        $lat = $request->input('context.lat');
        $lng = $request->input('context.lng');

        // Return a helpful response with nearby info context
        $response = "I understand you're asking about: \"$message\". ";

        if ($lat && $lng) {
            $nearbyPlaces = Place::select('name', 'category_id', 'latitude', 'longitude')
                ->selectRaw(
                    "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance",
                    [$lat, $lng, $lat]
                )
                ->having('distance', '<=', 5)
                ->orderBy('distance')
                ->limit(5)
                ->get();

            if ($nearbyPlaces->isNotEmpty()) {
                $names = $nearbyPlaces->pluck('name')->implode(', ');
                $response .= "Nearby places include: $names. ";
            }
        }

        // Log the chat for future AI training
        if ($request->user()) {
            \App\Models\AssistantChat::create([
                'user_id' => $request->user()->id,
                'message' => $message,
                'response' => $response,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'response' => $response,
                'context' => [
                    'nearby_places' => $nearbyPlaces ?? [],
                ],
            ],
        ]);
    }

    private function haversineDistanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return GeoHelper::haversineMeters($lat1, $lng1, $lat2, $lng2);
    }
}