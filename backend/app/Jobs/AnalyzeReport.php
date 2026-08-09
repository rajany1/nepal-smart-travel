<?php

namespace App\Jobs;

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
    public int $tries = 3;

    public function __construct(int $reportId)
    {
        $this->reportId = $reportId;
    }

    public function handle(ReportAnalysisService $service): void
    {
        $report = Report::find($this->reportId);
        if (!$report || $report->status !== 'pending') return;

        try {
            $service->process($report);
        } catch (\Throwable $e) {
            Log::error('AnalyzeReport failed for report#' . $this->reportId . ': ' . $e->getMessage());
            throw $e;
        }
    }
}
