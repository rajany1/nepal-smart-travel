<?php

namespace App\Services\Ai;

use App\Exceptions\AiRateLimitException;
use App\Models\GameSetting;
use App\Models\ModerationQueue;
use App\Models\Report;
use App\Models\ReportMedia;
use App\Models\XpTransaction;
use App\Services\AchievementService;
use App\Services\LocationIntegrityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ReportAnalysisService
{
    private const MAX_IMAGES = 3;
    private const MAX_IMAGE_BYTES = 4 * 1024 * 1024;
    private const IMAGE_VIOLATION_CONFIDENCE = 0.8;
    private const SCREEN_WEAK_SIGNAL = 0.15;

    private const NEPAL_BBOX = ['lat_min' => 26.0, 'lat_max' => 31.0, 'lng_min' => 79.5, 'lng_max' => 89.0];

    protected AiFallbackRouter $textRouter;
    protected AiFallbackRouter $visionRouter;
    protected ImageScreeningService $imageScreening;

    public function __construct(
        ?AiFallbackRouter $textRouter = null,
        ?AiFallbackRouter $visionRouter = null,
        ?ImageScreeningService $imageScreening = null,
    ) {
        $this->textRouter = $textRouter ?? AiFallbackRouter::textChain();
        $this->visionRouter = $visionRouter ?? AiFallbackRouter::visionChain(fn (array $r) => $this->isVisionResultUsable($r));
        $this->imageScreening = $imageScreening ?? new ImageScreeningService;
    }

    public function process(Report $report, bool $force = false): array
    {
        // Human review takes priority: once an admin/moderator has approved or
        // rejected a report (verified_by set / non-pending status), the AI agent
        // must never touch it — manual decisions are final.
        if ($report->verified_by !== null || $report->status !== 'pending') {
            return ['report_id' => $report->id, 'skipped' => true];
        }
        if (!$force && $report->ai_analyzed_at !== null) {
            return ['report_id' => $report->id, 'skipped' => true];
        }

        $analysis = $this->analyze($report);
        $analysis['authenticity_score'] = $this->computeAuthenticityScore($analysis);
        $message = $this->actionMessage($analysis);

        DB::transaction(function () use ($report, $analysis, $message) {
            $action = $analysis['action'] ?? 'approve';
            $now = now();

            $report->update([
                'ai_analysis' => $analysis,
                'ai_analyzed_at' => $now,
                'status' => $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'pending'),
                'verified_at' => $now,
                'moderation_message' => $message,
                'authenticity_score' => $analysis['authenticity_score'],
            ]);

            if (isset($analysis['suggested_priority']) && $analysis['suggested_priority'] !== $report->priority) {
                $report->update(['priority' => $analysis['suggested_priority']]);
            }

            ModerationQueue::where('content_type', 'report')
                ->where('content_id', $report->id)
                ->update([
                    'status' => $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'pending'),
                    'reviewed_at' => $now,
                    'reviewed_by' => null,
                    'rejection_reason' => $action === 'reject' ? $message : null,
                ]);

            if ($action === 'approve') {
                $gpsVerified = ($analysis['location_check']['gps_status'] ?? null) === 'verified';
                if ($gpsVerified) {
                    $this->awardApprovalXp($report);
                }
            } else {
                app(AchievementService::class)->revokeReportApprovalXp($report);
            }
        });

        // AI approval counts as moderation approval: publish proximity alert
        // + nearby push (deduped inside the publisher).
        if (($analysis['action'] ?? 'approve') === 'approve') {
            app(\App\Services\AlertPublisherService::class)->publishFromReport($report->fresh() ?? $report);
        }

        return [
            'report_id' => $report->id,
            'action' => $analysis['action'] ?? 'approve',
            'analysis' => $analysis,
        ];
    }

    /**
     * Re-decide an ALREADY-analyzed pending report using its stored analysis
     * and the CURRENT policy — no API call. Fixes reports stuck in pending
     * under the old GPS gate (no_gps_data used to force pending-review).
     */
    public function redecode(Report $report): array
    {
        if ($report->status !== 'pending' || $report->ai_analyzed_at === null) {
            return ['report_id' => $report->id, 'skipped' => true];
        }

        $analysis = $report->ai_analysis ?? [];
        if (empty($analysis)) {
            return ['report_id' => $report->id, 'skipped' => true];
        }

        $action = $this->decideAction($analysis, $analysis['location_check'] ?? [], $analysis['image_check'] ?? [], $report);
        $analysis['action'] = $action;
        // Mark the integrity gate so actionMessage() renders the mock-location
        // reason instead of a stale "AI rejected" line from old analysis data.
        if ($action === 'reject' && $this->isMockLocationRejected($report)) {
            $analysis['integrity_gate'] = 'mock_location_detected';
        }
        $analysis['authenticity_score'] = $this->computeAuthenticityScore($analysis);
        $message = $this->actionMessage($analysis);

        DB::transaction(function () use ($report, $analysis, $action, $message) {
            $report->update([
                'ai_analysis' => $analysis,
                'status' => $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'pending'),
                'moderation_message' => $message,
                'authenticity_score' => $analysis['authenticity_score'],
            ]);

            ModerationQueue::where('content_type', 'report')
                ->where('content_id', $report->id)
                ->update([
                    'status' => $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'pending'),
                    'reviewed_at' => now(),
                    'reviewed_by' => null,
                    'rejection_reason' => $action === 'reject' ? $message : null,
                ]);

            if ($action === 'approve') {
                $gpsVerified = ($analysis['location_check']['gps_status'] ?? null) === 'verified';
                if ($gpsVerified) {
                    $this->awardApprovalXp($report);
                }
            } else {
                app(AchievementService::class)->revokeReportApprovalXp($report);
            }
        });

        // Re-decode approvals also fan out proximity alerts (deduped).
        if ($action === 'approve') {
            app(\App\Services\AlertPublisherService::class)->publishFromReport($report->fresh() ?? $report);
        }

        return ['report_id' => $report->id, 'action' => $action, 'analysis' => $analysis];
    }

    protected function analyze(Report $report): array
    {
        // === Location-integrity gate: mock location → direct reject ========
        // The device's own detector reported a spoofed GPS fix (server-side
        // suspicious status + mock_location_detected). The decision is already
        // made, so reject immediately and SKIP text + vision AI entirely —
        // no API tokens are spent reviewing an untrustworthy location.
        if ($this->isMockLocationRejected($report)) {
            return $this->mockLocationRejection($report);
        }

        $quality = $this->checkQuality($report);

        if (!$quality['pass']) {
            $text = [
                'suggested_priority' => $report->priority,
                'is_legitimate' => false,
                'is_duplicate' => false,
                'summary' => $quality['reason'],
                'category_match' => null,
                'category_reason' => '',
                'quality_check' => $quality,
                'action' => 'reject',
            ];

            return array_merge($text, [
                'location_check' => $this->checkLocation($report),
                'image_check' => ['reviewed' => 0, 'images' => [], 'verdict' => 'unverifiable', 'message' => 'Skipped — failed quality check'],
                'action' => 'reject',
            ]);
        }

        $text = $this->analyzeText($report);
        $text['quality_check'] = $quality;

        if (!$text['is_legitimate'] || $text['is_duplicate']) {
            return array_merge($text, [
                'location_check' => $this->checkLocation($report),
                'image_check' => ['reviewed' => 0, 'images' => [], 'verdict' => 'unverifiable', 'message' => 'Skipped — text analysis rejected'],
                'action' => 'reject',
            ]);
        }

        $location = $this->checkLocation($report);

        if (!($location['valid'] ?? true)) {
            return array_merge($text, [
                'location_check' => $location,
                'image_check' => ['reviewed' => 0, 'images' => [], 'verdict' => 'unverifiable', 'message' => 'Skipped — location invalid'],
                'action' => 'reject',
            ]);
        }

        $image = $this->analyzeImages($report, $text);
        $action = $this->decideAction($text, $location, $image, $report);

        return array_merge($text, [
            'location_check' => $location,
            'image_check' => $image,
            'action' => $action,
        ]);
    }

    protected function checkQuality(Report $report): array
    {
        $title = strtolower(trim((string) $report->title));
        $description = strtolower(trim((string) $report->description));

        $testPatterns = [
            '/\btest(ing|er)?\b/', '/\bexample\b/', '/\bdemo\b/', '/\bsample\b/',
            '/\btrial\b/', '/\blorem\b/', '/\basdf\b/', '/\bqwerty\b/', '/\bjunk\b/',
            '/\bfake\b/', '/\bxyz\b/', '/^test\b/', '/\btesting\b/',
        ];

        foreach ($testPatterns as $pattern) {
            if (preg_match($pattern, $title) || preg_match($pattern, $description)) {
                return ['pass' => false, 'reason' => 'Looks like a test/example report (contains test-related keyword)'];
            }
        }

        if (mb_strlen($title) < 4) {
            return ['pass' => false, 'reason' => 'Title too short — not enough information'];
        }

        if (mb_strlen($description) > 0 && mb_strlen($description) < 10) {
            return ['pass' => false, 'reason' => 'Description too short — not enough information'];
        }

        return ['pass' => true, 'reason' => 'Quality check passed'];
    }

    protected function analyzeText(Report $report): array
    {
        $category = $report->category?->name ?? 'unknown';
        $text = "Title: {$report->title}\nDescription: {$report->description}\nPriority: {$report->priority}\nDistrict: {$report->district}\nCategory: {$category}\nReported location: {$report->latitude}, {$report->longitude}";

        try {
            $result = $this->textRouter->generateJson(
                "You are the approval officer for a Nepal community reporting app. Analyze this community report. Reports are written in Nepali OR English — Nepali text is NORMAL and legitimate, never reject a report just because it is not in English.\n\n"
                . "is_legitimate must be FALSE (reject) for: test/example/meaningless reports; personal or social chatter that is not a community issue (e.g. someone visiting a house, gossip, greetings, mood posts); vague statements with no incident, hazard, problem, event or actionable information; spam.\n"
                . "is_legitimate must be TRUE (approve) only for reports describing a real community issue: road damage, landslide, flood, fire, accident, waste, electricity/water outage, crime, missing person, animal hazards, events, lost/found, and similar.\n"
                . "When in doubt between legitimate and junk, choose junk (is_legitimate=false) — never approve a trivial report.\n"
                . "Return JSON: suggested_priority (low/medium/high/critical), is_legitimate (bool), is_duplicate (bool — true if same issue already reported), summary (string, max 2 sentences in English), category_match (bool — true if title/description matches the report category), category_reason (string), action (approve/reject).\n\n"
                . "If is_duplicate is true, action must be reject. If is_legitimate is true, action must be approve.\n\n{$text}"
            );
        } catch (\Throwable $e) {
            // AI unavailability must NEVER abort the automated pipeline (this
            // was the root cause of reports stuck pending with no analysis).
            // The deterministic quality gate already passed, so continue with
            // the remaining deterministic signals — location validity, image
            // duplicate reuse, EXIF camera trace, deterministic screening,
            // GPS verification — and let the existing decision engine weigh
            // them under the normal thresholds. A blanket "AI down → human
            // review" rule is explicitly not what happens here: the engine
            // still auto-approves/rejects when its evidence is sufficient.
            Log::warning("Text AI unavailable for report#{$report->id}: " . $e->getMessage());

            return [
                'suggested_priority' => $report->priority,
                'is_legitimate' => true,
                'is_duplicate' => false,
                'summary' => 'Automated text analysis unavailable — decision based on deterministic signals',
                'category_match' => null,
                'category_reason' => 'Text AI unavailable — category match not asserted',
                'action' => 'approve',
                'ai_status' => 'unavailable',
                'ai_error' => mb_substr($e->getMessage(), 0, 300),
            ];
        }

        $isDuplicate = $result['is_duplicate'] ?? false;
        $isLegitimate = $result['is_legitimate'] ?? true;

        $result['is_duplicate'] = $isDuplicate;
        $result['is_legitimate'] = $isLegitimate;
        $result['category_match'] = $result['category_match'] ?? null;
        $result['category_reason'] = $result['category_reason'] ?? '';
        $result['action'] = ($isDuplicate || !$isLegitimate) ? 'reject' : ($result['action'] ?? 'approve');
        $result['ai_status'] = 'ok';

        return $result;
    }

    protected function checkLocation(Report $report): array
    {
        $lat = $report->latitude;
        $lng = $report->longitude;
        // Server-evaluated location integrity (genuine | suspicious |
        // cannot_determine). NEVER invalidates the location on its own —
        // it only modulates the confidence score below, so a suspicious
        // result routes to human review instead of auto-reject.
        $integrity = $report->location_integrity_status;

        if ($lat === null || $lng === null) {
            return ['valid' => true, 'verifiable' => false, 'gps_status' => $report->gps_verification_status, 'integrity_status' => $integrity, 'reason' => 'No coordinates provided'];
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat == 0.0 && $lng == 0.0) {
            return ['valid' => false, 'verifiable' => true, 'gps_status' => $report->gps_verification_status, 'integrity_status' => $integrity, 'reason' => 'Placeholder coordinates (0,0) — location not real'];
        }

        if ($lat < self::NEPAL_BBOX['lat_min'] || $lat > self::NEPAL_BBOX['lat_max']
            || $lng < self::NEPAL_BBOX['lng_min'] || $lng > self::NEPAL_BBOX['lng_max']) {
            return ['valid' => false, 'verifiable' => true, 'gps_status' => $report->gps_verification_status, 'integrity_status' => $integrity, 'reason' => 'Coordinates outside Nepal bounds — likely fake'];
        }

        if ($report->gps_verification_status === 'mismatched') {
            return ['valid' => false, 'verifiable' => true, 'gps_status' => 'mismatched', 'integrity_status' => $integrity, 'reason' => 'Photo GPS does not match reported location — likely fake'];
        }

        $verifiable = $report->gps_verification_status === 'verified';

        return ['valid' => true, 'verifiable' => $verifiable, 'gps_status' => $report->gps_verification_status, 'integrity_status' => $integrity, 'reason' => 'Location looks valid'];
    }

    protected function analyzeImages(Report $report, array $text = []): array
    {
        $media = ReportMedia::where('report_id', $report->id)
            ->where('type', 'image')
            ->take(self::MAX_IMAGES)
            ->get(['id', 'media_url']);

        if ($media->isEmpty()) {
            return ['reviewed' => 0, 'images' => [], 'verdict' => 'clean', 'message' => 'No images attached'];
        }

        $images = [];
        foreach ($media as $item) {
            $path = Storage::disk('public')->path($item->media_url);

            if (!is_file($path) || filesize($path) === 0) {
                $images[] = ['media_id' => $item->id, 'verdict' => 'skipped', 'reason' => 'File missing or empty'];
                continue;
            }

            if (filesize($path) > self::MAX_IMAGE_BYTES) {
                $images[] = ['media_id' => $item->id, 'verdict' => 'skipped', 'reason' => 'Image too large (>4MB) — skipped'];
                continue;
            }

            // Same-image reuse trap: exact duplicate of a photo used in another
            // report within the last 30 days is almost certainly re-uploaded fake.
            $hash = $item->media_hash ?: hash_file('sha256', $path);
            if ($hash) {
                $duplicate = DB::table('report_media')
                    ->join('reports', 'reports.id', '=', 'report_media.report_id')
                    ->where('report_media.media_hash', $hash)
                    ->where('report_media.report_id', '!=', $report->id)
                    ->where('reports.created_at', '>=', now()->subDays(30))
                    ->first(['report_media.report_id']);
                if ($duplicate) {
                    $images[] = [
                        'media_id' => $item->id,
                        'verdict' => 'duplicate',
                        'reason' => "Identical image already used in report #{$duplicate->report_id} — likely re-uploaded",
                    ];
                    continue;
                }
            }

            // Fingerprint-based near-duplicate detection: images with the same
            // fingerprint hash are likely the same photo (byte-level sampling).
            // Limitation: this is NOT a perceptual hash — it won't detect
            // resized/recompressed versions, but it catches same-byte copies
            // that were saved with different filenames.
            $fingerprint = $item->fingerprint_hash ?? null;
            if ($fingerprint) {
                $fpDuplicate = DB::table('report_media')
                    ->join('reports', 'reports.id', '=', 'report_media.report_id')
                    ->where('report_media.fingerprint_hash', $fingerprint)
                    ->where('report_media.report_id', '!=', $report->id)
                    ->where('report_media.media_hash', '!=', $hash)
                    ->where('reports.created_at', '>=', now()->subDays(30))
                    ->first(['report_media.report_id']);
                if ($fpDuplicate) {
                    $images[] = [
                        'media_id' => $item->id,
                        'verdict' => 'duplicate',
                        'reason' => "Fingerprint match with report #{$fpDuplicate->report_id} — likely same photo, different encoding",
                    ];
                    continue;
                }
            }

            // Pure-code EXIF trace (free — no AI call): real camera photos carry
            // Make/Model + lens data; screenshots/downloads usually carry none.
            $trace = $this->checkCameraTrace($path);
            $dims = @getimagesize($path);
            $width = $dims[0] ?? 0;
            $height = $dims[1] ?? 0;

            // Deterministic pre-filter (no AI cost): cheap structural
            // screen/reuse signals. Strong evidence reuses the EXISTING
            // 'suspicious' verdict (which the decision engine already routes
            // to human review, never auto-approval) and skips the vision
            // call. Otherwise behaviour is unchanged: vision AI runs, and the
            // screening facts ride along as extra evidence for moderators.
            // This is NOT camera-provenance proof — see ImageScreeningService.
            //
            // Computed BEFORE every remaining branch (square heuristic, strong
            // signal, vision call, provider outage) and attached to each one,
            // so the stored result always carries the screening evidence —
            // including when all AI providers are unavailable.
            $screening = $this->imageScreening->screen($path, $width, $height, $trace);

            // Screenshot/download heuristic: no camera metadata + square web-size
            // dimensions = classic re-uploaded template/screenshot. Flag for human
            // review WITHOUT spending any AI quota.
            if ($trace['kind'] === 'no_metadata' && $width > 0 && $width === $height && $width < 800) {
                $images[] = [
                    'media_id' => $item['id'],
                    'verdict' => 'suspicious',
                    'reason' => 'No camera metadata + square web-size dimensions — likely screenshot or downloaded image',
                    'exif_trace' => $trace,
                    'ai_skipped' => true,
                    'provider_used' => null,
                    'screening' => $screening,
                ];
                continue;
            }

            if ($screening['strong']) {
                $images[] = [
                    'media_id' => $item['id'],
                    'verdict' => 'suspicious',
                    'reason' => 'Deterministic image pre-filter — Vision AI skipped: '
                        . implode('; ', array_column($screening['signals'], 'detail')),
                    'exif_trace' => $trace,
                    'ai_skipped' => true,
                    'provider_used' => null,
                    'screening' => $screening,
                ];
                continue;
            }

            try {
                $result = $this->visionRouter->generateJsonWithImages(
                    $this->imagePrompt($report, $text),
                    [$path],
                    ['maxOutputTokens' => 900]
                );
                $entry = $this->judgeImage($item->id, $result);
                $entry['exif_trace'] = $trace;
                $entry['screening'] = $screening;
                $images[] = $entry;
            } catch (AiRateLimitException $e) {
                Log::warning("Vision AI unavailable for report#{$report->id} media#{$item->id}: " . $e->getMessage());
                $images[] = [
                    'media_id' => $item->id,
                    'verdict' => 'unverifiable',
                    'reason' => 'AI providers all unavailable (quota/rate limit)',
                    'exif_trace' => $trace,
                    'provider_used' => null,
                    'screening' => $screening,
                ];
            } catch (\Throwable $e) {
                Log::error("Image analysis failed for report#{$report->id} media#{$item->id}: " . $e->getMessage());
                $images[] = [
                    'media_id' => $item->id,
                    'verdict' => 'unverifiable',
                    'reason' => 'Vision API error',
                    'exif_trace' => $trace,
                    'provider_used' => null,
                    'screening' => $screening,
                ];
            }
        }

        $reviewed = collect($images)->whereIn('verdict', ['clean', 'suspicious', 'violation'])->count();

        if ($reviewed === 0) {
            return ['reviewed' => 0, 'images' => $images, 'verdict' => 'unverifiable', 'message' => 'Images attached but none could be analyzed — needs moderator review'];
        }

        $verdict = 'clean';
        $duplicates = false;
        $unverifiable = false;
        foreach ($images as $img) {
            if ($img['verdict'] === 'violation') {
                $verdict = 'violation';
                break;
            }
            if ($img['verdict'] === 'suspicious' && $verdict === 'clean') {
                $verdict = 'suspicious';
            }
            if ($img['verdict'] === 'unverifiable') {
                $unverifiable = true;
            }
            if ($img['verdict'] === 'duplicate') {
                $duplicates = true;
                if ($verdict === 'clean') {
                    $verdict = 'duplicate';
                }
            }
        }
        if ($verdict === 'clean' && $unverifiable) {
            $verdict = 'unverifiable';
        }

        return ['reviewed' => $reviewed, 'images' => $images, 'verdict' => $verdict, 'message' => $verdict];
    }

    protected function imagePrompt(Report $report, array $text = []): string
    {
        $category = $report->category?->name ?? 'unknown';
        $summary = $text['summary'] ?? '';
        $categoryMatch = $text['category_match'] ?? null;

        return "You are a content moderator for a Nepal community reporting app. Analyze the attached photo of a community report.\n\n"
            . "Report title: {$report->title}\n"
            . "Description: {$report->description}\n"
            . "Category: {$category}\n"
            . "District: {$report->district}\n"
            . "Text analysis summary: {$summary}\n"
            . "Text analysis category match: " . ($categoryMatch === null ? 'unknown' : ($categoryMatch ? 'yes' : 'no')) . "\n\n"
            . "Guidelines:\n"
            . "- A photo is LEGITIMATE if it actually shows the incident/issue described (e.g. landslide, flood, damaged road, garbage pile, accident scene).\n"
            . "- A photo taken OF A DEVICE SCREEN/DISPLAY (a laptop, phone or monitor showing another image) is FAKE. Detect screen-photo telltales: moiré pattern (wavy rainbow distortion), visible pixel grid or scanlines when zoomed, reflections or glare from a display surface, screen bezel or display edge visible, wallpaper-like texture. Real photos taken with a phone camera do not have these patterns.\n"
            . "- A MAP SCREENSHOT or a photo of a device showing a MAP/SATELLITE VIEW (e.g. Google Maps, Map Data, (c) Google, tiles, satellite/aerial imagery, labels over tiled imagery, top-down view) is NOT proof of a ground-level incident — such map/satellite-looking evidence is treated as screen/misleading content unless the description itself claims an aerial/map event.\n"
            . "- Explicitly look for on-screen UI: map search box, zoom +/- buttons, (c) Google / Map data watermarks, map tiles with labels, browser tabs/address bar, taskbar, cursor, video progress bar, settings icons, window title bar.\n"
            . "- A photo is MISLEADING or fake if: it is AI-generated, it is a photo of a screen, it is a map/satellite screenshot, it shows something unrelated to the claim (e.g. random objects, furniture, selfie, screenshot, stock photo), or it contradicts the description.\n"
            . "- Reports can be in Nepali or English — language of the photo text is never a violation.\n"
            . "Return JSON ONLY:\n"
            . "- \"is_ai_generated\" (bool — true if the image looks AI-generated/rendered)\n"
            . "- \"is_screen_photo\" (bool — true if the photo is a photo of a screen/display showing another image, or shows moiré/pixel-grid/glare patterns typical of photographing a screen)\n"
            . "- \"screen_probability\" (number 0-1 — how likely this photo is a photo of another device's screen)\n"
            . "- \"real_scene_probability\" (number 0-1 — how likely this is a direct real-world capture of the actual scene)\n"
            . "- \"is_map_screenshot\" (bool — true if the photo shows a map/satellite view, map app interface, or a screen showing a map)\n"
            . "- \"shows_what\" (string — what the photo actually shows)\n"
            . "- \"matches_title_description\" (bool — photo matches the claim)\n"
            . "- \"report_match\" (number 0-1 — how strongly the photo matches the report title/description)\n"
            . "- \"misleading\" (bool — photo shows something different from the claim or is deliberately misleading)\n"
            . "- \"inappropriate_abusive\" (bool — abusive, offensive, gali, or NSFW content)\n"
            . "- \"phishing\" (bool — scam/phishing/fake promotion content)\n"
            . "- \"confidence\" (number 0-1 — how confident you are about the flags above)\n"
            . "- \"summary\" (string, 1 sentence)";
    }

    /**
     * Pure-code EXIF camera-trace classification (zero AI cost). Classifies an
     * image as: 'camera' (real camera EXIF: Make/Model), 'screenshotish'
     * (software stamp, no camera), 'no_camera_trace' (EXIF but nothing camera),
     * 'no_metadata' (no EXIF at all), or 'unknown' (unreadable container).
     * When ext-exif is unavailable it falls back to the pure-PHP TIFF/IFD
     * parser (JPEG APP1 / PNG eXIf / WebP EXIF) so the server's missing
     * 'exif' extension no longer forces every image to 'unknown'.
     * This is a WEAK signal — never a hard reject on its own.
     */
    protected function checkCameraTrace(string $path): array
    {
        if (function_exists('exif_read_data')) {
            $mime = mime_content_type($path) ?: '';
            if (!in_array($mime, ['image/jpeg', 'image/jpg'])) {
                return ['kind' => 'unknown', 'reason' => 'Not a JPEG image (' . $mime . ')'];
            }

            $exif = @exif_read_data($path);
            if (!$exif || !is_array($exif)) {
                return ['kind' => 'no_metadata', 'reason' => 'No EXIF metadata at all'];
            }

            return $this->classifyCameraTrace([
                'make' => (string) ($exif['Make'] ?? ''),
                'model' => (string) ($exif['Model'] ?? ''),
                'software' => (string) ($exif['Software'] ?? ''),
                'has_lens_data' => isset($exif['FNumber']) || isset($exif['FocalLength'])
                    || isset($exif['ExposureTime']) || isset($exif['ISOSpeedRatings']),
            ]);
        }

        // No ext-exif on this server: use the pure-PHP TIFF/IFD parser
        // (ImageScreeningService::parseExifTags) instead of giving up.
        $parsed = $this->imageScreening->parseExifTags($path);

        if ($parsed['container'] === 'unknown') {
            return ['kind' => 'unknown', 'reason' => 'Unsupported image container for EXIF inspection'];
        }

        if (!$parsed['exif_found']) {
            return ['kind' => 'no_metadata', 'reason' => 'No EXIF metadata at all'];
        }

        return $this->classifyCameraTrace($parsed);
    }

    /**
     * Shared classification for both trace readers (ext-exif and the pure-PHP
     * fallback): make/model -> camera, software stamp -> screenshotish,
     * anything else -> EXIF present but no camera trace.
     */
    private function classifyCameraTrace(array $tags): array
    {
        $make = trim((string) ($tags['make'] ?? ''));
        $model = trim((string) ($tags['model'] ?? ''));
        $software = trim((string) ($tags['software'] ?? ''));
        $hasLens = !empty($tags['has_lens_data']);

        if ($make !== '' || $model !== '') {
            return [
                'kind' => 'camera',
                'make' => $make,
                'model' => $model,
                'has_lens_data' => $hasLens,
                'reason' => 'Real camera EXIF trace present',
            ];
        }

        if ($software !== '') {
            return [
                'kind' => 'screenshotish',
                'software' => $software,
                'has_lens_data' => false,
                'reason' => 'No camera trace; software stamp: ' . $software,
            ];
        }

        return ['kind' => 'no_camera_trace', 'has_lens_data' => false, 'reason' => 'EXIF present but no camera or software trace'];
    }

    protected function judgeImage(int $mediaId, array $result): array
    {
        $confidence = (float) ($result['confidence'] ?? 0);
        $reason = (string) ($result['summary'] ?? $result['shows_what'] ?? '');

        $screenProb = (float) ($result['screen_probability'] ?? 0);
        if ($screenProb <= 0 && ($result['is_screen_photo'] ?? false)) {
            $screenProb = min($confidence, 0.99);
        }
        if (($result['is_screen_photo'] ?? false) && $screenProb < self::SCREEN_WEAK_SIGNAL) {
            $screenProb = self::SCREEN_WEAK_SIGNAL;
        }
        $isMapShot = (bool) ($result['is_map_screenshot'] ?? false);
        if ($isMapShot && $screenProb < self::SCREEN_WEAK_SIGNAL) {
            $screenProb = self::SCREEN_WEAK_SIGNAL;
        }
        $realProb = (float) ($result['real_scene_probability'] ?? 0);
        if ($realProb <= 0) {
            $realProb = (float) ($result['report_match'] ?? 0);
        }
        $reportMatch = (float) ($result['report_match'] ?? (($result['matches_title_description'] ?? null) ? 0.9 : 0.3));

        $entry = [
            'media_id' => $mediaId,
            'is_ai_generated' => (bool) ($result['is_ai_generated'] ?? false),
            'is_screen_photo' => (bool) ($result['is_screen_photo'] ?? false) || $isMapShot,
            'is_map_screenshot' => $isMapShot,
            'screen_probability' => round($screenProb, 2),
            'real_scene_probability' => round($realProb, 2),
            'report_match' => round($reportMatch, 2),
            'shows_what' => $result['shows_what'] ?? '',
            'matches_title_description' => $result['matches_title_description'] ?? null,
            'misleading' => (bool) ($result['misleading'] ?? false),
            'inappropriate_abusive' => (bool) ($result['inappropriate_abusive'] ?? false),
            'phishing' => (bool) ($result['phishing'] ?? false),
            'confidence' => $confidence,
            'reason' => $reason,
            'provider_used' => $result['provider_used'] ?? null,
        ];

        if (($result['phishing'] ?? false) || ($result['inappropriate_abusive'] ?? false)) {
            $entry['verdict'] = 'violation';
            $entry['verdict_reason'] = 'Prohibited content (abusive/phishing)';
        } elseif ($screenProb >= self::IMAGE_VIOLATION_CONFIDENCE) {
            $entry['verdict'] = 'violation';
            $entry['verdict_reason'] = 'Photo of a screen/display (moiré or pixel-grid pattern) — not a real scene';
        } elseif ($screenProb >= 0.55) {
            $entry['verdict'] = 'suspicious';
            $entry['verdict_reason'] = 'Possible photo of a screen (moiré/pixel pattern) — needs moderator review';
        } elseif ($screenProb >= self::SCREEN_WEAK_SIGNAL) {
            $entry['verdict'] = 'suspicious';
            $entry['verdict_reason'] = 'Weak screen-photo signal detected — needs moderator review';
        } elseif (($result['is_ai_generated'] ?? false) || ($result['misleading'] ?? false)) {
            if ($confidence >= self::IMAGE_VIOLATION_CONFIDENCE) {
                $entry['verdict'] = 'violation';
                $entry['verdict_reason'] = ($result['is_ai_generated'] ?? false) ? 'AI-generated or fake image' : 'Misleading image';
            } else {
                $entry['verdict'] = 'suspicious';
                $entry['verdict_reason'] = 'Possible AI/fake or misleading image — needs moderator review';
            }
        } else {
            $entry['verdict'] = 'clean';
            $entry['verdict_reason'] = '';
        }

        return $entry;
    }

    /**
     * A vision result full of defaults (empty JSON, all-false flags, no
     * description) means the model produced NO usable analysis — treat it
     * as a provider failure, never as a "clean" verdict.
     */
    protected function isVisionResultUsable(array $result): bool
    {
        if (empty($result)) {
            return false;
        }
        foreach (['shows_what', 'summary', 'what_it_shows', 'key_content', 'confidence', 'report_match', 'screen_probability'] as $key) {
            if (isset($result[$key]) && $result[$key] !== '' && $result[$key] !== null && $result[$key] !== 0) {
                return true;
            }
        }
        if (isset($result['is_screen_photo']) || isset($result['is_ai_generated'])
            || isset($result['misleading']) || isset($result['matches_title_description'])) {
            return true;
        }
        return false;
    }

    /**
     * Overall trust score 0.00-1.00 combining text legitimacy, location
     * verification, and the vision verdict. Used for the admin "AI trust"
     * badge and stored in reports.authenticity_score.
     */
    public function computeAuthenticityScore(array $analysis): float
    {
        $text = round((int) ($analysis['is_legitimate'] ?? true) * (($analysis['is_duplicate'] ?? false) ? 0 : 1), 2);

        $location = $analysis['location_check'] ?? [];
        $gps = $location['gps_status'] ?? null;
        $loc = ($location['valid'] ?? true)
            ? ($gps === 'verified' ? 1.0 : ($gps === 'mismatched' ? 0.0 : 0.85))
            : 0.0;

        // Suspicious location integrity (e.g. mock-location detection,
        // impossible movement) caps the location trust component. It reduces
        // trust but never zeroes it — that would auto-reject on a single
        // client-side signal, which the moderation policy explicitly avoids.
        if (($location['integrity_status'] ?? null) === LocationIntegrityService::STATUS_SUSPICIOUS) {
            $loc = min($loc, 0.5);
        }

        $image = $analysis['image_check'] ?? [];
        $verdict = $image['verdict'] ?? 'clean';
        $img = match ($verdict) {
            'violation' => 0.05,
            'duplicate' => 0.2,
            'suspicious' => 0.45,
            'unverifiable' => 0.5,
            default => 0.95,
        };
        $images = $image['images'] ?? [];
        if (count($images) > 0) {
            $probs = array_map(fn ($i) => 1 - (float) ($i['screen_probability'] ?? 0), $images);
            $img = ($img + min($probs)) / 2;
        }

        $score = ($text * 0.35) + ($loc * 0.25) + ($img * 0.4);
        return round(max(0.0, min(1.0, $score)), 2);
    }

    /**
     * Compute a confidence score for the Decision Engine.
     *
     * This score determines whether a report is auto-approved, sent to
     * human review, or auto-rejected. It combines ALL available signals
     * into a single 0.00-1.00 score.
     *
     * SIGNAL WEIGHTS (total = 1.0):
     *   Image analysis:   0.30 (dominant — visual evidence is strongest)
     *   Text analysis:    0.25 (legitimacy + duplicate)
     *   Location:         0.20 (Nepal bounds + GPS verification)
     *   Duplicate image:  0.15 (exact/fingerprint match)
     *   Fraud/velocity:   0.10 (user history)
     *
     * THRESHOLDS:
     *   >= 0.80  →  AUTO APPROVE (high confidence safe)
     *   >= 0.50  →  PENDING REVIEW (uncertain/suspicious)
     *   <  0.50  →  AUTO REJECT (clearly invalid/low confidence)
     *
     * HARD REJECTS override the score entirely (return 0.0).
     */
    public function computeConfidence(
        array $analysis,
        ?Report $report = null,
        ?array $imageMetadata = null,
        ?array $fraudResult = null,
    ): float {
        $image = $analysis['image_check'] ?? [];
        $location = $analysis['location_check'] ?? [];
        $images = $image['images'] ?? [];

        // === HARD REJECTS: override score to 0.0 ===

        if (($analysis['is_duplicate'] ?? false) || !($analysis['is_legitimate'] ?? true)) {
            return 0.0;
        }

        if (!($location['valid'] ?? true)) {
            return 0.0;
        }

        if (($image['verdict'] ?? 'clean') === 'violation') {
            return 0.0;
        }

        if (isset($imageMetadata['hard_reject']) && $imageMetadata['hard_reject']) {
            return 0.0;
        }

        if ($fraudResult && ($fraudResult['blocked'] ?? false)) {
            return 0.0;
        }

        // === COMPUTE WEIGHTED SCORE ===

        // --- Image signal (0.30) ---
        $verdict = $image['verdict'] ?? 'clean';
        $imgScore = match ($verdict) {
            'clean' => 0.95,
            'unverifiable' => 0.50,
            'suspicious' => 0.35,
            'duplicate' => 0.20,
            default => 0.50,
        };

        // --- Image signal adjustments (vision probabilities) ---
        // The bonus/penalty below are driven by VISION probabilities
        // (real_scene_probability / report_match). Entries produced WITHOUT
        // vision — deterministic screening skip, provider outage, missing
        // file — carry no probabilities at all. Treating that absence as
        // "vision said 0.0" double-penalizes missing evidence and would
        // force every report into human review purely because AI was down.
        // When vision actually ran, behaviour is byte-for-byte unchanged.
        $hasVisionEvidence = false;
        foreach ($images as $img) {
            if (array_key_exists('real_scene_probability', $img) || array_key_exists('report_match', $img)) {
                $hasVisionEvidence = true;
                break;
            }
        }

        if (!empty($images) && $hasVisionEvidence) {
            $avgRealScene = 0;
            $avgMatch = 0;
            $count = count($images);
            foreach ($images as $img) {
                $avgRealScene += (float) ($img['real_scene_probability'] ?? 0);
                $avgMatch += (float) ($img['report_match'] ?? 0);
            }
            $avgRealScene /= max($count, 1);
            $avgMatch /= max($count, 1);

            if ($avgRealScene >= 0.8 && $avgMatch >= 0.7) {
                $imgScore = min(1.0, $imgScore + 0.15);
            }
            if ($avgRealScene < 0.5 || $avgMatch < 0.4) {
                $imgScore = max(0.0, $imgScore - 0.20);
            }
        }

        if (empty($images) && $verdict === 'clean') {
            $imgScore = 0.40;
        }

        // --- Text signal (0.25) ---
        $textScore = ($analysis['is_legitimate'] ?? true) ? 0.90 : 0.0;
        if ($analysis['is_duplicate'] ?? false) {
            $textScore = 0.0;
        }
        if ($analysis['category_match'] ?? false) {
            $textScore = min(1.0, $textScore + 0.10);
        }

        // --- Location signal (0.20) ---
        $gps = $location['gps_status'] ?? null;
        $locScore = match ($gps) {
            'verified' => 1.0,
            'no_gps_data' => 0.70,
            'mismatched' => 0.20,
            default => 0.70,
        };
        if (!($location['valid'] ?? true)) {
            $locScore = 0.0;
        }

        // Location-integrity modulation: a server-evaluated 'suspicious'
        // integrity (mock location reported, impossible movement, implausible
        // accuracy/timestamp, photo conflict) lowers the location signal to
        // 0.45 — enough to make auto-approval harder and push borderline
        // reports to human review, but NOT enough to auto-reject on its own.
        // 'genuine' and 'cannot_determine' are deliberately neutral here:
        // lack of detector support must never be punished as fraud.
        if (($location['integrity_status'] ?? null) === LocationIntegrityService::STATUS_SUSPICIOUS) {
            $locScore = min($locScore, 0.45);
        }

        // --- Duplicate image signal (0.15) ---
        $dupScore = 0.90;
        if (($image['verdict'] ?? '') === 'duplicate') {
            $dupScore = 0.10;
        }
        if (($imageMetadata['fingerprint_near_duplicate'] ?? false)) {
            $dupScore = max(0.30, $dupScore - 0.30);
        }

        // --- Fraud/velocity signal (0.10) ---
        $fraudScoreVal = 0.90;
        if (!empty($fraudResult['reasons'])) {
            $fraudScoreVal = 0.20;
        }
        if ($report && $report->provenance === 'suspicious') {
            $fraudScoreVal = max(0.30, $fraudScoreVal - 0.30);
        }

        // === WEIGHTED COMBINATION ===
        $confidence = ($imgScore * 0.30)
            + ($textScore * 0.25)
            + ($locScore * 0.20)
            + ($dupScore * 0.15)
            + ($fraudScoreVal * 0.10);

        return round(max(0.0, min(1.0, $confidence)), 2);
    }

    /**
     * Decide the action for a report after AI analysis.
     *
     * DECISION ENGINE:
     * Uses a confidence score computed from ALL available signals to determine
     * the outcome. The client never controls the decision — only the server-side
     * analysis determines the result.
     *
     * THRESHOLDS:
     *   confidence >= 0.80  →  AUTO APPROVE (high confidence safe)
     *   confidence >= 0.50  →  PENDING REVIEW (uncertain/suspicious)
     *   confidence <  0.50  →  AUTO REJECT (clearly invalid)
     *
     * BIPAD/SYSTEM reports get a +0.10 provenance boost.
     */
    protected function decideAction(array $text, array $location, array $image, ?Report $report = null): string
    {
        // === EARLY REJECTS (no scoring needed) ===

        // Mock location gate (same rule as analyze()): active here so the
        // redecode() path over stored analyses also rejects directly.
        if ($report !== null && $this->isMockLocationRejected($report)) {
            return 'reject';
        }

        if (($text['is_duplicate'] ?? false) || !($text['is_legitimate'] ?? true)) {
            return 'reject';
        }

        if (!($location['valid'] ?? true)) {
            return 'reject';
        }

        if (($image['verdict'] ?? 'clean') === 'violation') {
            return 'reject';
        }

        // === COMPUTE CONFIDENCE SCORE ===
        $combined = $text + ['image_check' => $image, 'location_check' => $location];
        $confidence = $this->computeConfidence($combined, $report);

        // === APPLY PROVENANCE BOOST FOR TRUSTED SOURCES ===
        $source = $report?->source ?? 'user';
        $provenance = $report?->provenance ?? null;

        if (in_array($source, ['bipad', 'system']) || $provenance === 'system') {
            $confidence = min(1.0, $confidence + 0.10);
        }

        // === DECISION ===

        if ($confidence >= 0.80) {
            // Suspicious location integrity (mock flag, impossible movement,
            // photo conflict …) vetoes AUTO-APPROVAL only: the report is
            // routed to human moderation instead. It never downgrades to
            // 'reject' here — combined evidence still decides rejection
            // through the normal confidence path.
            if (($location['integrity_status'] ?? null) === LocationIntegrityService::STATUS_SUSPICIOUS) {
                return 'pending-review';
            }

            return 'approve';
        }

        if ($confidence >= 0.50) {
            return 'pending-review';
        }

        return 'reject';
    }

    /**
     * Mock location was detected on the device: reject the report directly,
     * before any AI call. Only the Android detector's own mock claim triggers
     * this (it affects only the submitter's own report). Every other integrity
     * signal (impossible movement, accuracy, stale timestamps …) keeps the
     * normal pipeline — those vetoes auto-approval but still get AI-reviewed.
     */
    private function isMockLocationRejected(Report $report): bool
    {
        return $report->mock_location_detected === true
            && $report->location_integrity_status === LocationIntegrityService::STATUS_SUSPICIOUS;
    }

    /**
     * Deterministic rejection payload for the mock-location gate. Mirrors the
     * shape of other early rejects (quality/text) so the admin panel and
     * redecode() render it unchanged — with zero AI provider calls.
     */
    private function mockLocationRejection(Report $report): array
    {
        return [
            'suggested_priority' => $report->priority,
            'is_legitimate' => false,
            'is_duplicate' => false,
            'summary' => 'Mock location detected — device reported a spoofed GPS fix; AI review skipped',
            'category_match' => null,
            'category_reason' => '',
            'quality_check' => [
                'pass' => false,
                'reason' => 'Mock location detected — rejected before AI review (no API tokens spent)',
            ],
            'location_check' => $this->checkLocation($report),
            'image_check' => [
                'reviewed' => 0,
                'images' => [],
                'verdict' => 'unverifiable',
                'message' => 'Skipped — mock location detected before any AI call',
            ],
            'action' => 'reject',
            'integrity_gate' => 'mock_location_detected',
        ];
    }

    /**
     * Human-readable one-line reason for the admin panel: why the agent
     * approved, rejected, or left this report for manual review.
     */
    protected function actionMessage(array $analysis): string
    {
        $action = $analysis['action'] ?? 'approve';
        $loc = $analysis['location_check'] ?? [];
        $img = $analysis['image_check'] ?? [];
        $gps = $loc['gps_status'] ?? null;
        $gpsNote = $gps === 'verified'
            ? 'photo GPS verified'
            : ($gps === null ? 'no photo GPS' : "photo GPS not verified ({$gps})");

        if ($action === 'approve') {
            $msg = 'AI approved — ' . trim((string) ($analysis['summary'] ?? 'valid community report'));
            if ($gps !== 'verified') {
                $msg .= ' | ' . $gpsNote . ' — processed without GPS verification';
            }
            $score = (float) ($analysis['authenticity_score'] ?? 0);
            if ($score > 0) {
                $msg .= ' | trust ' . round($score * 100) . '%';
            }
            return $msg;
        }

        if ($action === 'reject') {
            // Integrity gate ran BEFORE any AI — do not mislabel it "AI rejected".
            if (($analysis['integrity_gate'] ?? null) === 'mock_location_detected') {
                return 'Auto-rejected — mock location detected (location integrity gate; AI review skipped)';
            }
            foreach (($img['images'] ?? []) as $i) {
                if (!empty($i['verdict_reason'])) {
                    return 'AI rejected — ' . $i['verdict_reason'];
                }
            }
            if (!empty($loc['reason']) && !($loc['valid'] ?? true)) {
                return 'AI rejected — ' . $loc['reason'];
            }
            if (!empty($analysis['quality_check']['reason']) && !($analysis['quality_check']['pass'] ?? true)) {
                return 'AI rejected — ' . $analysis['quality_check']['reason'];
            }
            $reason = (string) ($analysis['summary'] ?? 'not a legitimate community issue');
            return 'AI rejected — ' . $reason;
        }

        $verdict = (string) ($img['verdict'] ?? 'manual review');
        // Integrity note must survive every return path in this branch —
        // otherwise an image verdict_reason hides why the report is queued.
        $suffix = ($loc['integrity_status'] ?? null) === LocationIntegrityService::STATUS_SUSPICIOUS
            ? ' | location integrity flagged (see report security log)'
            : '';
        foreach (($img['images'] ?? []) as $i) {
            if (!empty($i['verdict_reason'])) {
                return 'AI: needs moderator review — ' . $i['verdict_reason'] . $suffix;
            }
        }
        $message = 'AI: needs moderator review — ' . ($img['message'] ?? $verdict);

        return $message . $suffix;
    }

    protected function awardApprovalXp(Report $report): void
    {
        $alreadyRewarded = XpTransaction::where('reference_type', Report::class)
            ->where('reference_id', $report->id)
            ->where('action_type', 'report_approved')
            ->exists();

        if ($alreadyRewarded) return;

        $reporter = $report->user;
        if (!$reporter) return;

        $rewardXp = GameSetting::getValue('report_approval_xp', 10);
        app(AchievementService::class)->awardXp(
            $reporter,
            $rewardXp,
            'report_approved',
            "Report approved: {$report->title}",
            $report
        );
        $reporter->increment('approved_reports');
    }
}
