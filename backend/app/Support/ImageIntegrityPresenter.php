<?php

namespace App\Support;

use App\Models\Report;
use App\Models\ReportMedia;
use Illuminate\Support\Collection;

/**
 * Admin-only presentation layer for stored image-screening / analysis data.
 *
 * Transforms data that ALREADY EXISTS on the report row (reports.ai_analysis
 * JSON, GPS verification fields, media hashes) into neutral, moderator-facing
 * indicator rows for the Admin -> Report Details "Image Integrity" section.
 *
 * Core principle: evidence, not conclusions.
 * - Every generated label/result/meaning is factual and non-accusatory.
 * - Unknown / unavailable is NEVER rendered as a negative verdict.
 * - Suspicious wording means "indicator detected / review recommended",
 *   never "fake", "fraud" or any definitive claim.
 * - Stored values from the existing pipeline (verdicts, reasons, duplicate
 *   results, Vision AI numbers) are surfaced VERBATIM as evidence — they are
 *   the system's existing states and are not reinterpreted here.
 *
 * This class performs ZERO analysis: no AI calls, no image reads, no
 * fingerprinting, no DB queries (the media relation is only read when it has
 * already been eager-loaded). It is a pure display transform.
 */
class ImageIntegrityPresenter
{
    /**
     * Human-readable labels for the deterministic screening signal ids
     * produced by ImageScreeningService.
     */
    private const SIGNAL_LABELS = [
        'known_screen_viewport_dimensions' => 'Screenshot viewport dimensions',
        'extreme_aspect_ratio' => 'Extreme aspect ratio',
        'screenshot_software_stamp' => 'Screenshot software stamp',
    ];

    /**
     * Build the full Image Integrity payload for one report.
     *
     * @return array{
     *     has_analysis: bool,
     *     analyzed_at: ?string,
     *     check_verdict: ?string,
     *     status: array{tone: string, text: string},
     *     indicators: array<int, array{label: string, result: string, tone: string, meaning: string}>,
     *     images: array<int, array{media_id: mixed, rows: array, technical: array}>,
     *     technical: array<int, array{label: string, value: string}>
     * }
     */
    public function build(Report $report): array
    {
        $analysis = is_array($report->ai_analysis) ? $report->ai_analysis : [];
        $imageCheck = is_array($analysis['image_check'] ?? null) ? $analysis['image_check'] : [];

        $entries = [];
        foreach ((array) ($imageCheck['images'] ?? []) as $entry) {
            if (is_array($entry)) {
                $entries[] = $entry;
            }
        }

        $indicators = array_merge(
            $this->cameraExifIndicator($entries, 'this report'),
            $this->gpsIndicators($report),
            $this->inAppCaptureIndicator($report),
            $this->screeningSignalIndicator($entries, 'this report', 'known_screen_viewport_dimensions', 'Screenshot viewport dimensions', 'The image dimensions match a known screen viewport. This is an indicator only and does not prove that the image is a screenshot.'),
            $this->screeningSignalIndicator($entries, 'this report', 'extreme_aspect_ratio', 'Extreme aspect ratio', 'A very long image ratio combined with no camera EXIF trace is an indicator only and does not prove that the image is a screenshot.'),
            $this->softwareStampIndicator($entries, 'this report'),
            $this->pixelAnalysisIndicator(),
            $this->duplicateIndicator($entries, 'exact'),
            $this->duplicateIndicator($entries, 'similar'),
            $this->visionStatusIndicator($entries),
            $this->booleanVisionIndicator($entries, 'is_ai_generated', 'AI-generated indicator', 'Stored Vision AI results flag image(s) as possibly AI-generated. This is an indicator only and does not prove the image is fake.'),
            $this->booleanVisionIndicator($entries, 'is_screen_photo', 'Screen-photo indicator', 'Stored Vision AI results flag image(s) as a photo of a screen or map view. This is an indicator only and requires human confirmation.'),
            $this->booleanVisionIndicator($entries, 'misleading', 'Misleading image indicator', 'Stored Vision AI results flag image(s) as possibly unrelated to or inconsistent with the report. This is an indicator only.'),
            $this->trustScoreIndicator($report, $imageCheck),
        );

        $media = $report->relationLoaded('media') ? $report->getRelation('media') : null;

        $images = [];
        foreach ($entries as $entry) {
            $images[] = $this->imageBlock($entry, $this->findMedia($media, $entry));
        }

        return [
            'has_analysis' => $analysis !== [],
            'analyzed_at' => $report->ai_analyzed_at?->toDateTimeString(),
            'check_verdict' => isset($imageCheck['verdict']) && is_string($imageCheck['verdict'])
                ? $imageCheck['verdict']
                : null,
            'status' => $this->overallStatus($indicators, $images, $entries),
            'indicators' => $indicators,
            'images' => $images,
            'technical' => $this->technicalRows($report, $analysis, $imageCheck, $entries),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Report-level indicators
    |--------------------------------------------------------------------------
    */

    private function cameraExifIndicator(array $entries, string $subject): array
    {
        $kinds = [];
        $unknownReason = '';
        $software = '';

        foreach ($entries as $entry) {
            $trace = $entry['exif_trace'] ?? null;
            if (! is_array($trace)) {
                continue;
            }
            $kind = $trace['kind'] ?? 'unknown';
            $kinds[] = $kind;
            if ($kind === 'screenshotish' && $software === '' && ! empty($trace['software'])) {
                $software = (string) $trace['software'];
            }
            if ($kind === 'unknown' && $unknownReason === '' && ! empty($trace['reason'])) {
                $unknownReason = (string) $trace['reason'];
            }
        }

        if ($kinds === []) {
            return [$this->row('Camera EXIF trace', 'Not evaluated', 'unknown',
                "No stored EXIF trace result is available for {$subject}.")];
        }

        if (in_array('camera', $kinds, true)) {
            return [$this->row('Camera EXIF trace', 'Detected', 'ok',
                'Camera metadata (make/model) is present in a stored EXIF trace result.')];
        }

        if (in_array('screenshotish', $kinds, true)) {
            return [$this->row('Camera EXIF trace', 'Suspicious signal detected', 'suspicious',
                'EXIF metadata is present, but a stored result records a software stamp instead of a camera trace'
                .($software !== '' ? ": {$software}" : '')
                .'. This is an indicator only and does not prove the image is a screenshot.')];
        }

        if (in_array('no_camera_trace', $kinds, true)) {
            return [$this->row('Camera EXIF trace', 'Not detected', 'unknown',
                'Metadata is present, but a stored result contains no camera make/model trace.')];
        }

        if (in_array('no_metadata', $kinds, true)) {
            return [$this->row('Camera EXIF trace', 'EXIF camera information unavailable', 'unknown',
                'Camera metadata was not available. Some cameras and apps remove metadata, so this alone does not prove external sourcing.')];
        }

        return [$this->row('Camera EXIF trace', 'Unknown', 'unknown',
            ($unknownReason !== '' ? $unknownReason : 'The file could not be inspected for EXIF metadata')
            .'. Metadata absence alone does not prove external sourcing.')];
    }

    private function gpsIndicators(Report $report): array
    {
        $hasGps = $report->photo_gps_lat !== null && $report->photo_gps_lng !== null;
        $gpsMeta = $hasGps
            ? $this->row('GPS metadata', 'Present', 'ok',
                "The stored photo record contains GPS coordinates ({$report->photo_gps_lat}, {$report->photo_gps_lng}).")
            : $this->row('GPS metadata', 'Unavailable', 'unknown',
                'No GPS metadata was captured with this photo. "Unavailable" is not the same as a mismatch.');

        $distance = $report->gps_distance_km;
        $distanceNote = $distance !== null ? " (stored distance: {$distance} km)" : '';

        $consistency = match ($report->gps_verification_status ?? 'none') {
            'verified' => $this->row('GPS consistency', 'Matches', 'ok',
                "Image GPS is within the configured tolerance of the report location{$distanceNote}."),
            'mismatched' => $this->row('GPS consistency', 'Mismatch detected', 'suspicious',
                "Image GPS metadata differs from the report location beyond the configured tolerance{$distanceNote}. This is not the same as missing GPS metadata."),
            'no_gps_data' => $this->row('GPS consistency', 'Unavailable', 'unknown',
                'The photo contained no GPS metadata, so consistency could not be checked. This is not the same as a mismatch.'),
            default => $this->row('GPS consistency', 'Not evaluated', 'unknown',
                'GPS consistency has not been evaluated for this report.'),
        };

        return [$gpsMeta, $consistency];
    }

    private function inAppCaptureIndicator(Report $report): array
    {
        if ($report->is_live_capture === null) {
            return [$this->row('In-app capture flag', 'Not evaluated', 'unknown',
                'No in-app capture flag is stored for this report.')];
        }

        if ($report->is_live_capture) {
            return [$this->row('In-app capture flag', 'Present', 'ok',
                'The upload was recorded by the app as an in-app camera capture (stored flag).')];
        }

        return [$this->row('In-app capture flag', 'Not detected', 'unknown',
            'The upload was not recorded by the app as an in-app camera capture (stored flag).')];
    }

    /**
     * Report-level row for one deterministic screening signal id, aggregated
     * across every stored per-image screening result.
     *
     * @param  string  $signalId  one of ImageScreeningService's signal ids
     * @param  string  $detectedMeaning  neutral explanation shown when detected
     */
    private function screeningSignalIndicator(array $entries, string $subject, string $signalId, string $label, string $detectedMeaning): array
    {
        $evaluated = 0;
        $detected = 0;
        $detail = '';

        foreach ($entries as $entry) {
            $screening = $entry['screening'] ?? null;
            if (! is_array($screening) || (($screening['evaluated'] ?? true) === false)) {
                continue;
            }
            $evaluated++;
            foreach ((array) ($screening['signals'] ?? []) as $signal) {
                if (is_array($signal) && ($signal['signal'] ?? null) === $signalId) {
                    $detected++;
                    if ($detail === '' && ! empty($signal['detail'])) {
                        $detail = trim((string) $signal['detail']);
                    }
                }
            }
        }

        if ($detected > 0) {
            return [$this->row($label, 'Detected', 'suspicious',
                ($detail !== '' ? "{$detail}. " : '').$detectedMeaning)];
        }

        if ($evaluated > 0) {
            return [$this->row($label, 'Not detected', 'ok',
                "The stored deterministic screening results contain no such signal for {$subject}.")];
        }

        return [$this->row($label, 'Not evaluated', 'unknown',
            "Deterministic screening results are not stored for {$subject}.")];
    }

    private function softwareStampIndicator(array $entries, string $subject): array
    {
        $label = self::SIGNAL_LABELS['screenshot_software_stamp'];
        $evaluated = 0;
        $uncertain = false;
        $detected = false;
        $detail = '';

        foreach ($entries as $entry) {
            $screening = $entry['screening'] ?? null;
            if (! is_array($screening) || (($screening['evaluated'] ?? true) === false)) {
                continue;
            }
            $evaluated++;
            if (($screening['facts']['camera_trace'] ?? null) === 'uncertain') {
                $uncertain = true;
            }
            foreach ((array) ($screening['signals'] ?? []) as $signal) {
                if (is_array($signal) && ($signal['signal'] ?? null) === 'screenshot_software_stamp') {
                    $detected = true;
                    if ($detail === '' && ! empty($signal['detail'])) {
                        $detail = trim((string) $signal['detail']);
                    }
                }
            }
        }

        if ($detected) {
            return [$this->row($label, 'Detected', 'suspicious',
                ($detail !== '' ? "{$detail}. " : '')
                .'An EXIF software stamp is an indicator only and does not prove that the image is a screenshot.')];
        }

        if ($evaluated === 0) {
            return [$this->row($label, 'Not evaluated', 'unknown',
                "Deterministic screening results are not stored for {$subject}.")];
        }

        if ($uncertain) {
            return [$this->row($label, 'Unknown', 'unknown',
                'EXIF could not be fully parsed for at least one image, so a screenshot software stamp could not be reliably checked.')];
        }

        return [$this->row($label, 'Not detected', 'ok',
            'The stored results contain no known screenshot software stamp.')];
    }

    /**
     * Pixel-level display-pattern rows (moire / pixel grid / bezel structure).
     *
     * These techniques are NOT implemented in this server environment (no
     * image-pixel decoding capability: no GD/Imagick, and hand-rolling a JPEG
     * decoder is deliberately out of scope), so they are always reported
     * honestly as "Unknown" — never as passed, never as a negative result.
     * Screen-photo semantics are assessed by the Vision AI image check when
     * it runs.
     */
    private function pixelAnalysisIndicator(): array
    {
        $meaning = 'Pixel-level display-pattern analysis (moire / pixel grid / bezel structure) was not evaluated: this server environment has no image-pixel decoding capability, so this technique is not implemented here. Screen-photo semantics are assessed by the Vision AI image check when it runs. "Unknown" is not a negative verdict.';

        return [
            $this->row('Moiré / display pattern', 'Unknown', 'unknown', $meaning),
            $this->row('Display structure', 'Unknown', 'unknown', $meaning),
        ];
    }

    private function duplicateIndicator(array $entries, string $kind): array
    {
        $isExact = $kind === 'exact';
        $label = $isExact ? 'Exact duplicate' : 'Similar image';
        $matches = [];
        $checked = 0;

        foreach ($entries as $entry) {
            if (($entry['verdict'] ?? null) === 'skipped') {
                continue; // image never reached the duplicate checks
            }
            if (! isset($entry['verdict'])) {
                continue;
            }
            $checked++;
            if (($entry['verdict'] ?? null) !== 'duplicate') {
                continue;
            }
            $reason = trim((string) ($entry['reason'] ?? ''));
            $isFingerprint = str_starts_with($reason, 'Fingerprint match');
            if ($isFingerprint === ! $isExact) {
                $matches[] = $reason;
            }
        }

        if ($matches !== []) {
            return [$this->row($label, 'Suspicious signal detected', 'suspicious',
                implode(' ', $matches)
                .' Existing stored duplicate-check result — verify the referenced report before acting.')];
        }

        if ($checked > 0) {
            return [$this->row($label, 'Not found', 'ok', $isExact
                ? 'The stored duplicate check ran and found no identical image used by another recent report.'
                : 'The stored duplicate check ran and found no fingerprint-similar image from another recent report.')];
        }

        return [$this->row($label, 'Not evaluated', 'unknown',
            'The duplicate check has not run for this report — no analyzable image results are stored.')];
    }

    private function visionStatusIndicator(array $entries): array
    {
        $ran = 0;
        $skipped = 0;
        $unavailable = 0;
        $providers = [];

        foreach ($entries as $entry) {
            if (! empty($entry['ai_skipped'])) {
                $skipped++;

                continue;
            }
            $hasVision = isset($entry['screen_probability']) || isset($entry['report_match']);
            if ($hasVision && in_array($entry['verdict'] ?? null, ['clean', 'suspicious', 'violation'], true)) {
                $ran++;
                if (! empty($entry['provider_used'])) {
                    $providers[] = (string) $entry['provider_used'];
                }
            } elseif (in_array($entry['verdict'] ?? null, ['duplicate', 'unverifiable'], true)) {
                if ($entry['verdict'] === 'duplicate') {
                    $skipped++;
                } else {
                    $unavailable++;
                }
            }
        }

        if ($ran > 0) {
            $providerNote = $providers !== [] ? ' (provider: '.implode(', ', array_unique($providers)).')' : '';
            $meaning = "Stored Vision AI results exist for {$ran} image(s){$providerNote}.";
            if ($skipped > 0) {
                $meaning .= " {$skipped} additional image(s) were handled by deterministic checks instead.";
            }

            return [$this->row('Vision AI analysis', 'Completed', 'ok', $meaning)];
        }

        if ($skipped > 0) {
            return [$this->row('Vision AI analysis', 'Skipped', 'info',
                "Vision AI was not called for {$skipped} image(s) because deterministic or duplicate checks flagged them first. Skipping is by design and is not a verdict.")];
        }

        if ($unavailable > 0) {
            return [$this->row('Vision AI analysis', 'Unavailable', 'unknown',
                'Stored results record that Vision AI could not be completed (provider quota/rate limit or error).')];
        }

        return [$this->row('Vision AI analysis', 'Not evaluated', 'unknown',
            'No stored Vision AI result exists for this report.')];
    }

    /**
     * Aggregate a boolean Vision AI flag (is_ai_generated / is_screen_photo /
     * misleading) across all stored vision entries.
     */
    private function booleanVisionIndicator(array $entries, string $key, string $label, string $detectedMeaning): array
    {
        $withKey = 0;
        $flagged = 0;

        foreach ($entries as $entry) {
            if (! array_key_exists($key, $entry)) {
                continue;
            }
            $withKey++;
            if ($entry[$key] === true) {
                $flagged++;
            }
        }

        if ($withKey === 0) {
            return [$this->row($label, 'Not evaluated', 'unknown',
                'No stored Vision AI result is available to evaluate this indicator.')];
        }

        if ($flagged > 0) {
            return [$this->row($label, 'Indicator detected', 'suspicious',
                "{$detectedMeaning} (flagged in {$flagged} of {$withKey} stored result(s).)")];
        }

        return [$this->row($label, 'Not detected', 'ok',
            'Stored Vision AI results did not flag this indicator.')];
    }

    /**
     * The stored combined authenticity score, labelled honestly so it can
     * never be mistaken for a Vision AI image-confidence value: it blends
     * text (35%), location (25%) and image (40%) components, and when the
     * Vision AI image check was not evaluated the image component is a
     * neutral placeholder rather than measured image evidence.
     */
    private function trustScoreIndicator(Report $report, array $imageCheck): array
    {
        if ($report->authenticity_score === null) {
            return [$this->row('Overall stored confidence', 'Not evaluated', 'unknown',
                'No combined authenticity score has been stored for this report.')];
        }

        $percent = (int) round((float) $report->authenticity_score * 100);

        $imageVerdict = $imageCheck['verdict'] ?? null;
        $imageEvaluated = $imageVerdict !== null
            && in_array($imageVerdict, ['clean', 'suspicious', 'violation', 'duplicate'], true);

        $meaning = 'Stored combined score across the evaluated checks — text 35%, location 25%, image 40% (0–100%). '
            .'It is NOT a Vision AI image-confidence value and never replaces human review.';

        if (! $imageEvaluated) {
            $meaning .= ' The image check was not evaluated for this report, so its component is a neutral placeholder rather than measured image evidence.';
        }

        return [$this->row('Overall stored confidence', "{$percent}%", 'info', $meaning)];
    }

    /*
    |--------------------------------------------------------------------------
    | Per-image blocks
    |--------------------------------------------------------------------------
    */

    private function imageBlock(array $entry, ?ReportMedia $media): array
    {
        $subject = 'this image';
        $reason = trim((string) ($entry['verdict_reason'] ?? $entry['reason'] ?? ''));

        $rows = [
            $this->storedVerdictRow($entry['verdict'] ?? null, $reason),
            ...$this->cameraExifIndicator([$entry], $subject),
            ...$this->screeningSignalIndicator([$entry], $subject, 'known_screen_viewport_dimensions', 'Screenshot viewport dimensions', 'The image dimensions match a known screen viewport. This is an indicator only and does not prove that the image is a screenshot.'),
            ...$this->screeningSignalIndicator([$entry], $subject, 'extreme_aspect_ratio', 'Extreme aspect ratio', 'A very long image ratio combined with no camera EXIF trace is an indicator only and does not prove that the image is a screenshot.'),
            ...$this->softwareStampIndicator([$entry], $subject),
            $this->visionRow($entry),
            ...$this->perImageVisionRows($entry),
        ];

        return [
            'media_id' => $entry['media_id'] ?? null,
            'rows' => $rows,
            'technical' => $this->imageTechnicalRows($entry, $media),
        ];
    }

    private function storedVerdictRow(mixed $verdict, string $reason): array
    {
        $meaning = $reason !== '' ? $reason : null;

        return match ($verdict) {
            'clean' => $this->row('Stored verdict', 'Clean', 'ok',
                $meaning ?? 'The stored analysis did not flag this image.'),
            'suspicious' => $this->row('Stored verdict', 'Suspicious signal detected', 'suspicious',
                $meaning ?? 'The stored analysis flagged this image for moderator review.'),
            'violation' => $this->row('Stored verdict', 'Violation flagged', 'suspicious',
                $meaning ?? 'The stored analysis flagged a policy concern for this image.'),
            'duplicate' => $this->row('Stored verdict', 'Duplicate signal detected', 'suspicious',
                $meaning ?? 'The stored duplicate check flagged this image.'),
            'unverifiable' => $this->row('Stored verdict', 'Unknown', 'unknown',
                $meaning ?? 'No usable stored result exists for this image.'),
            'skipped' => $this->row('Stored verdict', 'Not evaluated', 'unknown',
                $meaning ?? 'This image was not evaluated.'),
            default => $this->row('Stored verdict', 'Not evaluated', 'unknown',
                'No stored verdict exists for this image.'),
        };
    }

    private function visionRow(array $entry): array
    {
        if (! empty($entry['ai_skipped'])) {
            return $this->row('Vision AI', 'Skipped', 'info',
                'Deterministic checks flagged this image before Vision AI — the model was not called for it.'
                .(isset($entry['reason']) ? ' Stored result: '.$entry['reason'].'.' : ''));
        }

        if (isset($entry['screen_probability']) || isset($entry['report_match'])) {
            $screen = isset($entry['screen_probability']) ? round(((float) $entry['screen_probability']) * 100).'%' : 'n/a';
            $real = isset($entry['real_scene_probability']) ? round(((float) $entry['real_scene_probability']) * 100).'%' : 'n/a';
            $match = isset($entry['report_match']) ? round(((float) $entry['report_match']) * 100).'%' : 'n/a';

            return $this->row('Vision AI', 'Completed', 'ok',
                "Stored result: screen-photo {$screen} · real scene {$real} · report match {$match}.");
        }

        $verdict = $entry['verdict'] ?? null;
        if ($verdict === 'duplicate') {
            return $this->row('Vision AI', 'Skipped', 'info',
                'Duplicate check flagged this image before Vision AI — the model was not called for it.');
        }

        if ($verdict === 'unverifiable') {
            return $this->row('Vision AI', 'Unavailable', 'unknown',
                isset($entry['reason']) ? (string) $entry['reason'] : 'Stored result records that Vision AI was unavailable for this image.');
        }

        if ($verdict === 'skipped') {
            return $this->row('Vision AI', 'Not evaluated', 'unknown',
                isset($entry['reason']) ? (string) $entry['reason'] : 'This image did not reach the Vision AI step.');
        }

        return $this->row('Vision AI', 'Not evaluated', 'unknown',
            'No stored Vision AI result exists for this image.');
    }

    /**
     * Per-image rows for the stored Vision AI flags/numbers (only rendered
     * when vision fields actually exist on the entry).
     *
     * @return array<int, array{label: string, result: string, tone: string, meaning: string}>
     */
    private function perImageVisionRows(array $entry): array
    {
        $hasVision = isset($entry['screen_probability']) || isset($entry['report_match'])
            || array_key_exists('is_ai_generated', $entry);

        if (! $hasVision) {
            return [
                $this->row('AI-generated indicator', 'Not evaluated', 'unknown',
                    'No stored Vision AI result is available for this image.'),
                $this->row('Title/description match', 'Not evaluated', 'unknown',
                    'No stored Vision AI result is available for this image.'),
                $this->row('AI confidence', 'Not evaluated', 'unknown',
                    'No stored Vision AI confidence is available for this image.'),
            ];
        }

        $rows = [];

        $rows[] = array_key_exists('is_ai_generated', $entry) && $entry['is_ai_generated'] === true
            ? $this->row('AI-generated indicator', 'Indicator detected', 'suspicious',
                'The stored Vision AI result flags this image as possibly AI-generated. This is an indicator only and does not prove the image is fake.')
            : $this->row('AI-generated indicator', 'Not detected', 'ok',
                'The stored Vision AI result did not flag AI generation for this image.');

        $match = $entry['matches_title_description'] ?? null;
        $rows[] = match ($match) {
            true => $this->row('Title/description match', 'Yes', 'ok',
                'The stored Vision AI result reports that the image matches the report title/description.'),
            false => $this->row('Title/description match', 'No', 'suspicious',
                'The stored Vision AI result reports that the image does not match the report title/description. This is an indicator only — confirm visually.'),
            default => $this->row('Title/description match', 'Unknown', 'unknown',
                'The stored Vision AI result does not include a title/description match value.'),
        };

        if (isset($entry['confidence'])) {
            $rows[] = $this->row('AI confidence', round(((float) $entry['confidence']) * 100).'%', 'info',
                'Stored Vision AI confidence for this image (0–100%).');
        } else {
            $rows[] = $this->row('AI confidence', 'Not evaluated', 'unknown',
                'No stored Vision AI confidence is available for this image.');
        }

        return $rows;
    }

    /*
    |--------------------------------------------------------------------------
    | Technical details (collapsible)
    |--------------------------------------------------------------------------
    */

    private function imageTechnicalRows(array $entry, ?ReportMedia $media): array
    {
        $rows = [];

        if (isset($entry['media_id'])) {
            $rows[] = ['label' => 'Media ID', 'value' => (string) $entry['media_id']];
        }

        if ($media instanceof ReportMedia) {
            if ($media->media_hash) {
                $rows[] = ['label' => 'SHA-256 (file hash)', 'value' => (string) $media->media_hash];
            }
            if ($media->fingerprint_hash) {
                $rows[] = ['label' => 'Fingerprint hash', 'value' => (string) $media->fingerprint_hash];
            }
        }

        $screening = $entry['screening'] ?? null;
        if (is_array($screening) && (($screening['evaluated'] ?? true) !== false)) {
            $facts = is_array($screening['facts'] ?? null) ? $screening['facts'] : [];
            if (isset($facts['mime'])) {
                $rows[] = ['label' => 'MIME type', 'value' => (string) $facts['mime']];
            }
            if (isset($facts['aspect_ratio'])) {
                $rows[] = ['label' => 'Aspect ratio', 'value' => (string) $facts['aspect_ratio']];
            }
            if (array_key_exists('viewport_match', $facts)) {
                $rows[] = ['label' => 'Viewport match', 'value' => $facts['viewport_match'] ? 'yes' : 'no'];
            }
            if (isset($facts['camera_trace'])) {
                $rows[] = ['label' => 'Camera trace (resolved)', 'value' => (string) $facts['camera_trace']];
            }
            $rows[] = ['label' => 'Deterministic screening strong', 'value' => ! empty($screening['strong']) ? 'yes' : 'no'];
            foreach ((array) ($screening['signals'] ?? []) as $signal) {
                if (is_array($signal) && isset($signal['signal'])) {
                    $rows[] = [
                        'label' => 'Screening signal',
                        'value' => $signal['signal'].(isset($signal['detail']) ? ' — '.$signal['detail'] : ''),
                    ];
                }
            }
        }

        $trace = $entry['exif_trace'] ?? null;
        if (is_array($trace)) {
            foreach (['kind', 'make', 'model', 'software', 'has_lens_data', 'reason'] as $key) {
                if (isset($trace[$key]) && $trace[$key] !== '' && $trace[$key] !== null) {
                    $rows[] = [
                        'label' => 'EXIF '.$key,
                        'value' => is_bool($trace[$key]) ? ($trace[$key] ? 'yes' : 'no') : (string) $trace[$key],
                    ];
                }
            }
        }

        if (array_key_exists('ai_skipped', $entry)) {
            $rows[] = ['label' => 'AI skipped', 'value' => $entry['ai_skipped'] ? 'yes' : 'no'];
        }
        if (array_key_exists('provider_used', $entry)) {
            $rows[] = ['label' => 'Vision provider', 'value' => (string) ($entry['provider_used'] ?? 'none')];
        }
        if (isset($entry['shows_what']) && $entry['shows_what'] !== '') {
            $rows[] = ['label' => 'Vision AI shows_what', 'value' => (string) $entry['shows_what']];
        }
        if (isset($entry['verdict'])) {
            $rows[] = ['label' => 'Stored verdict (raw)', 'value' => (string) $entry['verdict']];
        }

        return $rows;
    }

    private function technicalRows(Report $report, array $analysis, array $imageCheck, array $entries): array
    {
        $rows = [
            ['label' => 'Analysis stored', 'value' => $analysis !== [] ? 'yes' : 'no'],
            ['label' => 'Analyzed at', 'value' => $report->ai_analyzed_at?->toDateTimeString() ?? '—'],
        ];

        if (isset($imageCheck['verdict']) && is_string($imageCheck['verdict'])) {
            $rows[] = ['label' => 'Image check verdict (raw)', 'value' => $imageCheck['verdict']];
        }
        if (isset($imageCheck['message']) && is_string($imageCheck['message'])) {
            $rows[] = ['label' => 'Image check message (raw)', 'value' => $imageCheck['message']];
        }
        if (isset($analysis['action']) && is_string($analysis['action'])) {
            $rows[] = ['label' => 'AI suggested action (raw)', 'value' => $analysis['action']];
        }

        $rows[] = ['label' => 'GPS verification status (raw)', 'value' => (string) ($report->gps_verification_status ?? 'none')];
        $rows[] = ['label' => 'GPS distance km (raw)', 'value' => (string) ($report->gps_distance_km ?? '—')];
        $rows[] = ['label' => 'In-app live capture flag (raw)', 'value' => $report->is_live_capture ? 'yes' : 'no'];
        $rows[] = ['label' => 'Photo captured at', 'value' => (string) ($report->photo_captured_at ?? '—')];

        $screeningStored = 0;
        foreach ($entries as $entry) {
            $result = $entry['screening'] ?? null;
            if (is_array($result) && (($result['evaluated'] ?? true) !== false)) {
                $screeningStored++;
            }
        }
        $rows[] = ['label' => 'Per-image screening stored', 'value' => "{$screeningStored} of ".count($entries).' image result(s)'];

        return $rows;
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function overallStatus(array $indicators, array $images, array $entries): array
    {
        $suspicious = 0;
        $ok = 0;
        foreach ($indicators as $row) {
            if (($row['tone'] ?? '') === 'suspicious') {
                $suspicious++;
            }
            if (($row['tone'] ?? '') === 'ok') {
                $ok++;
            }
        }
        foreach ($images as $block) {
            foreach ($block['rows'] ?? [] as $row) {
                if (($row['tone'] ?? '') === 'suspicious') {
                    $suspicious++;
                }
                if (($row['tone'] ?? '') === 'ok') {
                    $ok++;
                }
            }
        }

        if ($suspicious > 0) {
            return [
                'tone' => 'suspicious',
                'text' => "Manual review recommended — {$suspicious} suspicious indicator(s) detected. Indicators are evidence, not proof.",
            ];
        }

        if ($entries !== [] && $ok > 0) {
            return [
                'tone' => 'ok',
                'text' => 'No suspicious indicators detected in the stored image results. Indicators are evidence, not proof — the final decision is human.',
            ];
        }

        return [
            'tone' => 'unknown',
            'text' => $entries === []
                ? 'Not evaluated — no stored image analysis is available for this report.'
                : 'Not evaluated — the stored image results contain no evaluated indicator.',
        ];
    }

    private function findMedia(mixed $media, array $entry): ?ReportMedia
    {
        if (! $media instanceof Collection || ! isset($entry['media_id'])) {
            return null;
        }

        return $media->first(fn (ReportMedia $m) => $m->id === $entry['media_id']) ?? null;
    }

    /**
     * @param  string  $tone  ok | suspicious | unknown | info
     */
    private function row(string $label, string $result, string $tone, string $meaning): array
    {
        return [
            'label' => $label,
            'result' => $result,
            'tone' => $tone,
            'meaning' => $meaning,
        ];
    }
}
