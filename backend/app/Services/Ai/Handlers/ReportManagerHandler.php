<?php

namespace App\Services\Ai\Handlers;

use App\Models\AiAgentTask;
use App\Models\ModerationQueue;
use App\Models\Report;
use App\Services\Ai\ReportAnalysisService;
use Illuminate\Support\Facades\Log;

class ReportManagerHandler extends BaseHandler
{
    public function handle(AiAgentTask $task): AiAgentTask
    {
        $input = $task->input_data;
        $action = $input['action'] ?? 'process';

        if ($action === 'assess') {
            $count = Report::where('status', 'pending')
                ->where(function ($q) {
                    $q->whereNull('ai_analysis')
                      ->orWhereRaw("JSON_EXTRACT(ai_analysis, '$.action') IS NULL")
                      ->orWhereRaw("NOT EXISTS (SELECT 1 FROM moderation_queues WHERE content_type = 'report' AND content_id = reports.id AND status IN ('approved','rejected'))");
                })
                ->count();
            $msg = "{$count} report(s) awaiting AI analysis";
            return $this->markComplete($task, ['pending_reports' => $count, 'message' => $msg]);
        }

        if (in_array($action, ['process-pending', 'auto', 'auto-work'])) {
            return $this->processPending($task);
        }

        if ($action === 'analyze' && isset($input['report_id'])) {
            return $this->analyzeReport($task, $input['report_id']);
        }

        return $this->markFailed($task, 'Unknown action');
    }

    protected function processPending(AiAgentTask $task): AiAgentTask
    {
        $service = app(ReportAnalysisService::class);
        $processed = [];

        $pending = Report::where('status', 'pending')
            ->where(function ($q) {
                $q->whereNull('ai_analysis')
                  ->orWhereRaw("JSON_EXTRACT(ai_analysis, '$.action') IS NULL")
                  ->orWhereRaw("NOT EXISTS (SELECT 1 FROM moderation_queues WHERE content_type = 'report' AND content_id = reports.id AND status IN ('approved','rejected'))");
            })
            ->take(5)->get();

        foreach ($pending as $report) {
            try {
                $result = $service->process($report);
                if (!empty($result['skipped'])) continue;

                $action = $result['action'] ?? 'approve';
                $priority = $result['analysis']['suggested_priority'] ?? 'not-set';
                $processed[] = "report#{$report->id}: {$action} ({$priority})";
            } catch (\Exception $e) {
                Log::error("Report analysis failed for report#{$report->id}: " . $e->getMessage());
            }
        }

        $msg = count($processed) . ' report(s) processed: ' . implode(', ', $processed);
        return $this->markComplete($task, [
            'processed' => count($processed),
            'items' => $processed,
            'message' => $msg,
        ]);
    }

    protected function analyzeReport(AiAgentTask $task, int $reportId): AiAgentTask
    {
        $report = Report::find($reportId);
        if (!$report) {
            return $this->markFailed($task, "Report #{$reportId} not found");
        }

        try {
            $result = app(ReportAnalysisService::class)->process($report);

            if (!empty($result['skipped'])) {
                return $this->markFailed($task, "Report #{$reportId} already analyzed or not pending");
            }

            $result['analysis']['message'] = "Report #{$reportId}: {$result['action']} (priority: " . ($result['analysis']['suggested_priority'] ?? 'not-set') . ")";
            return $this->markComplete($task, $result['analysis']);
        } catch (\Exception $e) {
            return $this->markFailed($task, $e->getMessage());
        }
    }
}
