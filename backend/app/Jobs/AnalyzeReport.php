<?php

namespace App\Jobs;

use App\Exceptions\AiRateLimitException;
use App\Models\Report;
use App\Services\Ai\ReportAnalysisService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $reportId;
    public int $tries = 6;

    public function __construct(int $reportId)
    {
        $this->reportId = $reportId;
    }

    public function handle(ReportAnalysisService $service): void
    {
        $report = Report::find($this->reportId);
        // Skip if missing, already human-reviewed, or no longer pending.
        if (!$report || $report->verified_by !== null || $report->status !== 'pending') return;

        try {
            $service->process($report);
        } catch (AiRateLimitException $e) {
            Log::warning('Report analysis paused for report#' . $this->reportId . ': ' . $e->getMessage());

            if ($this->attempts() < $this->tries) {
                $this->release(600);
            } else {
                throw $e;
            }
        } catch (\Throwable $e) {
            Log::error('AnalyzeReport failed for report#' . $this->reportId . ': ' . $e->getMessage());
            throw $e;
        }
    }
}
