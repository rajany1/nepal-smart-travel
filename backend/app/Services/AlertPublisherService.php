<?php

namespace App\Services;

use App\Jobs\SendNearbyPushNotification;
use App\Models\Alert;
use App\Models\Report;
use Illuminate\Support\Facades\Log;

/**
 * Single entry point for publishing proximity alerts.
 *
 * An approved report becomes a real, queryable Alert row (deduped per
 * source) and fans out one nearby push notification within the configured
 * radius. All approval paths (admin web, admin API update, AI auto-moderation)
 * must go through this service so behaviour stays consistent and pushes
 * never fire more than once per report approval.
 */
class AlertPublisherService
{
    public function publishFromReport(Report $report): ?Alert
    {
        if ($report->status !== 'approved') {
            return null;
        }

        if (!$report->latitude || !$report->longitude) {
            return null;
        }

        $existing = Alert::where('source_type', 'report')
            ->where('source_id', $report->id)
            ->first();

        // Re-approval after a reject/re-approve cycle: refresh the alert
        // window but do not spam users with another push.
        if ($existing) {
            $existing->update([
                'title' => $this->reportTitle($report),
                'description' => (string) $report->description,
                'severity' => $this->severityFromReport($report),
                'latitude' => $report->latitude,
                'longitude' => $report->longitude,
                'expires_at' => $this->expiry(),
                'sender_type' => 'system',
                'link_type' => 'report',
                'link_value' => (string) $report->id,
            ]);

            return $existing;
        }

        try {
            $alert = Alert::create([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'title' => $this->reportTitle($report),
                'description' => (string) $report->description,
                'alert_type' => 'emergency',
                'severity' => $this->severityFromReport($report),
                'latitude' => $report->latitude,
                'longitude' => $report->longitude,
                'affected_district' => $report->district,
                'expires_at' => $this->expiry(),
                'source_type' => 'report',
                'source_id' => $report->id,
                'sender_type' => 'system',
                'link_type' => 'report',
                'link_value' => (string) $report->id,
            ]);
        } catch (\Throwable $e) {
            // Unique (source_type, source_id) race: another worker won it.
            Log::warning('Alert publish dedupe race for report ' . $report->id . ': ' . $e->getMessage());

            return null;
        }

        $this->dispatchNearbyPush(
            title: $this->pushTitle($report),
            message: str((string) $report->description)->limit(100),
            latitude: (float) $report->latitude,
            longitude: (float) $report->longitude,
            data: ['type' => 'alert', 'id' => $alert->id, 'report_id' => $report->id],
        );

        return $alert;
    }

    /**
     * Fan out a push for a manually created alert (admin or user authored).
     * Idempotent per call-site — callers decide when a push is warranted.
     */
    public function dispatchForAlert(Alert $alert): void
    {
        // Broadcast alerts reach ALL subscribers regardless of location.
        if ($alert->is_broadcast) {
            $this->dispatchAll(
                title: (string) $alert->title,
                message: str((string) $alert->description)->limit(100),
                data: ['type' => 'alert', 'id' => $alert->id],
                settingsKey: 'notifications_enabled',
            );
            return;
        }

        if (!$alert->latitude || !$alert->longitude) {
            return;
        }

        $this->dispatchNearbyPush(
            title: (string) $alert->title,
            message: str((string) $alert->description)->limit(100),
            latitude: (float) $alert->latitude,
            longitude: (float) $alert->longitude,
            data: ['type' => 'alert', 'id' => $alert->id],
            settingsKey: 'notifications_enabled',
        );
    }

    private function dispatchAll(
        string $title,
        string $message,
        array $data = [],
        string $settingsKey = 'push_notifications',
    ): void {
        try {
            \App\Jobs\SendAllUsersPushNotification::dispatch(
                title: $title,
                message: $message,
                data: $data,
                settingsKey: $settingsKey,
            );
        } catch (\Throwable $e) {
            Log::warning('Alert broadcast push dispatch failed: ' . $e->getMessage());
        }
    }

    private function dispatchNearbyPush(
        string $title,
        string $message,
        float $latitude,
        float $longitude,
        array $data = [],
        string $settingsKey = 'push_notifications',
    ): void {
        try {
            SendNearbyPushNotification::dispatch(
                title: $title,
                message: $message,
                latitude: $latitude,
                longitude: $longitude,
                radiusKm: $this->radiusKm(),
                data: $data,
                settingsKey: $settingsKey,
            );
        } catch (\Throwable $e) {
            Log::warning('Alert push dispatch failed: ' . $e->getMessage());
        }
    }

    public function radiusKm(): float
    {
        return (float) config('services.alerts.push_radius_km', 5);
    }

    private function expiry(): \Illuminate\Support\Carbon
    {
        return now()->addHours((int) config('services.alerts.report_alert_ttl_hours', 48));
    }

    private function severityFromReport(Report $report): string
    {
        return match ($report->priority) {
            'critical' => 'critical',
            'high' => 'high',
            'low' => 'info',
            default => 'medium',
        };
    }

    private function reportTitle(Report $report): string
    {
        return (string) $report->title;
    }

    private function pushTitle(Report $report): string
    {
        return ($report->priority === 'critical' ? '🚨 ' : '⚠️ ') . $report->title;
    }
}
