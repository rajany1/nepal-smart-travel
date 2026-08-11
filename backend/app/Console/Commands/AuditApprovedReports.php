<?php

namespace App\Console\Commands;

use App\Models\ModerationQueue;
use App\Models\Report;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Safety audit: find APPROVED reports whose vision analysis produced NO
 * usable data (empty model response treated as "clean" - the loophole) and
 * put them back to pending for human review. Also catches approved reports
 * that were never actually image-reviewed (only text+GPS).
 */
class AuditApprovedReports extends Command
{
    protected $signature = 'reports:audit-approvals';

    protected $description = 'Re-queue approved reports whose AI vision check was empty/unusable (data-less clean)';

    public function handle(): int
    {
        $reports = Report::where('status', 'approved')
            ->whereNotNull('ai_analysis')
            ->get();

        $flagged = 0;
        foreach ($reports as $report) {
            $analysis = $report->ai_analysis;
            $image = $analysis['image_check'] ?? [];

            $reviewed = (int) ($image['reviewed'] ?? 0);
            $images = $image['images'] ?? [];
            $hasMedia = Report::whereKey($report->id)->whereHas('media', fn ($q) => $q->where('type', 'image'))->exists();

            if (!$hasMedia) {
                continue;
            }
            if ($reviewed === 0) {
                $reason = 'Approved without a usable vision check — re-queued for human review';
            } elseif ($this->imagesHaveNoUsableData($images)) {
                $reason = 'Vision model returned no usable data at approval time — re-queued for human review';
            } else {
                continue;
            }

            $report->update([
                'status' => 'pending',
                'moderation_message' => $reason,
                'verified_by' => null,
                'verified_at' => null,
            ]);

            app(\App\Services\AchievementService::class)->revokeReportApprovalXp($report);

            ModerationQueue::where('content_type', 'report')
                ->where('content_id', $report->id)
                ->update(['status' => 'pending', 'reviewed_at' => null, 'reviewed_by' => null]);

            $this->line("  #{$report->id} -> pending ({$reason})");
            $flagged++;
        }

        $this->info("Audit done — {$flagged} report(s) re-queued for human review.");

        return Command::SUCCESS;
    }

    protected function imagesHaveNoUsableData(array $images): bool
    {
        foreach ($images as $img) {
            if (($img['verdict'] ?? '') === 'clean') {
                $conf = (float) ($img['confidence'] ?? 0);
                $reason = (string) ($img['reason'] ?? '');
                $screen = (float) ($img['screen_probability'] ?? 0);
                if ($conf <= 0 && $reason === '' && $screen <= 0) {
                    return true;
                }
            }
        }
        return false;
    }
}