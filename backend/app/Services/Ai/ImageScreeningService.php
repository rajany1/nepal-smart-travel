<?php

namespace App\Services\Ai;

/**
 * Deterministic (zero-AI) image pre-filter for the report moderation pipeline.
 *
 * PURPOSE
 * - Cheaply detect obvious non-live / screen-captured / structurally
 *   screenshot-like images BEFORE the vision model is invoked, so that
 *   obvious cases skip the Vision AI call and go straight to the existing
 *   'suspicious' verdict (pending human review).
 * - Attach structural evidence ("what does the file structure suggest?")
 *   alongside the vision verdict ("what does the image depict?").
 *
 * EXPLICITLY NOT IN SCOPE
 * - This is NOT camera-provenance proof. It cannot prove an image came from
 *   Oripori's camera and does not attempt to.
 * - It never hard-rejects a report. Strong signals map onto the EXISTING
 *   'suspicious' image verdict, which the existing decision engine already
 *   routes to human review (never auto-approval).
 * - It never replaces the vision model's semantic checks (content, report
 *   match, screen-photo probability, AI-generated probability, map
 *   screenshot detection, misleading content).
 *
 * ENVIRONMENT CONSTRAINTS
 * - Works with core PHP only: no GD, no Imagick, no ext-exif required.
 *   When ext-exif is unavailable the pipeline falls back to this service's
 *   pure-PHP TIFF/IFD parser (JPEG APP1 / PNG eXIf / WebP EXIF chunk), which
 *   reads Make/Model/Software/DateTime tags so camera-trace classification
 *   keeps working server-wide. Pixel-level techniques (moire/grid/FFT) are
 *   deliberately NOT implemented — no JPEG decoding is hand-rolled — and the
 *   admin UI reports them honestly as "Unknown".
 * - Deliberately conservative: when EXIF presence cannot be determined,
 *   dimension-based signals do NOT fire.
 *
 * SIGNALS (strong = skips Vision AI, verdict 'suspicious'):
 * 1. known_screen_viewport_dimensions — exact match on a documented screen
 *    viewport resolution while no camera EXIF trace exists. Every listed
 *    resolution has a long edge > 1600 px, which the official in-app capture
 *    path (image_picker maxWidth/maxHeight 1600) can never produce.
 * 2. extreme_aspect_ratio — aspect ratio >= 4:1 with no camera EXIF trace
 *    (scroll/long-screenshot signature; phone cameras do not output such
 *    stills).
 * 3. screenshot_software_stamp — EXIF Software tag explicitly names a
 *    screen-capture tool (only readable when ext-exif is available).
 *
 * 'no EXIF' ALONE IS NEVER A SIGNAL: legitimate images can lose metadata
 * (including Oripori's own captures, since image_picker re-encoding strips
 * metadata), so metadata absence is treated as neutral unless combined with
 * a structural signal above.
 */
class ImageScreeningService
{
    /**
     * Exact, documented screen-viewport resolutions (phone + desktop), in
     * either orientation. Every entry has a long edge STRICTLY greater than
     * 1600 px so that a genuine Oripori camera capture — which image_picker
     * downscales to maxWidth/maxHeight 1600 — can never match by accident.
     *
     * Exposed as public const so tests can enforce the > 1600 invariant.
     */
    public const SCREEN_VIEWPORT_DIMENSIONS = [
        // Android phones
        [1080, 1920],
        [1080, 2220],
        [1080, 2280],
        [1080, 2340],
        [1080, 2400],
        [1440, 2560],
        [1440, 3040],
        [1440, 3088],
        [1440, 3120],
        [1440, 3200],
        // iPhone (native raster resolutions)
        [1125, 2436],
        [1170, 2532],
        [1179, 2556],
        [1206, 2622],
        [1242, 2688],
        [1284, 2778],
        [1290, 2796],
        [1320, 2868],
        // Desktop / laptop
        [1680, 1050],
        [1920, 1080],
        [1920, 1200],
        [2560, 1080],
        [2560, 1440],
        [2560, 1600],
        [3440, 1440],
        [3840, 2160],
    ];

    private const EXTREME_ASPECT_RATIO = 4.0;

    private const SCREENSHOT_SOFTWARE_PATTERN =
        '/screenshot|screen[\s_-]?shot|snipping|snagit|snipaste|greenshot|sharex|lightshot|screen[\s_-]?capture|screencapture/i';

    /**
     * Screen the image using only deterministic, cheap checks.
     *
     * @param  string  $path  Real path of the stored image.
     * @param  int  $width  Pixel width (already computed by the caller via getimagesize).
     * @param  int  $height  Pixel height (already computed by the caller).
     * @param  array  $trace  EXIF camera trace already computed by the caller
     *                        (ReportAnalysisService::checkCameraTrace).
     * @return array{evaluated: bool, strong: bool, signals: array<int, array{signal: string, detail: string}>, facts: array}
     *                                                                                                                        evaluated is always true for a screen() result (it maps to the
     *                                                                                                                        spec's screening.evaluated flag); strong maps to strong_signal.
     */
    public function screen(string $path, int $width, int $height, array $trace): array
    {
        $mime = $this->detectMime($path);
        $cameraTrace = $this->resolveCameraTrace($path, $mime, $trace);
        $signals = [];

        // --- Signal 3: EXIF Software tag explicitly names a capture tool ---
        // Only possible when ext-exif parsed the tag (trace kind 'screenshotish'
        // carries the raw Software value).
        if (($trace['kind'] ?? null) === 'screenshotish'
            && ! empty($trace['software'])
            && is_string($trace['software'])
            && preg_match(self::SCREENSHOT_SOFTWARE_PATTERN, $trace['software']) === 1) {
            $signals[] = [
                'signal' => 'screenshot_software_stamp',
                'detail' => 'EXIF Software tag: '.$trace['software'],
            ];
        }

        $aspectRatio = 0.0;
        $viewportMatch = false;

        if ($width > 0 && $height > 0) {
            $aspectRatio = round(max($width, $height) / min($width, $height), 3);
            $viewportMatch = $this->matchesScreenViewport($width, $height);

            // Dimension-based signals require PROOF that no camera EXIF trace
            // exists. If EXIF presence could not be determined ('uncertain'),
            // they deliberately do not fire.
            if ($cameraTrace === 'absent') {
                // --- Signal 1: exact documented screen viewport resolution ---
                if ($viewportMatch) {
                    $signals[] = [
                        'signal' => 'known_screen_viewport_dimensions',
                        'detail' => "{$width}x{$height} matches a documented screen viewport resolution and the file has no camera EXIF trace",
                    ];
                }

                // --- Signal 2: extreme aspect ratio (scroll/long screenshot) ---
                if ($aspectRatio >= self::EXTREME_ASPECT_RATIO) {
                    $signals[] = [
                        'signal' => 'extreme_aspect_ratio',
                        'detail' => "aspect ratio {$aspectRatio} (>= ".self::EXTREME_ASPECT_RATIO.') with no camera EXIF trace',
                    ];
                }
            }
        }

        return [
            'evaluated' => true,
            'strong' => count($signals) > 0,
            'signals' => $signals,
            'facts' => [
                'mime' => $mime,
                'camera_trace' => $cameraTrace,
                'aspect_ratio' => $aspectRatio,
                'viewport_match' => $viewportMatch,
            ],
        ];
    }

    /**
     * Decide whether a camera EXIF trace is definitively present / absent.
     *
     * Returns:
     * - 'present'  : trace reader identified a camera Make/Model.
     * - 'absent'   : trace reader positively read the file and found no camera
     *                trace (no EXIF / Software-only / EXIF without Make/Model),
     *                OR a container-header probe found no EXIF block at all.
     * - 'uncertain': EXIF block exists but could not be parsed (ext-exif
     *                missing), or the container is unknown/unreadable.
     */
    private function resolveCameraTrace(string $path, string $mime, array $trace): string
    {
        $kind = $trace['kind'] ?? 'unknown';

        if ($kind === 'camera') {
            return 'present';
        }

        if (in_array($kind, ['no_metadata', 'screenshotish', 'no_camera_trace'], true)) {
            return 'absent';
        }

        // kind === 'unknown': the trace reader could not inspect the file
        // (ext-exif missing, or container not natively readable). Fall back to
        // a cheap container-header probe for an EXIF block.
        $hasExif = match (true) {
            $this->isJpegMime($mime) => $this->jpegHasExifSegment($path),
            str_contains($mime, 'png') => $this->pngHasExifChunk($path),
            str_contains($mime, 'webp') => $this->webpHasExifChunk($path),
            default => null, // unknown container -> cannot determine
        };

        return $hasExif === false ? 'absent' : 'uncertain';
    }

    private function matchesScreenViewport(int $width, int $height): bool
    {
        foreach (self::SCREEN_VIEWPORT_DIMENSIONS as [$w, $h]) {
            if (($width === $w && $height === $h) || ($width === $h && $height === $w)) {
                return true;
            }
        }

        return false;
    }

    private function detectMime(string $path): string
    {
        if (! is_file($path)) {
            return 'unknown';
        }

        $mime = @mime_content_type($path);

        return is_string($mime) && $mime !== '' ? $mime : 'unknown';
    }

    private function isJpegMime(string $mime): bool
    {
        return str_contains($mime, 'jpeg') || str_contains($mime, 'jpg');
    }

    /**
     * Scan JPEG markers (header only, capped at 512 KB) for an APP1 segment
     * carrying the "Exif\0\0" signature. Returns true only when an EXIF block
     * is (or may be) present. Fails open to 'true' (uncertain) on malformed /
     * unscanned input so dimension signals stay conservative.
     */
    private function jpegHasExifSegment(string $path): bool
    {
        $data = @file_get_contents($path, false, null, 0, 524288);

        if ($data === false || strlen($data) < 4 || substr($data, 0, 2) !== "\xFF\xD8") {
            return true; // unreadable header -> uncertain
        }

        $i = 2;
        $len = strlen($data);

        while ($i + 2 <= $len) {
            if (ord($data[$i]) !== 0xFF) {
                $i++;

                continue;
            }

            $marker = ord($data[$i + 1]);

            if ($marker === 0xFF) {
                $i++;

                continue;
            }

            // Start-of-scan / end-of-image: header section finished, no EXIF found.
            if ($marker === 0xDA || $marker === 0xD9) {
                return false;
            }

            // Standalone markers without a length field.
            if ($marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                $i += 2;

                continue;
            }

            if ($i + 4 > $len) {
                return true; // truncated segment header -> uncertain
            }

            $segLen = (ord($data[$i + 2]) << 8) | ord($data[$i + 3]);
            if ($segLen < 2) {
                return true; // malformed -> uncertain
            }

            if ($marker === 0xE1 && substr($data, $i + 4, 6) === "Exif\x00\x00") {
                return true;
            }

            $i += 2 + $segLen;
        }

        // Ran out of buffer before reaching scan data -> could not fully scan.
        return true;
    }

    /**
     * Walk PNG chunk table looking for an 'eXIf' chunk. Only chunk headers are
     * read (data is skipped via fseek), so cost is O(number of chunks).
     */
    private function pngHasExifChunk(string $path): bool
    {
        $fh = @fopen($path, 'rb');
        if (! $fh) {
            return true; // unreadable -> uncertain
        }

        try {
            if (fread($fh, 8) !== "\x89PNG\r\n\x1a\n") {
                return true; // not actually a PNG -> uncertain
            }

            while (true) {
                $header = fread($fh, 8);
                if (strlen($header) < 8) {
                    return false; // end of file, no eXIf chunk
                }

                $chunkLen = unpack('N', substr($header, 0, 4))[1];
                $type = substr($header, 4, 4);

                if ($type === 'eXIf') {
                    return true;
                }
                if ($type === 'IEND') {
                    return false;
                }
                if ($chunkLen > 268435456) {
                    return true; // absurd chunk length -> uncertain
                }
                if (fseek($fh, $chunkLen + 4, SEEK_CUR) !== 0) {
                    return false;
                }
            }
        } finally {
            fclose($fh);
        }
    }

    /**
     * Walk WebP/RIFF chunk table looking for an 'EXIF' chunk. Chunk headers
     * only; data is skipped via fseek.
     */
    private function webpHasExifChunk(string $path): bool
    {
        $fh = @fopen($path, 'rb');
        if (! $fh) {
            return true; // unreadable -> uncertain
        }

        try {
            $header = fread($fh, 12);
            if (strlen($header) < 12 || substr($header, 0, 4) !== 'RIFF' || substr($header, 8, 4) !== 'WEBP') {
                return true; // not actually a WebP -> uncertain
            }

            while (true) {
                $chunk = fread($fh, 8);
                if (strlen($chunk) < 8) {
                    return false; // end of file, no EXIF chunk
                }

                if (substr($chunk, 0, 4) === 'EXIF') {
                    return true;
                }

                $size = unpack('V', substr($chunk, 4, 4))[1];
                $padding = $size % 2; // RIFF chunks are word-aligned
                if (fseek($fh, $size + $padding, SEEK_CUR) !== 0) {
                    return false;
                }
            }
        } finally {
            fclose($fh);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Pure-PHP EXIF tag fallback (no ext-exif)
    |--------------------------------------------------------------------------
    |
    | When the server has no 'exif' extension, the pipeline cannot use
    | exif_read_data(). This parser reads the TIFF/IFD structure inside a
    | JPEG APP1, PNG eXIf or WebP EXIF block directly, so camera/software
    | trace classification still works instead of degrading to 'unknown'
    | for every image. It only parses metadata — it never decodes pixels
    | (no GD/Imagick is required or used).
    |
    */

    /**
     * Parse the handful of EXIF tags the moderation pipeline uses, using only
     * core PHP. Handles little-endian ('II') and big-endian ('MM') TIFF
     * headers, IFD0 and the EXIF sub-IFD (tag 0x8769).
     *
     * Tolerant by design: malformed, truncated or out-of-bounds input simply
     * yields fewer tags — this method never throws and never warns loudly.
     *
     * @return array{container: string, exif_found: bool, make: string, model: string, software: string, datetime: string, datetime_original: string, has_lens_data: bool}
     *                                                                                                                                                                     container: 'jpeg' | 'png' | 'webp' | 'unknown'
     */
    public function parseExifTags(string $path): array
    {
        $out = [
            'container' => 'unknown',
            'exif_found' => false,
            'make' => '',
            'model' => '',
            'software' => '',
            'datetime' => '',
            'datetime_original' => '',
            'has_lens_data' => false,
        ];

        $data = @file_get_contents($path);
        if (! is_string($data) || $data === '') {
            return $out;
        }

        $tiff = null;

        if (substr($data, 0, 2) === "\xFF\xD8") {
            $out['container'] = 'jpeg';
            $tiff = $this->jpegExifTiff($data);
        } elseif (substr($data, 0, 8) === "\x89PNG\r\n\x1a\n") {
            $out['container'] = 'png';
            $tiff = $this->pngExifTiff($data);
        } elseif (strlen($data) >= 12 && substr($data, 0, 4) === 'RIFF' && substr($data, 8, 4) === 'WEBP') {
            $out['container'] = 'webp';
            $tiff = $this->webpExifTiff($data);
        }

        if ($tiff === null || $tiff === '') {
            return $out; // container recognized but no EXIF block found
        }

        $out['exif_found'] = true;
        $this->parseTiffTags($tiff, $out);

        return $out;
    }

    /**
     * Locate the TIFF payload of the first EXIF APP1 segment in a JPEG
     * (marker walk, header only). Returns null when no EXIF APP1 exists —
     * XMP-only APP1 segments are skipped and scanning continues.
     */
    private function jpegExifTiff(string $data): ?string
    {
        $len = strlen($data);
        $i = 2;

        while ($i + 4 <= $len) {
            if (ord($data[$i]) !== 0xFF) {
                $i++;

                continue;
            }

            $marker = ord($data[$i + 1]);

            if ($marker === 0xFF) {
                $i++;

                continue;
            }

            if ($marker === 0xDA || $marker === 0xD9) {
                return null; // reached scan data / EOI without EXIF
            }

            if ($marker === 0x01 || ($marker >= 0xD0 && $marker <= 0xD7)) {
                $i += 2;

                continue;
            }

            $segLen = (ord($data[$i + 2]) << 8) | ord($data[$i + 3]);
            if ($segLen < 2 || $i + 2 + $segLen > $len) {
                return null; // malformed segment
            }

            if ($marker === 0xE1 && substr($data, $i + 4, 6) === "Exif\x00\x00") {
                return substr($data, $i + 10, $segLen - 8);
            }

            $i += 2 + $segLen;
        }

        return null;
    }

    /**
     * Locate the TIFF payload of a PNG 'eXIf' chunk (chunk-table walk).
     * Accepts both raw-TIFF payloads and the common "Exif\0\0"-prefixed form.
     */
    private function pngExifTiff(string $data): ?string
    {
        $len = strlen($data);
        $i = 8;

        while ($i + 8 <= $len) {
            $chunkLen = unpack('N', substr($data, $i, 4))[1];
            $type = substr($data, $i + 4, 4);

            if ($i + 12 + $chunkLen > $len) {
                return null; // truncated chunk
            }

            if ($type === 'eXIf') {
                return $this->stripExifPrefix(substr($data, $i + 8, $chunkLen));
            }

            if ($type === 'IEND') {
                return null;
            }

            $i += 12 + $chunkLen; // 4 len + 4 type + data + 4 crc
        }

        return null;
    }

    /**
     * Locate the TIFF payload of a WebP/RIFF 'EXIF' chunk (chunk-table walk).
     * Accepts both raw-TIFF payloads and the "Exif\0\0"-prefixed form.
     */
    private function webpExifTiff(string $data): ?string
    {
        $len = strlen($data);
        $i = 12;

        while ($i + 8 <= $len) {
            $id = substr($data, $i, 4);
            $size = unpack('V', substr($data, $i + 4, 4))[1];

            if ($i + 8 + $size > $len) {
                return null; // truncated chunk
            }

            if ($id === 'EXIF') {
                return $this->stripExifPrefix(substr($data, $i + 8, $size));
            }

            $i += 8 + $size + ($size % 2); // RIFF chunks are word-aligned
        }

        return null;
    }

    private function stripExifPrefix(string $payload): string
    {
        return substr($payload, 0, 6) === "Exif\x00\x00" ? substr($payload, 6) : $payload;
    }

    /**
     * Walk a TIFF structure (both byte orders) and merge the tags the
     * pipeline uses into $out: Make, Model, Software, DateTime (IFD0) and
     * DateTimeOriginal + lens/exposure presence (EXIF sub-IFD).
     */
    private function parseTiffTags(string $tiff, array &$out): void
    {
        $ord = substr($tiff, 0, 2);
        if ($ord === 'II') {
            $le = true;
        } elseif ($ord === 'MM') {
            $le = false;
        } else {
            return; // not a TIFF structure
        }

        if ($this->tiffU16($tiff, 2, $le) !== 42) {
            return; // invalid TIFF magic
        }

        $ifd0 = $this->tiffU32($tiff, 4, $le);
        if ($ifd0 === null) {
            return;
        }

        $exifIfd = $this->readIfd($tiff, $ifd0, $le, $out, false);
        if ($exifIfd !== null) {
            $this->readIfd($tiff, $exifIfd, $le, $out, true);
        }
    }

    /**
     * Read one IFD directory into $out. Returns the EXIF sub-IFD offset
     * (tag 0x8769) when encountered in IFD0, otherwise null.
     */
    private function readIfd(string $tiff, int $offset, bool $le, array &$out, bool $isExifIfd): ?int
    {
        $tl = strlen($tiff);
        if ($offset < 0 || $offset + 2 > $tl) {
            return null;
        }

        $count = $this->tiffU16($tiff, $offset, $le);
        if ($count === null || $count < 1 || $count > 512) {
            return null; // absurd directory size -> stop (bounds guard)
        }

        $exifIfd = null;
        $base = $offset + 2;

        for ($i = 0; $i < $count; $i++) {
            $e = $base + $i * 12;
            if ($e + 12 > $tl) {
                break;
            }

            $tag = $this->tiffU16($tiff, $e, $le);
            $type = $this->tiffU16($tiff, $e + 2, $le);
            $n = $this->tiffU32($tiff, $e + 4, $le);
            if ($tag === null || $type === null || $n === null) {
                break;
            }

            if ($isExifIfd) {
                // Presence of any lens/exposure tag mirrors ext-exif's
                // FNumber/FocalLength/ExposureTime/ISOSpeedRatings keys.
                if (in_array($tag, [0x829D, 0x9202, 0x829A, 0x8827], true)) {
                    $out['has_lens_data'] = true;
                }
                if ($tag === 0x9003) { // DateTimeOriginal
                    $v = $this->tiffAscii($tiff, $e, $type, $n, $le);
                    if ($v !== '') {
                        $out['datetime_original'] = $v;
                    }
                }

                continue;
            }

            switch ($tag) {
                case 0x010F: // Make
                    $v = $this->tiffAscii($tiff, $e, $type, $n, $le);
                    if ($v !== '') {
                        $out['make'] = $v;
                    }
                    break;
                case 0x0110: // Model
                    $v = $this->tiffAscii($tiff, $e, $type, $n, $le);
                    if ($v !== '') {
                        $out['model'] = $v;
                    }
                    break;
                case 0x0131: // Software
                    $v = $this->tiffAscii($tiff, $e, $type, $n, $le);
                    if ($v !== '') {
                        $out['software'] = $v;
                    }
                    break;
                case 0x0132: // DateTime
                    $v = $this->tiffAscii($tiff, $e, $type, $n, $le);
                    if ($v !== '') {
                        $out['datetime'] = $v;
                    }
                    break;
                case 0x8769: // ExifIFD pointer (LONG, count 1)
                    if ($type === 4 && $n === 1) {
                        $ptr = $this->tiffU32($tiff, $e + 8, $le);
                        if ($ptr !== null) {
                            $exifIfd = $ptr;
                        }
                    }
                    break;
            }
        }

        return $exifIfd;
    }

    /**
     * Read an ASCII (type 2) tag value, inline (<=4 bytes) or by offset.
     * Every read is bounds-checked against the TIFF payload length.
     */
    private function tiffAscii(string $tiff, int $entry, int $type, int $count, bool $le): string
    {
        if ($type !== 2 || $count < 1 || $count > 4096) {
            return '';
        }

        $tl = strlen($tiff);
        if ($count <= 4) {
            $offset = $entry + 8;
        } else {
            $offset = $this->tiffU32($tiff, $entry + 8, $le);
            if ($offset === null) {
                return '';
            }
        }

        if ($offset < 0 || $offset + $count > $tl) {
            return ''; // out of bounds -> ignore
        }

        $raw = substr($tiff, $offset, $count);
        $nul = strpos($raw, "\0");
        if ($nul !== false) {
            $raw = substr($raw, 0, $nul);
        }

        return trim($raw);
    }

    private function tiffU16(string $b, int $o, bool $le): ?int
    {
        if ($o < 0 || $o + 2 > strlen($b)) {
            return null;
        }

        $x = ord($b[$o]);
        $y = ord($b[$o + 1]);

        return $le ? $x | ($y << 8) : ($x << 8) | $y;
    }

    private function tiffU32(string $b, int $o, bool $le): ?int
    {
        if ($o < 0 || $o + 4 > strlen($b)) {
            return null;
        }

        $x = ord($b[$o]);
        $y = ord($b[$o + 1]);
        $z = ord($b[$o + 2]);
        $w = ord($b[$o + 3]);

        return $le
            ? $x | ($y << 8) | ($z << 16) | ($w << 24)
            : ($x << 24) | ($y << 16) | ($z << 8) | $w;
    }
}
