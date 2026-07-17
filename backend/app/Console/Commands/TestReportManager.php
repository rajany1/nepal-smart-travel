<?php

namespace App\Console\Commands;

use App\Models\AiAgent;
use App\Models\Report;
use App\Models\AiAgentTask;
use App\Services\Ai\AgentOrchestrator;
use Illuminate\Console\Command;

class TestReportManager extends Command
{
    protected $signature = 'ai:test-report-manager';
    protected $description = 'Test Report Manager AI with real pending reports';

    public function handle(AgentOrchestrator $orchestrator): int
    {
        $pending = Report::where('status', 'pending')->whereNull('ai_analysis')->count();
        if ($pending === 0) {
            $this->warn('No pending reports to analyze.');
            return Command::SUCCESS;
        }

        $agent = AiAgent::where('role', 'report_manager')->first();
        if (!$agent) {
            $this->error('Report Manager AI not found. Run db:seed first.');
            return Command::FAILURE;
        }

        $this->info("Found {$pending} pending reports. Analyzing...");

        $task = AiAgentTask::create([
            'ai_agent_id' => $agent->id,
            'type' => 'process-pending',
            'status' => 'pending',
            'input_data' => ['action' => 'process-pending'],
        ]);

        $result = $orchestrator->executeTask($task);
        $this->line(json_encode($result, JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }
}
