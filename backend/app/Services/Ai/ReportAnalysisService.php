<?php

namespace App\Services\Ai;

use App\Exceptions\AiRateLimitException;
use App\Models\GameSetting;
use App\Models\ModerationQueue;
use App\Models\Report;
use App\Models\ReportMedia;
use App\Models\XpTransaction;
use App\Services\AchievementService;
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

    public function __construct(?AiFallbackRouter $textRouter = null, ?AiFallbackRouter $visionRouter = null)
    {
        $this->textRouter = $textRouter ?? AiFallbackRouter::textChain();
        $this->visionRouter = $visionRouter ?? AiFallbackRouter::visionChain(fn (array $r) => $this->isVisionResultUsable($r));
    }

    public function process(Report $report, bool $force = false): array
    {
        if (!$force && ($report->status !== 'pending' || $report->ai_analyzed_at !== null)) {
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

        $action = $this->decideAction($analysis, $analysis['location_check'] ?? [], $analysis['image_check'] ?? []);
        $analysis['action'] = $action;
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

        return ['report_id' => $report->id, 'action' => $action, 'analysis' => $analysis];
    }

    protected function analyze(Report $report): array
    {
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
        $action = $this->decideAction($text, $location, $image);

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

        $result = $this->textRouter->generateJson(
            "You are the approval officer for a Nepal community reporting app. Analyze this community report. Reports are written in Nepali OR English — Nepali text is NORMAL and legitimate, never reject a report just because it is not in English.\n\n"
            . "is_legitimate must be FALSE (reject) for: test/example/meaningless reports; personal or social chatter that is not a community issue (e.g. someone visiting a house, gossip, greetings, mood posts); vague statements with no incident, hazard, problem, event or actionable information; spam.\n"
            . "is_legitimate must be TRUE (approve) only for reports describing a real community issue: road damage, landslide, flood, fire, accident, waste, electricity/water outage, crime, missing person, animal hazards, events, lost/found, and similar.\n"
            . "When in doubt between legitimate and junk, choose junk (is_legitimate=false) — never approve a trivial report.\n"
            . "Return JSON: suggested_priority (low/medium/high/critical), is_legitimate (bool), is_duplicate (bool — true if same issue already reported), summary (string, max 2 sentences in English), category_match (bool — true if title/description matches the report category), category_reason (string), action (approve/reject).\n\n"
            . "If is_duplicate is true, action must be reject. If is_legitimate is true, action must be approve.\n\n{$text}"
        );

        $isDuplicate = $result['is_duplicate'] ?? false;
        $isLegitimate = $result['is_legitimate'] ?? true;

        $result['is_duplicate'] = $isDuplicate;
        $result['is_legitimate'] = $isLegitimate;
        $result['category_match'] = $result['category_match'] ?? null;
        $result['category_reason'] = $result['category_reason'] ?? '';
        $result['action'] = ($isDuplicate || !$isLegitimate) ? 'reject' : ($result['action'] ?? 'approve');

        return $result;
    }

    protected function checkLocation(Report $report): array
    {
        $lat = $report->latitude;
        $lng = $report->longitude;

        if ($lat === null || $lng === null) {
            return ['valid' => true, 'verifiable' => false, 'gps_status' => $report->gps_verification_status, 'reason' => 'No coordinates provided'];
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat == 0.0 && $lng == 0.0) {
            return ['valid' => false, 'verifiable' => true, 'gps_status' => $report->gps_verification_status, 'reason' => 'Placeholder coordinates (0,0) — location not real'];
        }

        if ($lat < self::NEPAL_BBOX['lat_min'] || $lat > self::NEPAL_BBOX['lat_max']
            || $lng < self::NEPAL_BBOX['lng_min'] || $lng > self::NEPAL_BBOX['lng_max']) {
            return ['valid' => false, 'verifiable' => true, 'gps_status' => $report->gps_verification_status, 'reason' => 'Coordinates outside Nepal bounds — likely fake'];
        }

        if ($report->gps_verification_status === 'mismatched') {
            return ['valid' => false, 'verifiable' => true, 'gps_status' => 'mismatched', 'reason' => 'Photo GPS does not match reported location — likely fake'];
        }

        $verifiable = $report->gps_verification_status === 'verified';

        return ['valid' => true, 'verifiable' => $verifiable, 'gps_status' => $report->gps_verification_status, 'reason' => 'Location looks valid'];
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

            // Pure-code EXIF trace (free — no AI call): real camera photos carry
            // Make/Model + lens data; screenshots/downloads usually carry none.
            $trace = $this->checkCameraTrace($path);
            $dims = @getimagesize($path);
            $width = $dims[0] ?? 0;
            $height = $dims[1] ?? 0;

            // Screenshot/download heuristic: no camera metadata + square web-size
            // dimensions = classic re-uploaded template/screenshot. Flag for human
            // review WITHOUT spending any AI quota.
            if ($trace['kind'] === 'no_metadata' && $width > 0 && $width === $height && $width < 800) {
                $images[] = [
                    'media_id' => $item->id,
                    'verdict' => 'suspicious',
                    'reason' => 'No camera metadata + square web-size dimensions — likely screenshot or downloaded image',
                    'exif_trace' => $trace,
                    'ai_skipped' => true,
                    'provider_used' => null,
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
                $images[] = $entry;
            } catch (AiRateLimitException $e) {
                Log::warning("Vision AI unavailable for report#{$report->id} media#{$item->id}: " . $e->getMessage());
                $images[] = [
                    'media_id' => $item->id,
                    'verdict' => 'unverifiable',
                    'reason' => 'AI providers all unavailable (quota/rate limit)',
                    'exif_trace' => $trace,
                    'provider_used' => null,
                ];
            } catch (\Throwable $e) {
                Log::error("Image analysis failed for report#{$report->id} media#{$item->id}: " . $e->getMessage());
                $images[] = [
                    'media_id' => $item->id,
                    'verdict' => 'unverifiable',
                    'reason' => 'Vision API error',
                    'exif_trace' => $trace,
                    'provider_used' => null,
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
     * 'no_metadata' (no EXIF at all), or 'unknown' (non-JPEG / no EXIF ext).
     * This is a WEAK signal — never a hard reject on its own.
     */
    protected function checkCameraTrace(string $path): array
    {
        if (!function_exists('exif_read_data')) {
            return ['kind' => 'unknown', 'reason' => 'EXIF extension missing on server'];
        }

        $mime = mime_content_type($path) ?: '';
        if (!in_array($mime, ['image/jpeg', 'image/jpg'])) {
            return ['kind' => 'unknown', 'reason' => 'Not a JPEG image (' . $mime . ')'];
        }

        $exif = @exif_read_data($path);
        if (!$exif || !is_array($exif)) {
            return ['kind' => 'no_metadata', 'reason' => 'No EXIF metadata at all'];
        }

        $make = trim((string) ($exif['Make'] ?? ''));
        $model = trim((string) ($exif['Model'] ?? ''));
        $software = trim((string) ($exif['Software'] ?? ''));
        $hasLens = isset($exif['FNumber']) || isset($exif['FocalLength'])
            || isset($exif['ExposureTime']) || isset($exif['ISOSpeedRatings']);

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

    protected function decideAction(array $text, array $location, array $image): string
    {
        if (($text['is_duplicate'] ?? false) || !($text['is_legitimate'] ?? true)) {
            return 'reject';
        }

        if (!($location['valid'] ?? true)) {
            return 'reject';
        }

        // Reports WITHOUT verified photo GPS are still fully processed:
        // missing EXIF data alone is not proof of a fake report, so a clean
        // text+image review can still approve. (Location invalid — 0,0,
        // outside Nepal, mismatched GPS — is rejected above/earlier.)

        // NEVER auto-approve on unverifiable/suspicious/duplicate images.
        if (in_array($image['verdict'] ?? 'clean', ['unverifiable', 'suspicious', 'duplicate'])) {
            return 'pending-review';
        }

        if (($image['verdict'] ?? 'clean') === 'violation') {
            return 'reject';
        }

        // NO-COMPROMISE GATE: a "clean" verdict must be data-backed. If the
        // vision model produced no usable numbers, or the image does not
        // strongly match the claim, a human decides — never silent approve.
        $images = $image['images'] ?? [];
        if (empty($images)) {
            return 'pending-review';
        }
        foreach ($images as $img) {
            if ($img['verdict'] !== 'clean') {
                return 'pending-review';
            }
            $realScene = (float) ($img['real_scene_probability'] ?? 0);
            $match = (float) ($img['report_match'] ?? 0);
            if ($realScene < 0.7 || $match < 0.6) {
                return 'pending-review';
            }
        }

        return 'approve';
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
        foreach (($img['images'] ?? []) as $i) {
            if (!empty($i['verdict_reason'])) {
                return 'AI: needs moderator review — ' . $i['verdict_reason'];
            }
        }
        return 'AI: needs moderator review — ' . ($img['message'] ?? $verdict);
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
