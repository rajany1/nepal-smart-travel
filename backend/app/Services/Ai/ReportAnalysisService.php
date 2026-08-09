<?php

namespace App\Services\Ai;

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

    private const NEPAL_BBOX = ['lat_min' => 26.0, 'lat_max' => 31.0, 'lng_min' => 79.5, 'lng_max' => 89.0];

    protected GroqService $groq;
    protected GeminiService $gemini;
    protected GroqService $visionGroq;
    protected GeminiService|GroqService $visionPrimary;
    protected GeminiService|GroqService $visionFallback;

    public function __construct(?GroqService $groq = null, ?GeminiService $gemini = null, ?GroqService $visionGroq = null)
    {
        $this->groq = $groq ?? new GroqService(config('services.ai.model', 'llama-3.3-70b-versatile'));
        $this->gemini = $gemini ?? new GeminiService(config('services.ai.vision_model', 'gemini-2.0-flash'));
        $this->visionGroq = $visionGroq ?? new GroqService(config('services.ai.vision_groq_model', 'qwen/qwen3.6-27b'));

        $this->visionPrimary = config('services.ai.vision_provider', 'gemini') === 'groq'
            ? $this->visionGroq
            : $this->gemini;
        $this->visionFallback = $this->visionPrimary === $this->gemini ? $this->visionGroq : $this->gemini;
    }

    public function process(Report $report): array
    {
        if ($report->status !== 'pending' || $report->ai_analyzed_at !== null) {
            return ['report_id' => $report->id, 'skipped' => true];
        }

        $analysis = $this->analyze($report);

        DB::transaction(function () use ($report, $analysis) {
            $action = $analysis['action'] ?? 'approve';
            $now = now();

            $report->update([
                'ai_analysis' => $analysis,
                'ai_analyzed_at' => $now,
                'status' => $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'pending'),
                'verified_at' => $now,
            ]);

            if (isset($analysis['suggested_priority']) && $analysis['suggested_priority'] !== $report->priority) {
                $report->update(['priority' => $analysis['suggested_priority']]);
            }

            ModerationQueue::where('content_type', 'report')
                ->where('content_id', $report->id)
                ->update([
                    'status' => $action === 'approve' ? 'approved' : ($action === 'reject' ? 'rejected' : 'pending'),
                    'reviewed_at' => $now,
                ]);

            if ($action === 'approve') {
                $this->awardApprovalXp($report);
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
                'image_check' => ['reviewed' => 0, 'images' => [], 'verdict' => 'clean', 'message' => 'Skipped — failed quality check'],
                'action' => 'reject',
            ]);
        }

        $text = $this->analyzeText($report);
        $text['quality_check'] = $quality;

        if (!$text['is_legitimate'] || $text['is_duplicate']) {
            return array_merge($text, [
                'location_check' => $this->checkLocation($report),
                'image_check' => ['reviewed' => 0, 'images' => [], 'verdict' => 'clean', 'message' => 'Skipped — text analysis rejected'],
                'action' => 'reject',
            ]);
        }

        $location = $this->checkLocation($report);

        if (!($location['valid'] ?? true)) {
            return array_merge($text, [
                'location_check' => $location,
                'image_check' => ['reviewed' => 0, 'images' => [], 'verdict' => 'clean', 'message' => 'Skipped — location invalid'],
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

        $result = $this->groq->generateJson(
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
            return ['valid' => true, 'verifiable' => true, 'gps_status' => 'mismatched', 'reason' => 'Photo GPS does not match reported location — flagged for moderator'];
        }

        return ['valid' => true, 'verifiable' => true, 'gps_status' => $report->gps_verification_status, 'reason' => 'Location looks valid'];
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

            try {
                $result = $this->visionPrimary->generateJsonWithImages(
                    $this->imagePrompt($report, $text),
                    [$path],
                    ['maxOutputTokens' => 900]
                );
                $images[] = $this->judgeImage($item->id, $result);
            } catch (\Exception $primaryError) {
                try {
                    $result = $this->visionFallback->generateJsonWithImages(
                        $this->imagePrompt($report, $text),
                        [$path],
                        ['maxOutputTokens' => 900]
                    );
                    $images[] = $this->judgeImage($item->id, $result);
                } catch (\Exception $fallbackError) {
                    Log::error("Image analysis failed for report#{$report->id} media#{$item->id}: Primary: " . $primaryError->getMessage() . " | Fallback: " . $fallbackError->getMessage());
                    $images[] = ['media_id' => $item->id, 'verdict' => 'skipped', 'reason' => 'Vision API error'];
                }
            }
        }

        $reviewed = collect($images)->whereIn('verdict', ['clean', 'suspicious', 'violation'])->count();

        if ($reviewed === 0) {
            return ['reviewed' => 0, 'images' => $images, 'verdict' => 'unverifiable', 'message' => 'Images attached but none could be analyzed — needs moderator review'];
        }

        $verdict = 'clean';
        foreach ($images as $img) {
            if ($img['verdict'] === 'violation') {
                $verdict = 'violation';
                break;
            }
            if ($img['verdict'] === 'suspicious' && $verdict === 'clean') {
                $verdict = 'suspicious';
            }
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
            . "- A photo is MISLEADING or fake if: it is AI-generated, it shows something unrelated to the claim (e.g. random objects, furniture, selfie, screenshot, stock photo), or it contradicts the description.\n"
            . "- Reports can be in Nepali or English — language of the photo text is never a violation.\n"
            . "Return JSON ONLY:\n"
            . "- \"is_ai_generated\" (bool — true if the image looks AI-generated/rendered)\n"
            . "- \"shows_what\" (string — what the photo actually shows)\n"
            . "- \"matches_title_description\" (bool — photo matches the claim)\n"
            . "- \"misleading\" (bool — photo shows something different from the claim or is deliberately misleading)\n"
            . "- \"inappropriate_abusive\" (bool — abusive, offensive, gali, or NSFW content)\n"
            . "- \"phishing\" (bool — scam/phishing/fake promotion content)\n"
            . "- \"confidence\" (number 0-1 — how confident you are about the flags above)\n"
            . "- \"summary\" (string, 1 sentence)";
    }

    protected function judgeImage(int $mediaId, array $result): array
    {
        $confidence = (float) ($result['confidence'] ?? 0);
        $reason = (string) ($result['summary'] ?? $result['shows_what'] ?? '');

        $entry = [
            'media_id' => $mediaId,
            'is_ai_generated' => (bool) ($result['is_ai_generated'] ?? false),
            'shows_what' => $result['shows_what'] ?? '',
            'matches_title_description' => $result['matches_title_description'] ?? null,
            'misleading' => (bool) ($result['misleading'] ?? false),
            'inappropriate_abusive' => (bool) ($result['inappropriate_abusive'] ?? false),
            'phishing' => (bool) ($result['phishing'] ?? false),
            'confidence' => $confidence,
            'reason' => $reason,
        ];

        if (($result['phishing'] ?? false) || ($result['inappropriate_abusive'] ?? false)) {
            $entry['verdict'] = 'violation';
            $entry['verdict_reason'] = 'Prohibited content (abusive/phishing)';
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

    protected function decideAction(array $text, array $location, array $image): string
    {
        if (($text['is_duplicate'] ?? false) || !($text['is_legitimate'] ?? true)) {
            return 'reject';
        }

        if (!($location['valid'] ?? true)) {
            return 'reject';
        }

        if (($image['verdict'] ?? 'clean') === 'violation') {
            return 'reject';
        }

        if (($image['verdict'] ?? 'clean') === 'unverifiable') {
            return 'pending-review';
        }

        if (($image['verdict'] ?? 'clean') === 'suspicious') {
            return 'pending-review';
        }

        return 'approve';
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
