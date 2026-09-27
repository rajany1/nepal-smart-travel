<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Report;
use App\Models\ReportCategorie;
use App\Models\ReportComment;
use App\Models\ReportConfirmation;
use App\Models\ReportReaction;
use App\Models\Place;
use App\Models\GameSetting;
use App\Jobs\AnalyzeReport;
use App\Jobs\TranslateContent;
use App\Models\AiAgent;
use App\Models\AiAgentTask;
use App\Services\AchievementService;
use App\Services\Ai\AgentOrchestrator;
use App\Services\ExifGpsVerificationService;
use App\Services\ImageValidationService;
use App\Services\ModeratorService;
use App\Services\ReportAutoClassifyService;
use App\Services\TranslationService;
use App\Helpers\GeoHelper;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ReportController extends Controller
{
    /** Daily AI assistant chat quota per user (Redis-backed, resets at NPT midnight). */
    private const ASSISTANT_DAILY_LIMIT = 5;

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
                    'name' => 'description',
                    'label' => 'Description',
                    'type' => 'textarea',
                    'required' => true,
                    'validation' => 'required|string|max:10000|min:10',
                    'placeholder' => 'Describe what happened — the more detail, the better. The system will auto-detect the category, priority and title for you.',
                    'icon' => 'description',
                    'order' => 1,
                    'rows' => 5,
                ],
            ],
            'submit_button_text' => 'Submit Report',
            'notice' => 'Only description + a live photo are needed. Category, priority and title are decided automatically from your description.',
        ];

        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }

    /**
     * Get all report categories organized by group with options and form fields.
     * Used for the "More Categories" explorer screen.
     */
    public function categoriesDetailed(Request $request)
    {
        $groups = ReportCategoryGroup::where('is_active', true)
            ->orderBy('sort_order')
            ->with([
                'activeCategories' => function ($q) {
                    $q->with([
                        'options' => function ($oq) {
                            $oq->orderBy('sort_order');
                        },
                        'fields' => function ($fq) {
                            $fq->orderBy('sort_order');
                        },
                    ])->orderBy('sort_order');
                },
            ])
            ->get();

        $data = $groups->map(function ($group) {
            return [
                'id' => $group->id,
                'name' => $group->name,
                'slug' => $group->slug,
                'name_ne' => $group->name_ne,
                'description' => $group->description,
                'description_ne' => $group->description_ne,
                'icon' => $group->icon,
                'icon_type' => $group->icon_type,
                'icon_color' => $group->icon_color,
                'icon_background' => $group->icon_background,
                'is_emergency_group' => $group->is_emergency_group,
                'categories' => $group->activeCategories->map(function ($cat) {
                    return [
                        'id' => $cat->id,
                        'name' => $cat->name,
                        'slug' => $cat->slug,
                        'name_ne' => $cat->name_ne,
                        'description' => $cat->description,
                        'description_ne' => $cat->description_ne,
                        'icon' => $cat->icon,
                        'icon_type' => $cat->icon_type,
                        'icon_color' => $cat->icon_color,
                        'icon_background' => $cat->icon_background,
                        'is_featured' => $cat->is_featured,
                        'is_emergency' => $cat->is_emergency,
                        'usage_count' => $cat->usage_count,
                        'options' => $cat->options->map(function ($opt) {
                            return [
                                'id' => $opt->id,
                                'name' => $opt->name,
                                'slug' => $opt->slug,
                                'name_ne' => $opt->name_ne,
                                'description' => $opt->description,
                                'description_ne' => $opt->description_ne,
                                'icon' => $opt->icon,
                                'icon_type' => $opt->icon_type,
                                'requires_photo' => $opt->requires_photo,
                                'requires_location' => $opt->requires_location,
                            ];
                        }),
                        'fields' => $cat->fields->map(function ($field) {
                            return [
                                'name' => $field->name,
                                'label' => $field->label,
                                'label_ne' => $field->label_ne,
                                'placeholder' => $field->placeholder,
                                'placeholder_ne' => $field->placeholder_ne,
                                'type' => $field->type,
                                'required' => $field->required,
                                'sort_order' => $field->sort_order,
                                'options' => $field->getOptionsArray(),
                                'validation' => $field->validation,
                                'help_text' => $field->help_text,
                                'help_text_ne' => $field->help_text_ne,
                                'show_in_preview' => $field->show_in_preview,
                            ];
                        }),
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get featured / most-used categories for the initial report bottom sheet.
     * Priority: 1) Admin featured, 2) Safety-critical, 3) Usage count, 4) sort_order
     */
    public function featuredCategories(Request $request)
    {
        $limit = (int) $request->input('limit', 6);

        // Priority 1: Admin featured categories
        $featured = ReportCategorie::where('is_active', true)
            ->where('is_featured', true)
            ->with(['options', 'fields'])
            ->orderBy('sort_order')
            ->limit($limit)
            ->get();

        // If we don't have enough featured, fill with safety-critical (emergency)
        if ($featured->count() < $limit) {
            $emergency = ReportCategorie::where('is_active', true)
                ->where('is_emergency', true)
                ->whereNotIn('id', $featured->pluck('id'))
                ->with(['options', 'fields'])
                ->orderBy('sort_order')
                ->limit($limit - $featured->count())
                ->get();
            $featured = $featured->concat($emergency);
        }

        // If still not enough, fill with usage-based ranking
        if ($featured->count() < $limit) {
            $popular = ReportCategorie::where('is_active', true)
                ->whereNotIn('id', $featured->pluck('id'))
                ->with(['options', 'fields'])
                ->orderByDesc('usage_count')
                ->orderBy('sort_order')
                ->limit($limit - $featured->count())
                ->get();
            $featured = $featured->concat($popular);
        }

        $data = $featured->map(function ($cat) {
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'name_ne' => $cat->name_ne,
                'description' => $cat->description,
                'description_ne' => $cat->description_ne,
                'icon' => $cat->icon,
                'icon_type' => $cat->icon_type,
                'icon_color' => $cat->icon_color,
                'icon_background' => $cat->icon_background,
                'is_featured' => $cat->is_featured,
                'is_emergency' => $cat->is_emergency,
                'usage_count' => $cat->usage_count,
                'options' => $cat->options->map(function ($opt) {
                    return [
                        'id' => $opt->id,
                        'name' => $opt->name,
                        'slug' => $opt->slug,
                        'name_ne' => $opt->name_ne,
                        'description' => $opt->description,
                        'description_ne' => $opt->description_ne,
                        'icon' => $opt->icon,
                        'icon_type' => $opt->icon_type,
                        'requires_photo' => $opt->requires_photo,
                        'requires_location' => $opt->requires_location,
                    ];
                }),
                'fields' => $cat->fields->map(function ($field) {
                    return [
                        'name' => $field->name,
                        'label' => $field->label,
                        'label_ne' => $field->label_ne,
                        'placeholder' => $field->placeholder,
                        'placeholder_ne' => $field->placeholder_ne,
                        'type' => $field->type,
                        'required' => $field->required,
                        'sort_order' => $field->sort_order,
                        'options' => $field->getOptionsArray(),
                        'validation' => $field->validation,
                        'help_text' => $field->help_text,
                        'help_text_ne' => $field->help_text_ne,
                        'show_in_preview' => $field->show_in_preview,
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get a single category with full configuration for form rendering.
     */
    public function categoryFormConfig(Request $request, $id)
    {
        $category = ReportCategorie::with(['options', 'fields', 'group'])->findOrFail($id);

        // Increment usage count for ranking
        $category->incrementUsage();

        $data = [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
            'name_ne' => $category->name_ne,
            'description' => $category->description,
            'description_ne' => $category->description_ne,
            'icon' => $category->icon,
            'icon_type' => $category->icon_type,
            'icon_color' => $category->icon_color,
            'icon_background' => $category->icon_background,
            'is_featured' => $category->is_featured,
            'is_emergency' => $category->is_emergency,
            'group' => $category->group ? [
                'id' => $category->group->id,
                'name' => $category->group->name,
                'slug' => $category->group->slug,
                'name_ne' => $category->group->name_ne,
                'icon' => $category->group->icon,
            ] : null,
            'options' => $category->options->map(function ($opt) {
                return [
                    'id' => $opt->id,
                    'name' => $opt->name,
                    'slug' => $opt->slug,
                    'name_ne' => $opt->name_ne,
                    'description' => $opt->description,
                    'description_ne' => $opt->description_ne,
                    'icon' => $opt->icon,
                    'icon_type' => $opt->icon_type,
                    'requires_photo' => $opt->requires_photo,
                    'requires_location' => $opt->requires_location,
                ];
            }),
            'fields' => $category->fields->map(function ($field) {
                return [
                    'name' => $field->name,
                    'label' => $field->label,
                    'label_ne' => $field->label_ne,
                    'placeholder' => $field->placeholder,
                    'placeholder_ne' => $field->placeholder_ne,
                    'type' => $field->type,
                    'required' => $field->required,
                    'sort_order' => $field->sort_order,
                    'options' => $field->getOptionsArray(),
                    'validation' => $field->validation,
                    'help_text' => $field->help_text,
                    'help_text_ne' => $field->help_text_ne,
                    'show_in_preview' => $field->show_in_preview,
                ];
            }),
            'submit_button_text' => 'Submit Report',
            'notice' => 'Fill in the details and submit your report.',
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Search categories by name, description, group.
     */
    public function searchCategories(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:1|max:100',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $query = $request->input('q');
        $limit = (int) $request->input('limit', 20);

        $categories = ReportCategorie::where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('name_ne', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->orWhere('description_ne', 'like', "%{$query}%")
                    ->orWhere('slug', 'like', "%{$query}%")
                    ->orWhereHas('group', function ($gq) use ($query) {
                        $gq->where('name', 'like', "%{$query}%")
                            ->orWhere('name_ne', 'like', "%{$query}%")
                            ->orWhere('slug', 'like', "%{$query}%");
                    });
            })
            ->with(['options', 'fields', 'group'])
            ->orderBy('is_featured', 'desc')
            ->orderBy('is_emergency', 'desc')
            ->orderByDesc('usage_count')
            ->orderBy('sort_order')
            ->limit($limit)
            ->get();

        $data = $categories->map(function ($cat) {
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'slug' => $cat->slug,
                'name_ne' => $cat->name_ne,
                'description' => $cat->description,
                'description_ne' => $cat->description_ne,
                'icon' => $cat->icon,
                'icon_type' => $cat->icon_type,
                'icon_color' => $cat->icon_color,
                'icon_background' => $cat->icon_background,
                'is_featured' => $cat->is_featured,
                'is_emergency' => $cat->is_emergency,
                'usage_count' => $cat->usage_count,
                'group' => $cat->group ? [
                    'id' => $cat->group->id,
                    'name' => $cat->group->name,
                    'slug' => $cat->group->slug,
                    'name_ne' => $cat->group->name_ne,
                ] : null,
                'options' => $cat->options->map(function ($opt) {
                    return [
                        'id' => $opt->id,
                        'name' => $opt->name,
                        'slug' => $opt->slug,
                        'name_ne' => $opt->name_ne,
                    ];
                }),
                'fields' => $cat->fields->map(function ($field) {
                    return [
                        'name' => $field->name,
                        'label' => $field->label,
                        'label_ne' => $field->label_ne,
                        'type' => $field->type,
                        'required' => $field->required,
                        'options' => $field->getOptionsArray(),
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * List reports with filters
     */
    public function index(Request $request)
    {
        // Try to detect authenticated user from bearer token even on public route
        $user = $request->user();
        if (!$user) {
            try {
                $user = Auth::guard('sanctum')->user();
            } catch (\Throwable $e) {
                $user = null;
            }
        }

        $query = Report::with(['user', 'category', 'media', 'reactions']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            // Default: only show approved reports for public,
            // but include own reports regardless of status
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

        // Filter by search query
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('district', 'like', "%{$search}%");
            });
        }

        // Filter by priority
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        // Filter by emergency (high/critical priority)
        if ($request->filled('is_emergency')) {
            $isEmergency = filter_var($request->is_emergency, FILTER_VALIDATE_BOOL);
            if ($isEmergency) {
                $query->whereIn('priority', ['high', 'critical']);
            } else {
                $query->whereNotIn('priority', ['high', 'critical']);
            }
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
            try {
                $user = Auth::guard('sanctum')->user();
            } catch (\Throwable $e) {
                // Invalid/expired token - treat as unauthenticated
                $user = null;
            }
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

        // BE-22: only approved reports are public by ID; owner and admins/moderators may see any
        $user = $request->user();
        $isOwner = $user && (string) $user->id === (string) $report->user_id;
        $isStaff = $user && ($user->isAdmin() || $user->isModerator());
        if ($report->status !== 'approved' && !$isOwner && !$isStaff) {
            return response()->json([
                'success' => false,
                'message' => 'Report not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatReport($report, true),
        ]);
    }

    /**
     * Submit a new report
     * 
     * Server-side security controls (bypass-proof):
     * 1. Image validation: MIME, dimensions, format integrity (finfo + getimagesize)
     * 2. Exact duplicate detection: SHA-256 hash blocks re-uploads
     * 3. Fingerprint near-duplicate: soft signal, sets provenance='suspicious'
     * 4. EXIF GPS verification: photo GPS vs report location (500m tolerance)
     * 5. Provenance tracking: server-assigned, never trusted from client
     * 
     * Camera provenance CANNOT be cryptographically proven after upload.
     * The is_live_capture field is client metadata for analytics only —
     * a cracked APK can send is_live_capture=true with any image.
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

        // === Auto-classification ===
        // Mobile submits only a description + live photo. The system infers
        // the title, category and priority from the description when they are
        // not provided (falling back to them if present).
        $auto = app(ReportAutoClassifyService::class)->classify(
            (string) $request->input('description', ''),
        );
        $request->merge([
            'title' => trim((string) $request->input('title')) !== ''
                ? $request->input('title')
                : $auto['title'],
            'category_id' => $request->filled('category_id')
                ? $request->input('category_id')
                : $auto['category_id'],
            'priority' => $request->filled('priority')
                ? $request->input('priority')
                : $auto['priority'],
        ]);

        $validated = $request->validate([
            'description' => 'required|string|max:10000|min:10',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'district' => 'nullable|string|max:100',
            'image' => 'required|image|max:5120', // 5MB max, now REQUIRED
            'is_live_capture' => 'required|boolean', // Client metadata — NOT a trust signal
            'photo_captured_at' => 'nullable|date',
            'capture_latitude' => 'nullable|numeric|between:-90,90',
            'capture_longitude' => 'nullable|numeric|between:-180,180',
        ]);

        // Layer 1: Server-side image validation (NEVER trust client metadata)
        // The is_live_capture flag from the client is a WEAK signal — a cracked APK
        // can send is_live_capture=true with any image. Instead, we validate the
        // image itself server-side and check for duplicates immediately.
        if (!$request->hasFile('image')) {
            return response()->json([
                'success' => false,
                'message' => 'No image provided. A live photo is required.',
            ], 422);
        }

        // Layer 2: Fraud detection
        $fraud = app(\App\Services\FraudDetectionService::class);
        $fraudResult = $fraud->checkReport($request, $request->user());
        if ($fraudResult['blocked']) {
            return response()->json([
                'success' => false,
                'message' => 'Report blocked due to suspicious activity.',
                'reasons' => $fraudResult['reasons'],
            ], 422);
        }

        $validated['user_id'] = $request->user()->id;
        $validated['status'] = 'pending';
        // Auto-inferred fields (merged into the request before validate()).
        $validated['title'] = (string) $request->input('title');
        $validated['category_id'] = (int) $request->input('category_id');
        $validated['priority'] = (string) $request->input('priority');

        // Provenance: NEVER trust is_live_capture from the client. A cracked APK
        // can send is_live_capture=true with any gallery image. Instead, we assign
        // provenance based on server-side signals.
        // - 'unverified_client': default for all user-submitted images
        // - 'suspicious': when soft signals (no EXIF, screenshot dims, etc.) are present
        // - 'system': for BIPAD/system reports (set in BipadSyncService)
        $validated['provenance'] = 'unverified_client';

        // Only include `is_live_capture` if the column exists in DB (some setups may not have run migrations)
        if (Schema::hasColumn('reports', 'is_live_capture')) {
            // Do NOT trust the client value — store the coerced value but it must
            // never be used as a trust signal. We keep it for analytics only.
            $validated['is_live_capture'] = $coercedIsLive;
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
        $imageMetadata = [];

        // Handle image upload
        if ($request->hasFile('image')) {
            $file = $request->file('image');

            // === SERVER-SIDE IMAGE VALIDATION ===
            // This is the real security boundary. A modified APK cannot bypass these checks.
            $imageValidator = app(ImageValidationService::class);
            $validation = $imageValidator->validate($file);

            if (!$validation['valid']) {
                // Log the rejection for security audit
                $imageValidator->logSuspicious(
                    userId: $request->user()->id,
                    reportId: null,
                    reason: 'image_validation_failed',
                    metadata: array_merge($validation['metadata'], ['errors' => $validation['errors']]),
                    ip: $request->ip(),
                    userAgent: $request->userAgent(),
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Image validation failed.',
                    'errors' => $validation['errors'],
                ], 422);
            }

            // Block repeat offenders (configurable threshold, default 10 per 24h)
            if ($imageValidator->isRepeatOffender($request->user()->id)) {
                $imageValidator->logSuspicious(
                    userId: $request->user()->id,
                    reportId: null,
                    reason: 'repeat_offender_blocked',
                    metadata: $validation['metadata'],
                    ip: $request->ip(),
                    userAgent: $request->userAgent(),
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Report submission temporarily restricted due to repeated suspicious activity.',
                ], 429);
            }

            // Log warnings (suspicious patterns that don't block submission)
            // and escalate provenance to 'suspicious' so AI never auto-approves.
            if (!empty($validation['warnings'])) {
                $validated['provenance'] = 'suspicious';
                $imageValidator->logSuspicious(
                    userId: $request->user()->id,
                    reportId: null,
                    reason: 'image_warnings',
                    metadata: array_merge($validation['metadata'], ['warnings' => $validation['warnings']]),
                    ip: $request->ip(),
                    userAgent: $request->userAgent(),
                );
            }

            $imageMetadata = $validation['metadata'];

            // === SYNCHRONOUS DUPLICATE DETECTION ===
            // Exact SHA-256 match blocks submission (hard reject).
            // Fingerprint near-duplicate is a soft signal (logged, does NOT block).
            $duplicateCheck = $imageValidator->checkDuplicates($file);
            if ($duplicateCheck['duplicate']) {
                // Hard reject: exact duplicate
                $imageValidator->logSuspicious(
                    userId: $request->user()->id,
                    reportId: null,
                    reason: 'duplicate_image_blocked',
                    metadata: array_merge($validation['metadata'], [
                        'match_type'     => $duplicateCheck['match_type'],
                        'matched_report' => $duplicateCheck['matched_report'],
                        'content_hash'   => $duplicateCheck['content_hash'],
                    ]),
                    ip: $request->ip(),
                    userAgent: $request->userAgent(),
                );

                return response()->json([
                    'success'  => false,
                    'message'  => 'This image appears to have been used in a recent report. Please submit a new, original photo.',
                    'match_type' => $duplicateCheck['match_type'],
                ], 422);
            }

            // Soft signal: fingerprint near-duplicate (logged, does NOT block)
            if (($duplicateCheck['match_type'] ?? null) === 'fingerprint_near_duplicate') {
                $validated['provenance'] = 'suspicious';
                $imageValidator->logSuspicious(
                    userId: $request->user()->id,
                    reportId: null,
                    reason: 'fingerprint_near_duplicate',
                    metadata: array_merge($validation['metadata'], [
                        'match_type'     => $duplicateCheck['match_type'],
                        'matched_report' => $duplicateCheck['matched_report'],
                        'warning'        => $duplicateCheck['warning'] ?? null,
                    ]),
                    ip: $request->ip(),
                    userAgent: $request->userAgent(),
                );
            }

            // Layer 2: EXIF GPS verification
            $gpsService = app(ExifGpsVerificationService::class);
            $gpsVerificationResult = $gpsService->verifyPhotoLocation(
                $file,
                (float) $validated['latitude'],
                (float) $validated['longitude']
            );

            // Fallback: if the image has no EXIF GPS (image_picker strips it),
            // verify against the capture-time coordinates sent by the app.
            if ($gpsVerificationResult['photo_lat'] === null
                && $gpsVerificationResult['photo_lng'] === null
                && isset($validated['capture_latitude'])
                && isset($validated['capture_longitude'])) {
                $gpsVerificationResult = $gpsService->verifyCaptureCoordinates(
                    (float) $validated['capture_latitude'],
                    (float) $validated['capture_longitude'],
                    (float) $validated['latitude'],
                    (float) $validated['longitude']
                );
            }

            // Store GPS verification results
            $validated['photo_gps_lat'] = $gpsVerificationResult['photo_lat'];
            $validated['photo_gps_lng'] = $gpsVerificationResult['photo_lng'];
            $validated['gps_distance_km'] = $gpsVerificationResult['distance_km'];

            if ($gpsVerificationResult['verified']) {
                $validated['gps_verification_status'] = 'verified';
            } elseif ($gpsVerificationResult['photo_lat'] === null && $gpsVerificationResult['photo_lng'] === null) {
                $validated['gps_verification_status'] = 'no_gps_data';
                // No EXIF GPS + no capture GPS = weak provenance
                if ($validated['provenance'] === 'unverified_client') {
                    $validated['provenance'] = 'suspicious';
                }
            } else {
                $validated['gps_verification_status'] = 'mismatched';
                // GPS mismatch = suspicious
                $validated['provenance'] = 'suspicious';
            }

            // Store the image
            $path = $file->store('report-images', 'public');
        }

        // If photo has no GPS data or GPS mismatched, still allow submission
        // but flag it for admin review. Moderators can reject based on this.
        $validated['ip_address'] = $request->ip();
        $validated['user_agent'] = $request->userAgent();
        $report = Report::create($validated);

        // Review AI agent - real-time safety guard
        $safety = app(\App\Services\ContentSafetyService::class);
        $guardTitle = $safety->guard($request->user(), (string) $validated['title'], 'report', $report->id, 'title', 'realtime');
        $guardDesc = $safety->guard($request->user(), (string) $validated['description'], 'report', $report->id, 'description', 'realtime');
        if ($guardTitle['action'] === 'censored' || $guardDesc['action'] === 'censored') {
            $report->update(['title' => $guardTitle['censored'] ?? $guardTitle['text'], 'description' => $guardDesc['censored'] ?? $guardDesc['text']]);
        }
        $safetyPayload = $safety->payload([$guardTitle, $guardDesc]);

        // AnalyzeReport is dispatched AFTER the media row and the moderation-
        // queue entry are created (see below): the decision engine reads
        // report_media for image evidence and updates the moderation-queue
        // row to the final action. Dispatching earlier loses both races —
        // guaranteed under QUEUE_CONNECTION=sync, timing-dependent with the
        // database queue.
        dispatch(new TranslateContent('report', $report->id, 'title'));
        dispatch(new TranslateContent('report', $report->id, 'description'));

        app(AchievementService::class)->checkAndAwardAchievements($request->user());

        if (isset($path)) {
            $mediaHash = $imageMetadata['content_hash'] ?? hash_file('sha256', \Illuminate\Support\Facades\Storage::disk('public')->path($path));
            $report->media()->create([
                'media_url' => $path,
                'type' => 'image',
                'media_hash' => $mediaHash,
                'fingerprint_hash' => $imageMetadata['fingerprint_hash'] ?? null,
            ]);

            // Log soft signals (for audit trail) and update provenance
            if (!empty($imageMetadata['no_exif_signal']) || !empty($imageMetadata['software_stamp_signal'])
                || !empty($imageMetadata['is_animated_webp'])) {
                if ($validated['provenance'] === 'unverified_client') {
                    $validated['provenance'] = 'suspicious';
                }
                app(ImageValidationService::class)->logSuspicious(
                    userId: $request->user()->id,
                    reportId: $report->id,
                    reason: 'soft_signal_detected',
                    metadata: $imageMetadata,
                    ip: $request->ip(),
                    userAgent: $request->userAgent(),
                );
            }
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

        // All submission artifacts now exist (report row, media row,
        // moderation-queue entry) — safe to start the automated decision.
        dispatch(new AnalyzeReport($report->id));

        // Push notifications are intentionally NOT sent at submission time:
        // users only get notified once moderators/AI approve the report
        // (see AlertPublisherService).

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
            'safety' => $safetyPayload,
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

        $wasApproved = $report->status === 'approved';
        $report->update($validated);

        // Revoke approval XP when an approved report is rejected/unapproved via API
        if ($wasApproved && isset($validated['status']) && $validated['status'] !== 'approved') {
            app(AchievementService::class)->revokeReportApprovalXp($report);
        }

        // Publish proximity alert + notify nearby users on the pending ->
        // approved transition only (queued - no sync block).
        if (isset($validated['status'])
            && $validated['status'] === 'approved'
            && !$wasApproved) {
            app(\App\Services\AlertPublisherService::class)->publishFromReport($report);
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

        // Delete media files from storage before deleting the report
        $mediaFiles = $report->media;
        foreach ($mediaFiles as $media) {
            if ($media->media_url && \Illuminate\Support\Facades\Storage::disk('report-images')->exists($media->media_url)) {
                \Illuminate\Support\Facades\Storage::disk('report-images')->delete($media->media_url);
            }
        }

        // Delete related data
        $report->comments()->delete();
        $report->reactions()->delete();
        $report->confirmations()->delete();

        $report->delete();
        \App\Support\LiveFeed::bump('reports', $id);

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

        // Review AI agent - real-time safety guard
        $safety = app(\App\Services\ContentSafetyService::class);
        $guard = $safety->guard($user, (string) $request->content, 'report_comment', $comment->id, 'content', 'realtime');
        if ($guard['action'] === 'censored') {
            $comment->update(['content' => $guard['censored'] ?? $guard['text']]);
        }
        $safetyPayload = $safety->payload([$guard]);

        $report->increment('comments_count');

        app(AchievementService::class)->checkAndAwardAchievements($user);

        return response()->json([
            'success' => true,
            'message' => 'Comment added',
            'data' => [
                'id' => (string) $comment->id,
                'content' => $comment->content,
                'user_name' => $user->name,
                'user_avatar' => ($a = $user->avatar) ? (str_starts_with($a, 'http') ? $a : asset('storage/' . $a)) : null,
                'user_id' => (string) $user->id,
                'report_user_id' => (string) $report->user_id,
                'created_at' => $comment->created_at,
                'time_ago' => $comment->created_at->diffForHumans(),
            ],
            'safety' => $safetyPayload,
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
        \App\Support\LiveFeed::bump('report_comments', $commentId);
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
        $user = request()->user();
        if (!$user) {
            try {
                $user = Auth::guard('sanctum')->user();
            } catch (\Throwable $e) {
                $user = null;
            }
        }

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
            'reporter_avatar' => ($a = $report->user?->avatar) ? (str_starts_with($a, 'http') ? $a : asset('storage/' . $a)) : null,
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
                'user_avatar' => ($a = $comment->user?->avatar) ? (str_starts_with($a, 'http') ? $a : asset('storage/' . $a)) : null,
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

        $data = TranslationService::attachToItems([$data], 'report')[0];

        return $data;
    }

    /**
     * Redis key for the user's daily assistant usage (NPT calendar day).
     */
    private function assistantDailyKey(int $userId): string
    {
        return 'assistant:daily:' . $userId . ':' . now()->format('Ymd');
    }

    private function assistantResetAt(): \Carbon\CarbonInterface
    {
        return now()->startOfDay()->addDay();
    }

    private function assistantQuotaPayload(int $used): array
    {
        return [
            'limit' => self::ASSISTANT_DAILY_LIMIT,
            'used' => $used,
            'remaining' => max(0, self::ASSISTANT_DAILY_LIMIT - $used),
            'reset_at' => $this->assistantResetAt()->toISOString(),
        ];
    }

    /**
     * Current daily AI chat quota for the authenticated user.
     */
    public function assistantQuota(Request $request)
    {
        $user = $request->user();
        $key = $this->assistantDailyKey($user->id);
        $used = (int) Cache::store('redis')->get($key, 0);

        return response()->json([
            'success' => true,
            'data' => $this->assistantQuotaPayload($used),
        ]);
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

        $user = $request->user();

        // Atomic daily counter — increment first so concurrent requests
        // cannot slip past the limit; refund when the AI call fails.
        $store = Cache::store('redis');
        $key = $this->assistantDailyKey($user->id);

        if (!$store->has($key)) {
            $ttl = max(60, now()->secondsUntilEndOfDay() + 1);
            $store->put($key, 0, $ttl);
        }

        $used = (int) $store->increment($key);

        if ($used > self::ASSISTANT_DAILY_LIMIT) {
            return response()->json([
                'success' => false,
                'message' => 'Daily chat limit reached. Your quota resets at midnight (NPT).',
                'data' => $this->assistantQuotaPayload($used),
            ], 429);
        }

        try {
            return $this->runAssistantChat($request, $user);
        } catch (\Throwable $e) {
            $store->decrement($key);
            throw $e;
        }
    }

    private function runAssistantChat(Request $request, $user)
    {
        $agent = AiAgent::where('agent_type', 'customer_support')
            ->where('status', '!=', 'paused')
            ->first();

        if (!$agent) {
            return response()->json([
                'success' => false,
                'data' => ['reply' => 'Customer Support AI is currently unavailable.'],
            ], 503);
        }

        $task = AiAgentTask::create([
            'ai_agent_id' => $agent->id,
            'type' => 'chat',
            'status' => 'pending',
            'input_data' => [
                'action' => 'chat',
                'message' => $request->input('message'),
                'lat' => $request->input('context.lat'),
                'lng' => $request->input('context.lng'),
                'user_id' => $user?->id,
            ],
        ]);

        $orchestrator = app(AgentOrchestrator::class);
        $result = $orchestrator->executeTask($task);

        $task->refresh();

        if ($task->status !== 'completed') {
            // Failed replies do not consume the user's daily quota.
            Cache::store('redis')->decrement($this->assistantDailyKey($user->id));
        }

        $output = $task->output_data;

        return response()->json([
            'success' => $task->status === 'completed',
            'data' => [
                'reply' => $output['reply'] ?? ($task->error_message ?? 'Error processing request'),
                'data' => [
                    'live_nearby' => $output['nearby'] ?? [],
                    'live_alerts' => $output['alerts'] ?? [],
                ],
                'actions' => $output['actions'] ?? [],
                'screen' => $output['screen'] ?? null,
                'deep_link' => $output['deep_link'] ?? null,
                'quota' => $this->assistantQuotaPayload((int) Cache::store('redis')->get($this->assistantDailyKey($user->id), 0)),
            ],
        ]);
    }

    private function haversineDistanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return GeoHelper::haversineMeters($lat1, $lng1, $lat2, $lng2);
    }

    public function confirm(Request $request, string $id)
    {
        $validated = $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'note' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $report = Report::findOrFail($id);

        if ($report->user_id === $user->id) {
            return response()->json(['success' => false, 'message' => 'Cannot confirm your own report'], 422);
        }

        $existing = ReportConfirmation::where('report_id', $report->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            return response()->json(['success' => false, 'message' => 'Already confirmed'], 422);
        }

        ReportConfirmation::create([
            'report_id' => $report->id,
            'user_id' => $user->id,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);

        $report->increment('confirmed_by_count');
        $report->update(['last_confirmed_at' => now()]);
        $report->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Report confirmed',
            'data' => [
                'confirmed_by_count' => $report->confirmed_by_count,
                'confidence_score' => $report->confidence_score,
            ],
        ]);
    }

    public function confirmers(Request $request, string $id)
    {
        $report = Report::findOrFail($id);
        $confirmers = $report->confirmations()
            ->with('user:id,name,avatar')
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'user_name' => $c->user->name ?? 'Anonymous',
                'user_avatar' => ($a = ($c->user->avatar ?? null)) ? (str_starts_with($a, 'http') ? $a : asset('storage/' . $a)) : null,
                'note' => $c->note,
                'created_at' => $c->created_at,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'confirmers' => $confirmers,
                'total_count' => $report->confirmed_by_count,
                'confidence_score' => $report->confidence_score,
            ],
        ]);
    }
}