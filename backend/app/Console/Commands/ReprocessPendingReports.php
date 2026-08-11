<?php

namespace App\Console\Commands;

use App\Models\Report;
use App\Services\Ai\ReportAnalysisService;
use Illuminate\Console\Command;

/**
 * Re-decide reports stuck in "pending": default mode applies the current
 * policy to the already-stored analysis (free, no AI call). --force runs a
 * full fresh AI analysis (brand new vision/text calls).
 */
class ReprocessPendingReports extends Command
{
    protected $signature = 'reports:reprocess-pending {--force : Run full fresh AI analysis instead of local re-decide}';

    protected $description = 'Re-process AI-analyzed reports stuck in pending';

    public function handle(ReportAnalysisService $service): int
    {
        $reports = Report::where('status', 'pending')->orderBy('created_at')->get();

        if ($reports->isEmpty()) {
            $this->info('No pending reports to reprocess.');
            return Command::SUCCESS;
        }

        $counts = ['approved' => 0, 'rejected' => 0, 'pending' => 0, 'skipped' => 0];
        foreach ($reports as $report) {
            $result = $this->option('force')
                ? $service->process($report, true)
                : $service->redecode($report);

            if (!empty($result['skipped'])) {
                $counts['skipped']++;
                continue;
            }
            $action = $result['action'] ?? 'pending';
            $counts[$action === 'pending-review' ? 'pending' : $action] = ($counts[$action === 'pending-review' ? 'pending' : $action] ?? 0) + 1;
            $this->line("  #{$report->id} -> {$action}" . ($this->option('force') ? ' (full re-analysis)' : ' (local re-decide)'));
        }

        $this->info(sprintf(
            "Done — %d approved, %d rejected, %d still pending, %d skipped.",
            $counts['approved'],
            $counts['rejected'],
            $counts['pending'],
            $counts['skipped']
        ));

        return Command::SUCCESS;
    }
}