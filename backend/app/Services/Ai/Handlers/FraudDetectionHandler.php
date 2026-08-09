<?php

namespace App\Services\Ai\Handlers;

use App\Models\AiAgentTask;
use App\Models\Booking;
use App\Models\PlaceReview;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class FraudDetectionHandler extends BaseHandler
{
    private const MAX_GPS_DISTANCE_KM = 1.0;

    public function handle(AiAgentTask $task): AiAgentTask
    {
        $input = $task->input_data;
        $action = $input['action'] ?? 'auto';

        if ($action === 'assess') {
            return $this->assess($task);
        }

        if (in_array($action, ['auto', 'auto-work'])) {
            return $this->handleAutoWork($task);
        }

        if ($action === 'scan-report' && isset($input['report_id'])) {
            return $this->scanReport($task, (int) $input['report_id']);
        }

        return $this->markFailed($task, 'Unknown action: ' . $action);
    }

    protected function assess(AiAgentTask $task): AiAgentTask
    {
        $reports = Report::whereNotNull('photo_gps_lat')->whereNotNull('photo_gps_lng')->count();
        $dupes = $this->duplicateTitleQuery()->count();
        $msg = "{$reports} report(s) with GPS data to re-verify, {$dupes} potential duplicate title(s)";
        return $this->markComplete($task, ['reports_with_gps' => $reports, 'potential_duplicates' => $dupes, 'message' => $msg]);
    }

    protected function handleAutoWork(AiAgentTask $task): AiAgentTask
    {
        $results = $this->autoWork();
        $msg = count($results['flags']) . ' fraud flag(s) raised (gps_status_updates: ' . count($results['gps_status_updates']) . ')';
        return $this->markComplete($task, $results);
    }

    protected function autoWork(): array
    {
        $gpsUpdates = [];
        $flags = [];

        $reports = Report::whereNotNull('photo_gps_lat')
            ->whereNotNull('photo_gps_lng')
            ->whereIn('status', ['pending', 'approved'])
            ->take(50)
            ->get(['id', 'title', 'user_id', 'latitude', 'longitude', 'photo_gps_lat', 'photo_gps_lng', 'gps_verification_status', 'created_at']);

        foreach ($reports as $report) {
            $distance = $this->haversineKm(
                (float) $report->photo_gps_lat,
                (float) $report->photo_gps_lng,
                (float) $report->latitude,
                (float) $report->longitude
            );

            if ($distance > self::MAX_GPS_DISTANCE_KM) {
                $gpsUpdates[] = [
                    'report_id' => $report->id,
                    'distance_km' => round($distance, 2),
                    'was' => $report->gps_verification_status,
                    'now' => 'mismatched',
                ];
                if ($report->gps_verification_status !== 'mismatched') {
                    $report->update(['gps_verification_status' => 'mismatched']);
                }
                $flags[] = [
                    'type' => 'gps_mismatch',
                    'report_id' => $report->id,
                    'title' => $report->title,
                    'severity' => $distance > 5 ? 'high' : 'medium',
                    'detail' => "Photo GPS is {$distance} km away from reported location",
                ];
            }
        }

        $duplicates = $this->duplicateTitleQuery()->get(['id', 'title', 'user_id', 'district', 'created_at']);
        foreach ($duplicates as $dupe) {
            $flags[] = [
                'type' => 'possible_duplicate',
                'report_id' => $dupe->id,
                'title' => $dupe->title,
                'severity' => 'medium',
                'detail' => 'Same title reported multiple times in ' . ($dupe->district ?? 'unknown district'),
            ];
        }

        $repeatOffenders = User::whereHas('reviews', function ($q) {
            $q->where('moderation_status', 'rejected')
                ->where('moderated_at', '>=', now()->subDays(7));
        })->withCount(['reviews' => fn($q) => $q->where('moderation_status', 'rejected')->where('moderated_at', '>=', now()->subDays(7))])
            ->having('reviews_count', '>=', 3)
            ->take(10)
            ->get(['id', 'name', 'email']);

        foreach ($repeatOffenders as $user) {
            $flags[] = [
                'type' => 'repeat_review_offender',
                'user_id' => $user->id,
                'severity' => 'high',
                'detail' => "{$user->name} had {$user->reviews_count} rejected reviews in the last 7 days",
            ];
        }

        $noContactBookings = Booking::whereDate('booked_at', today())
            ->where(function ($q) {
                $q->whereNull('customer_phone')->orWhere('customer_phone', '');
            })
            ->take(10)
            ->get(['id', 'customer_name', 'amount', 'status']);

        foreach ($noContactBookings as $booking) {
            $flags[] = [
                'type' => 'booking_no_contact',
                'booking_id' => $booking->id,
                'severity' => 'low',
                'detail' => 'Booking without customer phone contact',
            ];
        }

        return [
            'gps_status_updates' => $gpsUpdates,
            'flags' => $flags,
            'count' => count($flags),
            'message' => count($flags) . ' fraud flag(s) raised, ' . count($gpsUpdates) . ' GPS status update(s) applied',
        ];
    }

    protected function scanReport(AiAgentTask $task, int $reportId): AiAgentTask
    {
        $report = Report::find($reportId);
        if (!$report) {
            return $this->markFailed($task, "Report #{$reportId} not found");
        }

        $issues = [];

        if ($report->photo_gps_lat !== null && $report->photo_gps_lng !== null) {
            $distance = $this->haversineKm(
                (float) $report->photo_gps_lat,
                (float) $report->photo_gps_lng,
                (float) $report->latitude,
                (float) $report->longitude
            );
            if ($distance > self::MAX_GPS_DISTANCE_KM) {
                $issues[] = 'gps_mismatch: ' . round($distance, 2) . ' km';
                if ($report->gps_verification_status !== 'mismatched') {
                    $report->update(['gps_verification_status' => 'mismatched']);
                }
            }
        }

        $dupes = Report::where('title', $report->title)
            ->where('id', '!=', $report->id)
            ->count();
        if ($dupes > 0) {
            $issues[] = "possible_duplicate: {$dupes} other report(s) share this title";
        }

        return $this->markComplete($task, [
            'report_id' => $reportId,
            'title' => $report->title,
            'issues' => $issues,
            'verdict' => empty($issues) ? 'clean' : 'flagged',
        ]);
    }

    protected function duplicateTitleQuery()
    {
        return Report::where('status', '!=', 'rejected')
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->whereIn('id', function ($q) {
                $q->selectRaw('MAX(id)')
                    ->from('reports')
                    ->whereNotNull('title')
                    ->where('title', '!=', '')
                    ->where('status', '!=', 'rejected')
                    ->groupBy('title')
                    ->havingRaw('COUNT(*) > 1');
            });
    }

    protected function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
