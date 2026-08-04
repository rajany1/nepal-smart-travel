<?php

namespace App\Jobs;

use App\Models\GameSetting;
use App\Models\ModerationQueue;
use App\Models\Report;
use App\Services\AchievementService;
use App\Services\Ai\GroqService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AnalyzeReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public int $reportId;
    public int $tries = 3;

    public function __construct(int $reportId)
    {
        $this->reportId = $reportId;
    }

    public function handle(GroqService $groq): void
    {
        $report = Report::find($this->reportId);
        if (!$report || $report->status !== 'pending') return;

        $text = "Title: {$report->title}\nDescription: {$report->description}\nPriority: {$report->priority}\nDistrict: {$report->district}";

        $result = $groq->generateJson(
            "Analyze this community report. Return JSON: suggested_priority (low/medium/high/critical), is_legitimate (bool — true if report is real and useful), is_duplicate (bool — true if same issue already reported), summary (max 2 sentences in English), action (approve/reject).\n\nIf is_duplicate is true, action must be reject. If is_legitimate is true, action must be approve.\n\n{$text}"
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

        // Award XP + stats on AI approval, mirroring manual approval
        if ($action === 'approve') {
            $reporter = $report->user;
            if ($reporter) {
                $rewardXp = GameSetting::getValue('report_approval_xp', 10);
                app(AchievementService::class)->awardXp(
                    $reporter, $rewardXp, 'report_approved',
                    "Report approved: {$report->title}", $report
                );
                $reporter->increment('approved_reports');
            }
        }
    }
}
