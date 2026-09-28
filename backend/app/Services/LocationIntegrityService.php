<?php

namespace App\Services;

use App\Helpers\GeoHelper;

/**
 * Server-side Location Integrity evaluation for incident reports.
 *
 * The mobile app may report mock-location detection results, GPS accuracy
 * and fix timestamps. ALL of that arrives over the wire and is therefore
 * untrusted: a modified APK can send location_integrity[status]=genuine with
 * spoofed coordinates. Client values are treated as ONE evidence signal and
 * are always combined with server-side observations:
 *
 *   - client mock-location claim (evidence only — a "false" claim is NOT
 *     proof that the location is real)
 *   - fix timestamp sanity (future / stale)
 *   - accuracy plausibility
 *   - photo GPS / capture-coordinate vs report-location conflict
 *   - impossible movement between consecutive reports from the same user
 *
 * Deliberately NOT implemented: IP-vs-GPS country comparison. VPNs legitimately
 * put a foreign IP behind a genuine Nepali GPS fix, so an IP mismatch must
 * never contribute to fraud scoring on its own.
 *
 * Output status vocabulary (server side):
 *   genuine           — no integrity concerns found in available evidence
 *   suspicious        — at least one risk signal fired; route to moderation
 *   cannot_determine  — not enough evidence either way (NEVER "genuine")
 *
 * A suspicious result never blocks a submission by itself — it feeds the
 * existing fraud / moderation pipeline, which owns the final decision.
 */
class LocationIntegrityService
{
    public const STATUS_GENUINE = 'genuine';
    public const STATUS_SUSPICIOUS = 'suspicious';
    public const STATUS_CANNOT_DETERMINE = 'cannot_determine';

    /** Client statuses accepted from the mobile detector. */
    public const CLIENT_STATUSES = ['genuine', 'mock_detected', 'cannot_determine', 'unknown'];

    /** Clock-skew tolerance before a future fix timestamp is suspicious. */
    private const FUTURE_TOLERANCE_SECONDS = 600;

    /** A fix older than this (hours) is stale for a "live" incident report. */
    private const MAX_FIX_AGE_HOURS = 24;

    /** No real GPS fix reports accuracy beyond this (metres). */
    private const MAX_PLAUSIBLE_ACCURACY_M = 10000.0;

    /** Speed (km/h) no ground transport can achieve between two reports. */
    private const IMPOSSIBLE_SPEED_KMH = 1000.0;

    /** Only compare movement inside this window; older gaps are irrelevant. */
    private const MOVEMENT_WINDOW_HOURS = 6.0;

    /** Ignore movement checks below this distance (walking/driving noise). */
    private const MOVEMENT_MIN_DISTANCE_KM = 50.0;

    /**
     * Evaluate location integrity from untrusted client signals plus
     * server-side context.
     *
     * @param array $input {
     *     @type array|null  $client               Raw client payload (location_integrity[])
     *     @type float       $latitude             Report latitude
     *     @type float       $longitude            Report longitude
     *     @type string|null $location_timestamp   Client fix timestamp (ISO-8601)
     *     @type float|null  $location_accuracy    Client fix accuracy in metres
     *     @type string|null $gps_verification_status EXIF/capture GPS verification result
     *     @type array|null  $previous             Previous report {latitude, longitude, created_at}
     * }
     * @return array{status: string, mock_location_detected: ?bool, source: ?string,
     *               client_status: ?string, signals: string[]}
     */
    public function evaluate(array $input): array
    {
        $client = is_array($input['client'] ?? null) ? $input['client'] : [];
        $signals = [];

        // --- Normalise the (untrusted) client claim -----------------------
        $clientStatus = null;
        if (array_key_exists('status', $client) && is_string($client['status'])) {
            $clientStatus = strtolower(trim($client['status']));
            if (!in_array($clientStatus, self::CLIENT_STATUSES, true)) {
                // Not a value our app can emit — treat as tampered input.
                $signals[] = 'invalid_client_status';
                $clientStatus = null;
            }
        }

        $mockClaim = null;
        if (array_key_exists('mock_location_detected', $client)) {
            $mockClaim = filter_var(
                $client['mock_location_detected'],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );
        }

        $source = null;
        if (array_key_exists('detection_source', $client) && is_string($client['detection_source'])) {
            $source = strtolower(trim($client['detection_source']));
            $source = preg_replace('/[^a-z0-9_\-]/', '', $source) ?: null;
            if ($source !== null && strlen($source) > 32) {
                $source = substr($source, 0, 32);
            }
        }

        // --- Signal 1: mock-location claim (client evidence) ---------------
        if ($mockClaim === true || $clientStatus === 'mock_detected') {
            $signals[] = 'mock_location_reported';
        }

        // --- Signal 2: fix timestamp sanity --------------------------------
        $timestamp = $input['location_timestamp'] ?? null;
        if ($timestamp !== null && $timestamp !== '') {
            $ts = strtotime((string) $timestamp);
            if ($ts === false) {
                $signals[] = 'invalid_location_timestamp';
            } else {
                $now = time();
                if ($ts > $now + self::FUTURE_TOLERANCE_SECONDS) {
                    $signals[] = 'location_timestamp_in_future';
                } elseif (($now - $ts) > self::MAX_FIX_AGE_HOURS * 3600) {
                    $signals[] = 'stale_location_timestamp';
                }
            }
        }

        // --- Signal 3: accuracy plausibility --------------------------------
        $accuracy = $input['location_accuracy'] ?? null;
        if ($accuracy !== null && $accuracy !== '') {
            if (!is_numeric($accuracy)) {
                $signals[] = 'invalid_location_accuracy';
            } else {
                $acc = (float) $accuracy;
                if ($acc <= 0.0 || $acc > self::MAX_PLAUSIBLE_ACCURACY_M) {
                    $signals[] = 'implausible_location_accuracy';
                }
            }
        }

        // --- Signal 4: photo/capture GPS conflicts with report location -----
        // Missing EXIF (no_gps_data) is NOT a risk signal: in-app camera
        // images routinely arrive without GPS metadata.
        if (($input['gps_verification_status'] ?? null) === 'mismatched') {
            $signals[] = 'photo_location_conflict';
        }

        // --- Signal 5: impossible movement between consecutive reports ------
        if ($this->isImpossibleMovement($input)) {
            $signals[] = 'impossible_movement';
        }

        // --- Final status ---------------------------------------------------
        if (!empty($signals)) {
            $status = self::STATUS_SUSPICIOUS;
        } elseif ($clientStatus === null) {
            // No usable client verdict (old app, detector unavailable, or
            // no payload at all) — never claim "genuine" without evidence.
            $status = self::STATUS_CANNOT_DETERMINE;
        } elseif ($clientStatus === 'genuine') {
            $status = self::STATUS_GENUINE;
        } else {
            // cannot_determine / unknown
            $status = self::STATUS_CANNOT_DETERMINE;
        }

        return [
            'status' => $status,
            'mock_location_detected' => $mockClaim,
            'source' => $source,
            'client_status' => $clientStatus,
            'signals' => array_values(array_unique($signals)),
        ];
    }

    /**
     * True when the implied speed between this report and the user's previous
     * one exceeds what any ground/air travel could realistically achieve.
     * Conservative thresholds: only fires for >= 50km inside a 6h window at
     * > 1000 km/h, so ordinary travel never triggers it.
     */
    private function isImpossibleMovement(array $input): bool
    {
        $previous = $input['previous'] ?? null;
        if (!is_array($previous)) {
            return false;
        }

        $prevLat = $previous['latitude'] ?? null;
        $prevLng = $previous['longitude'] ?? null;
        $prevAt = $previous['created_at'] ?? null;

        if (!is_numeric($prevLat) || !is_numeric($prevLng) || $prevAt === null) {
            return false;
        }

        $lat = $input['latitude'] ?? null;
        $lng = $input['longitude'] ?? null;
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return false;
        }

        $prevTs = strtotime((string) $prevAt);
        if ($prevTs === false) {
            return false;
        }

        $hours = (time() - $prevTs) / 3600;
        if ($hours <= 0 || $hours > self::MOVEMENT_WINDOW_HOURS) {
            return false;
        }

        $km = GeoHelper::haversineKm(
            (float) $prevLat,
            (float) $prevLng,
            (float) $lat,
            (float) $lng
        );

        if ($km < self::MOVEMENT_MIN_DISTANCE_KM) {
            return false;
        }

        return ($km / $hours) > self::IMPOSSIBLE_SPEED_KMH;
    }

    /**
     * Map raw integrity signals to the non-blocking fraud reasons consumed by
     * FraudDetectionService's existing points map. Only actual risk signals
     * produce points — "cannot_determine" is never penalised.
     *
     * @param string[] $signals
     * @return string[]
     */
    public function fraudReasons(array $signals): array
    {
        $reasons = [];

        if (in_array('mock_location_reported', $signals, true)) {
            $reasons[] = 'mock_location_detected';
        }

        if (in_array('impossible_movement', $signals, true)) {
            $reasons[] = 'impossible_movement';
        }

        $residual = array_diff($signals, ['mock_location_reported', 'impossible_movement']);
        if (!empty($residual)) {
            $reasons[] = 'location_integrity_suspicious';
        }

        return array_values(array_unique($reasons));
    }
}
