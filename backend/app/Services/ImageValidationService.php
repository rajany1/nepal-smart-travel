<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Server-side image validation for report uploads.
 *
 * This service enforces security controls that cannot be bypassed by
 * a modified/cracked APK. It does NOT trust any client-provided metadata.
 *
 * TRUST MODEL:
 * - HARD REJECT: Invalid/malicious files that must never enter the system.
 * - SOFT SIGNAL: Weak indicators that influence trust scoring but do NOT
 *   determine camera provenance. These are logged for audit but never
 *   used to auto-approve or auto-reject legitimate camera photos.
 *
 * LIMITATIONS (documented honestly):
 * - Camera provenance cannot be cryptographically proven after the image
 *   reaches the server. Android does not provide reliable camera-origin
 *   attestation for this use case.
 * - EXIF data can be forged by a sophisticated attacker.
 * - Screenshot dimensions overlap with legitimate camera resolutions
 *   (e.g., 1920x1080 is both a common screenshot AND camera resolution).
 * - Without GD/Imagick, we cannot compute proper perceptual hashes.
 * - We can DETECT suspicious patterns but cannot GUARANTEE a photo is
 *   live-captured. The trust gate is human moderation, not image analysis.
 */
class ImageValidationService
{
    // --- HARD REJECT: Dimension limits ---
    private const MIN_WIDTH = 200;
    private const MIN_HEIGHT = 200;
    private const MAX_WIDTH = 8192;
    private const MAX_HEIGHT = 8192;

    // --- HARD REJECT: File size ---
    private const MAX_FILE_BYTES = 5 * 1024 * 1024; // 5 MB
    private const MIN_FILE_BYTES = 10 * 1024; // 10 KB — anything smaller is suspicious

    // --- HARD REJECT: Allowed MIME types (verified via finfo, NOT client-provided) ---
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    // --- SOFT SIGNAL: Duplicate detection ---
    private const HASH_LOOKBACK_DAYS = 60;
    private const FINGERPRINT_HAMMING_THRESHOLD = 8;

    // --- SOFT SIGNAL: Repeat offender threshold (configurable via GameSetting) ---
    private const DEFAULT_REPEAT_OFFENDER_THRESHOLD = 10;
    private const REPEAT_OFFENDER_WINDOW_HOURS = 24;

    /**
     * Validate an uploaded image file against all server-side rules.
     *
     * Returns:
     * - valid: bool — true if the file passes all HARD REJECT checks
     * - errors: string[] — hard rejection reasons (file never enters system)
     * - warnings: string[] — soft signals (influence trust, never block)
     * - metadata: array — extracted metadata for audit trail
     *
     * SOFT SIGNALS (warnings) do NOT determine camera provenance.
     * They are logged for audit and influence the 'provenance' field,
     * but they never auto-reject a legitimate camera photo.
     */
    public function validate(UploadedFile $file): array
    {
        $errors = [];
        $warnings = [];
        $metadata = [];

        // === HARD REJECT CHECKS ===

        // 1. File exists and is readable
        $realPath = $file->getRealPath();
        if ($realPath === false || !is_readable($realPath)) {
            return $this->reject('File is not readable or does not exist.');
        }

        // 2. File size checks
        $fileSize = filesize($realPath);
        $metadata['file_size_bytes'] = $fileSize;

        if ($fileSize < self::MIN_FILE_BYTES) {
            $errors[] = "File too small ({$fileSize} bytes). Minimum: " . self::MIN_FILE_BYTES . " bytes.";
        }
        if ($fileSize > self::MAX_FILE_BYTES) {
            $errors[] = "File too large ({$fileSize} bytes). Maximum: " . self::MAX_FILE_BYTES . " bytes.";
        }

        // 3. MIME type verification via finfo (server-side, NOT client-provided)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $actualMime = $finfo->buffer(file_get_contents($realPath));
        $metadata['detected_mime'] = $actualMime;

        if (!in_array($actualMime, self::ALLOWED_MIMES, true)) {
            $errors[] = "Invalid image type '{$actualMime}'. Only JPEG, PNG, and WebP are allowed.";
            return $this->reject('Invalid image type.', $errors, $warnings, $metadata);
        }

        // 4. Extension cross-check — warn if extension doesn't match actual MIME
        $clientExtension = strtolower($file->getClientOriginalExtension());
        $expectedExtensions = match ($actualMime) {
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
            default => [],
        };
        if (!empty($clientExtension) && !in_array($clientExtension, $expectedExtensions, true)) {
            $warnings[] = "File extension '{$clientExtension}' does not match detected type '{$actualMime}'.";
        }

        // 5. Image dimensions via getimagesize (works without GD)
        $dims = @getimagesize($realPath);
        if ($dims === false) {
            $errors[] = 'Could not read image dimensions — file may be corrupted or disguised.';
            return $this->reject('Could not read image dimensions.', $errors, $warnings, $metadata);
        }

        $width = (int) $dims[0];
        $height = (int) $dims[1];
        $metadata['width'] = $width;
        $metadata['height'] = $height;
        $metadata['bits'] = $dims['bits'] ?? null;
        $metadata['channels'] = $dims['channels'] ?? null;

        if ($width < self::MIN_WIDTH || $height < self::MIN_HEIGHT) {
            $errors[] = "Image too small ({$width}x{$height}). Minimum: " . self::MIN_WIDTH . "x" . self::MIN_HEIGHT . ".";
        }
        if ($width > self::MAX_WIDTH || $height > self::MAX_HEIGHT) {
            $errors[] = "Image too large ({$width}x{$height}). Maximum: " . self::MAX_WIDTH . "x" . self::MAX_HEIGHT . ".";
        }

        // === SOFT SIGNAL CHECKS ===
        // These do NOT reject the file. They log warnings that influence
        // the 'provenance' field but never block a legitimate camera photo.
        // Screenshot-like dimensions are NOT a reliable signal because
        // legitimate phone cameras produce 1920x1080, 1280x720, etc.

        // 6. Camera EXIF trace (weak signal, logged but not a hard reject)
        $trace = $this->checkCameraTrace($realPath, $actualMime);
        $metadata['camera_trace'] = $trace;

        if ($trace['kind'] === 'no_metadata') {
            $warnings[] = 'No camera EXIF metadata — this is a weak signal, not proof of a non-camera image.';
            $metadata['no_exif_signal'] = true;
        }

        if ($trace['kind'] === 'screenshotish') {
            $warnings[] = 'Software stamp detected in EXIF (no camera trace) — weak signal.';
            $metadata['software_stamp_signal'] = true;
        }

        // 7. Animated image detection (animated WebP — unusual for camera captures)
        if ($actualMime === 'image/webp') {
            $isAnimated = $this->isAnimatedWebp($realPath);
            $metadata['is_animated_webp'] = $isAnimated;
            if ($isAnimated) {
                $warnings[] = 'Animated WebP detected — unusual for camera captures.';
            }
        }

        // 8. Compute content hash for exact duplicate detection (SHA-256)
        $metadata['content_hash'] = hash_file('sha256', $realPath);

        // 9. Compute a simple fingerprint hash for near-duplicate detection
        $metadata['fingerprint_hash'] = $this->computeFingerprint($realPath, $actualMime);

        $valid = empty($errors);

        return [
            'valid' => $valid,
            'errors' => $errors,
            'warnings' => $warnings,
            'metadata' => $metadata,
        ];
    }

    /**
     * Check for exact duplicate images synchronously at upload time.
     *
     * HARD REJECT: Exact SHA-256 match (same file bytes) — blocks submission.
     * SOFT SIGNAL: Fingerprint near-duplicate — logged as warning, does NOT block.
     *
     * Limitation: Without GD/Imagick, we cannot compute proper perceptual
     * hashes. A determined attacker can resize/recompress to bypass the
     * fingerprint check. The real protection is that user reports always
     * require human moderation (never auto-approved).
     *
     * @param UploadedFile $file
     * @param int $excludeReportId Report ID to exclude (for updates)
     * @return array{duplicate: bool, match_type: string|null, matched_report: int|null, content_hash: string, fingerprint_hash: string}
     */
    public function checkDuplicates(UploadedFile $file, int $excludeReportId = 0): array
    {
        $realPath = $file->getRealPath();
        $contentHash = hash_file('sha256', $realPath);
        $fingerprint = $this->computeFingerprint($realPath, '');

        // HARD REJECT: Exact SHA-256 match (last N days, user-submitted reports only)
        $exactMatch = DB::table('report_media')
            ->join('reports', 'reports.id', '=', 'report_media.report_id')
            ->where('report_media.media_hash', $contentHash)
            ->where('report_media.report_id', '!=', $excludeReportId)
            ->where('reports.created_at', '>=', now()->subDays(self::HASH_LOOKBACK_DAYS))
            ->where('reports.source', 'user')
            ->first(['report_media.report_id']);

        if ($exactMatch) {
            return [
                'duplicate'       => true,
                'match_type'      => 'exact',
                'matched_report'  => (int) $exactMatch->report_id,
                'content_hash'    => $contentHash,
                'fingerprint_hash'=> $fingerprint,
            ];
        }

        // SOFT SIGNAL: Fingerprint near-duplicate (logged but does NOT block)
        if ($fingerprint) {
            $recent = DB::table('report_media')
                ->join('reports', 'reports.id', '=', 'report_media.report_id')
                ->whereNotNull('report_media.fingerprint_hash')
                ->where('report_media.report_id', '!=', $excludeReportId)
                ->where('reports.created_at', '>=', now()->subDays(self::HASH_LOOKBACK_DAYS))
                ->where('reports.source', 'user')
                ->pluck('report_media.fingerprint_hash', 'report_media.report_id');

            foreach ($recent as $recentId => $recentFp) {
                $distance = $this->hammingDistance($fingerprint, $recentFp);
                if ($distance <= self::FINGERPRINT_HAMMING_THRESHOLD) {
                    // Soft signal: log but do NOT block
                    return [
                        'duplicate'       => false, // NOT blocking
                        'match_type'      => 'fingerprint_near_duplicate',
                        'matched_report'  => (int) $recentId,
                        'content_hash'    => $contentHash,
                        'fingerprint_hash'=> $fingerprint,
                        'warning'         => "Fingerprint near-match with report #{$recentId} — logged as soft signal.",
                    ];
                }
            }
        }

        return [
            'duplicate'       => false,
            'match_type'      => null,
            'matched_report'  => null,
            'content_hash'    => $contentHash,
            'fingerprint_hash'=> $fingerprint,
        ];
    }

    /**
     * Pure-code EXIF camera-trace classification (no AI cost).
     * Mirrors ReportAnalysisService::checkCameraTrace but runs at upload time.
     */
    private function checkCameraTrace(string $path, string $mime): array
    {
        if (!function_exists('exif_read_data')) {
            return ['kind' => 'unknown', 'reason' => 'EXIF extension missing on server'];
        }

        if (!in_array($mime, ['image/jpeg', 'image/jpg'], true)) {
            return ['kind' => 'unknown', 'reason' => 'Not a JPEG (' . $mime . ')'];
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

    /**
     * Detect animated WebP by scanning for the ANMF chunk.
     */
    private function isAnimatedWebp(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if (!$handle) return false;

        // Read first 20 bytes: RIFF header + size + WEBP signature
        $header = fread($handle, 20);
        if (strlen($header) < 20 || substr($header, 0, 4) !== 'RIFF' || substr($header, 8, 4) !== 'WEBP') {
            fclose($handle);
            return false;
        }

        // Scan for ANMF chunk (animated WebP frame) within first 1MB
        $chunk = fread($handle, 1024 * 1024);
        fclose($handle);

        return str_contains($chunk, 'ANMF');
    }

    /**
     * Compute a coarse file fingerprint by sampling fixed byte offsets.
     * Used for rough near-duplicate detection without GD/Imagick.
     *
     * Limitation: This is a byte-level fingerprint, NOT a perceptual hash.
     * It will NOT detect resized or re-compressed versions of the same image.
     * It WILL detect identical bytes (e.g., same file saved with different name).
     *
     * @return string hex fingerprint (64 chars)
     */
    private function computeFingerprint(string $path, string $mime): string
    {
        $size = filesize($path);
        if ($size === false || $size < 64) {
            return hash_file('sha256', $path);
        }

        // Sample 32 bytes at evenly-spaced offsets through the file
        $samples = '';
        $step = max(1, intdiv($size, 33));
        $handle = @fopen($path, 'rb');
        if (!$handle) {
            return hash_file('sha256', $path);
        }

        for ($i = 0; $i < 32; $i++) {
            $offset = $i * $step;
            if (fseek($handle, $offset, SEEK_SET) === 0) {
                $samples .= fread($handle, 1);
            }
        }
        fclose($handle);

        return hash('sha256', $samples);
    }

    /**
     * Log a suspicious submission to the security audit log.
     */
    public function logSuspicious(
        int $userId,
        ?int $reportId,
        string $reason,
        array $metadata,
        ?string $ip = null,
        ?string $userAgent = null,
    ): void {
        try {
            DB::table('report_security_logs')->insert([
                'user_id' => $userId,
                'report_id' => $reportId,
                'reason' => $reason,
                'metadata' => json_encode($metadata),
                'ip_address' => $ip,
                'user_agent' => substr((string) $userAgent, 0, 500),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to write security log: ' . $e->getMessage());
        }
    }

    /**
     * Check if a user has too many recent rejected/suspicious submissions.
     * Returns true if the user should be temporarily blocked.
     *
     * Threshold is configurable via GameSetting 'report_repeat_offender_threshold'
     * (default: 10 per 24 hours). This is deliberately generous to avoid
     * blocking legitimate emergency reporters.
     */
    public function isRepeatOffender(int $userId): bool
    {
        try {
            $threshold = (int) \App\Models\GameSetting::getValue(
                'report_repeat_offender_threshold',
                self::DEFAULT_REPEAT_OFFENDER_THRESHOLD
            );

            $recentFlags = DB::table('report_security_logs')
                ->where('user_id', $userId)
                ->where('created_at', '>=', now()->subHours(self::REPEAT_OFFENDER_WINDOW_HOURS))
                ->count();

            return $recentFlags >= $threshold;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function reject(string $message, array $errors = [], array $warnings = [], array $metadata = []): array
    {
        return [
            'valid' => false,
            'errors' => array_merge([$message], $errors),
            'warnings' => $warnings,
            'metadata' => $metadata,
        ];
    }

    /**
     * Hamming distance between two hex-encoded bitstrings.
     */
    private function hammingDistance(string $hex1, string $hex2): int
    {
        if (strlen($hex1) !== strlen($hex2)) return PHP_INT_MAX;

        $distance = 0;
        $len = strlen($hex1);
        for ($i = 0; $i < $len; $i++) {
            $v1 = hexdec($hex1[$i]);
            $v2 = hexdec($hex2[$i]);
            $xor = $v1 ^ $v2;
            $distance += substr_count(sprintf('%04b', $xor), '1');
        }
        return $distance;
    }
}
