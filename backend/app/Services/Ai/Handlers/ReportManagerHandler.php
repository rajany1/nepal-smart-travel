<?php

namespace App\Services\Ai\Handlers;

use App\Models\AiAgentTask;
use App\Models\ModerationQueue;
use App\Models\Report;
use Illuminate\Support\Facades\Log;

class ReportManagerHandler extends BaseHandler
{
    public function handle(AiAgentTask $task): AiAgentTask
    {
        $input = $task->input_data;
        $action = $input['action'] ?? 'process';

        if ($action === 'process-pending') {
            return $this->processPending($task);
        }

        if ($action === 'analyze' && isset($input['report_id'])) {
            return $this->analyzeReport($task, $input['report_id']);
        }

        return $this->markFailed($task, 'Unknown action');
    }

    protected function processPending(AiAgentTask $task): AiAgentTask
    {
        $llm = $this->ai();
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
                $text = "Title: {$report->title}\nDescription: {$report->description}\nPriority: {$report->priority}\nDistrict: {$report->district}";

                $result = $llm->generateJson(
                    "Analyze this community report. Return JSON: suggested_priority (low/medium/high/critical), is_legitimate (bool — true if report is real and useful), is_duplicate (bool — true if same issue already reported), summary (string, max 2 sentences in English), action (approve/reject).\n\nIf is_duplicate is true, action must be reject. If is_legitimate is true, action must be approve.\n\n{$text}"
                );

                $action = $result['action'] ?? 'approve';
                $isDuplicate = $result['is_duplicate'] ?? false;
                $isLegitimate = $result['is_legitimate'] ?? true;

                if ($isDuplicate || !$isLegitimate) {
                    $action = 'reject';
                }

                $now = now();
                $report->update([
                    'ai_analysis' => $result,
                    'ai_analyzed_at' => $now,
                    'status' => $action === 'approve' ? 'approved' : 'rejected',
                    'verified_at' => $now,
                ]);

                if (isset($result['suggested_priority'])) {
                    $report->update(['priority' => $result['suggested_priority']]);
                }

                ModerationQueue::where('content_type', 'report')
                    ->where('content_id', $report->id)
                    ->update([
                        'status' => $action === 'approve' ? 'approved' : 'rejected',
                        'reviewed_at' => $now,
                    ]);

                $processed[] = "report#{$report->id}: {$action} ({$result['suggested_priority']})";
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

        $llm = $this->ai();
        $text = "Title: {$report->title}\nDescription: {$report->description}\nPriority: {$report->priority}\nDistrict: {$report->district}";

        try {
            $result = $llm->generateJson(
                "Analyze this community report. Return JSON: suggested_priority (low/medium/high/critical), is_legitimate (bool), is_duplicate (bool), summary (string), action (approve/reject).\n\n{$text}"
            );

            $action = $result['action'] ?? 'approve';
            if (($result['is_duplicate'] ?? false) || !($result['is_legitimate'] ?? true)) {
                $action = 'reject';
            }

            $now = now();
            $report->update([
                'ai_analysis' => $result,
                'ai_analyzed_at' => $now,
                'status' => $action === 'approve' ? 'approved' : 'rejected',
                'priority' => $result['suggested_priority'] ?? $report->priority,
                'verified_at' => $now,
            ]);

            ModerationQueue::where('content_type', 'report')
                ->where('content_id', $report->id)
                ->update([
                    'status' => $action === 'approve' ? 'approved' : 'rejected',
                    'reviewed_at' => $now,
                ]);

            $result['message'] = "Report #{$reportId}: {$action} (priority: {$result['suggested_priority']})";
            return $this->markComplete($task, $result);
        } catch (\Exception $e) {
            return $this->markFailed($task, $e->getMessage());
        }
    }
}
