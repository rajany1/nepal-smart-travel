<?php

namespace App\Console\Commands;

use App\Models\ModerationQueue;
use App\Models\Report;
use App\Services\AchievementService;
use Illuminate\Console\Command;

/**
 * One-time cleanup for reports that were auto-approved before the
 * GPS-trust gate existed: approved reports whose photo GPS was missing
 * or mismatched are put back into pending so a human can review them,
 * and their auto-awarded XP is revoked (it will be re-awarded on
 * manual approval).
 */
class GpsGateCleanup extends Command
{
    protected $signature = 'reports:gps-gate-cleanup';

    protected $description = 'Re-queue previously auto-approved reports whose photo GPS was not verified';

    public function handle(AchievementService $achievements): int
    {
        $reports = Report::query()
            ->where('status', 'approved')
            ->where(function ($q) {
                $q->whereNull('gps_verification_status')
                    ->orWhereIn('gps_verification_status', ['no_gps_data', 'mismatched']);
            })
            ->get();

        $count = 0;
        foreach ($reports as $report) {
            $achievements->revokeReportApprovalXp($report);

            $report->update([
                'status' => 'pending',
                'verified_by' => null,
                'verified_at' => null,
            ]);

            ModerationQueue::where('content_type', 'report')
                ->where('content_id', $report->id)
                ->update(['status' => 'pending', 'reviewed_at' => null, 'reviewed_by' => null]);

            $count++;
        }

        $this->info("Re-queued {$count} report(s) for human review (GPS not verified).");

        return self::SUCCESS;
    }
}