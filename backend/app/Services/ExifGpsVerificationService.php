<?php

namespace App\Services;

use App\Helpers\GeoHelper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Verifies that an uploaded photo's EXIF GPS metadata matches
 * a user's reported location, preventing fake reports.
 */
class ExifGpsVerificationService
{
    /**
     * Maximum allowed distance in kilometers between photo GPS and reported location.
     */
    const MAX_DISTANCE_KM = 0.5; // 500 meters

    /**
     * Extract GPS coordinates from an image's EXIF data.
     *
     * @param UploadedFile $file
     * @return array|null ['lat' => float, 'lng' => float] or null if not available
     */
    public function extractGpsCoordinates(UploadedFile $file): ?array
    {
        if (!function_exists('exif_read_data')) {
            Log::warning('EXIF extension not available on this PHP installation.');
            return null;
        }

        try {
            $exif = @exif_read_data($file->getRealPath(), 'GPS', true);

            if ($exif === false || !isset($exif['GPS'])) {
                Log::info('No EXIF GPS data found in uploaded image.');
                return null;
            }

            $gpsData = $exif['GPS'];

            $lat = $this->extractGpsCoordinate(
                $gpsData['GPSLatitude'] ?? null,
                $gpsData['GPSLatitudeRef'] ?? 'N'
            );

            $lng = $this->extractGpsCoordinate(
                $gpsData['GPSLongitude'] ?? null,
                $gpsData['GPSLongitudeRef'] ?? 'E'
            );

            if ($lat === null || $lng === null) {
                Log::info('Could not parse GPS coordinates from EXIF data.');
                return null;
            }

            return [
                'lat' => round($lat, 7),
                'lng' => round($lng, 7),
            ];
        } catch (\Exception $e) {
            Log::warning('Error reading EXIF data: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Verify that the photo's GPS coordinates match the reported location.
     *
     * @param UploadedFile $file The uploaded photo
     * @param float $reportLat Latitude from the report submission
     * @param float $reportLng Longitude from the report submission
     * @return array ['verified' => bool, 'photo_lat' => float|null, 'photo_lng' => float|null, 'distance_km' => float|null, 'message' => string]
     */
    public function verifyPhotoLocation(
        UploadedFile $file,
        float $reportLat,
        float $reportLng
    ): array {
        $photoCoords = $this->extractGpsCoordinates($file);

        if ($photoCoords === null) {
            return [
                'verified' => false,
                'photo_lat' => null,
                'photo_lng' => null,
                'distance_km' => null,
                'message' => 'Photo does not contain GPS location data. In-app camera photos are required.',
            ];
        }

        $distance = $this->calculateDistance(
            $reportLat, $reportLng,
            $photoCoords['lat'], $photoCoords['lng']
        );

        if ($distance <= self::MAX_DISTANCE_KM) {
            return [
                'verified' => true,
                'photo_lat' => $photoCoords['lat'],
                'photo_lng' => $photoCoords['lng'],
                'distance_km' => round($distance, 3),
                'message' => 'Photo GPS verified within ' . self::MAX_DISTANCE_KM . 'km tolerance.',
            ];
        }

        return [
            'verified' => false,
            'photo_lat' => $photoCoords['lat'],
            'photo_lng' => $photoCoords['lng'],
            'distance_km' => round($distance, 3),
            'message' => "Photo GPS location ({$photoCoords['lat']}, {$photoCoords['lng']}) is {$distance}km from your reported location. Max allowed: " . self::MAX_DISTANCE_KM . "km.",
        ];
    }

    /**
     * Parse a GPS coordinate from EXIF format (degrees/minutes/seconds) to decimal.
     */
    private function extractGpsCoordinate(?array $coordinate, string $ref): ?float
    {
        if ($coordinate === null || count($coordinate) !== 3) {
            return null;
        }

        try {
            $degrees = $this->gpsToFloat($coordinate[0] ?? '0');
            $minutes = $this->gpsToFloat($coordinate[1] ?? '0');
            $seconds = $this->gpsToFloat($coordinate[2] ?? '0');

            $decimal = $degrees + ($minutes / 60.0) + ($seconds / 3600.0);

            // South and West are negative
            if (strtoupper($ref) === 'S' || strtoupper($ref) === 'W') {
                $decimal *= -1;
            }

            return $decimal;
        } catch (\Exception $e) {
            Log::warning('Failed to parse GPS coordinate: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Convert a GPS value (could be a string like "46/1" or a float) to a float.
     */
    private function gpsToFloat($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        // Handle fraction format: "46/1" or "30/1"
        if (is_string($value) && str_contains($value, '/')) {
            $parts = explode('/', $value);
            if (count($parts) === 2 && (float) $parts[1] !== 0.0) {
                return (float) $parts[0] / (float) $parts[1];
            }
        }

        return (float) $value;
    }

    /**
     * Calculate the distance between two GPS coordinates using the Haversine formula.
     *
     * @param float $lat1
     * @param float $lng1
     * @param float $lat2
     * @param float $lng2
     * @return float Distance in kilometers
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        return GeoHelper::haversineKm($lat1, $lng1, $lat2, $lng2);
    }
}