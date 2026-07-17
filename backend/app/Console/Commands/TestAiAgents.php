<?php

namespace App\Console\Commands;

use App\Models\AiAgent;
use App\Models\AiAgentTask;
use App\Services\Ai\AgentOrchestrator;
use Illuminate\Console\Command;

class TestAiAgents extends Command
{
    protected $signature = 'ai:test-agents';
    protected $description = 'Test all AI agents by running scenarios';

    public function handle(AgentOrchestrator $orchestrator): int
    {
        $this->info('🧪 Testing all AI agents...');

        $agents = AiAgent::all();
        if ($agents->isEmpty()) {
            $this->warn('No AI agents found. Run php artisan db:seed --class=AiAgentSeeder first.');
            return Command::FAILURE;
        }

        $passed = 0;
        $failed = 0;

        foreach ($agents as $agent) {
            $this->line("  Testing {$agent->name} ({$agent->role})...");

            $task = AiAgentTask::create([
                'ai_agent_id' => $agent->id,
                'type' => 'test',
                'status' => 'pending',
                'input_data' => ['action' => 'assess'],
            ]);

            $result = $orchestrator->executeTask($task);

            if (($result['status'] ?? '') === 'completed') {
                $this->info("    ✅ {$agent->name} passed");
                $passed++;
            } else {
                $this->error("    ❌ {$agent->name} failed: " . ($result['error'] ?? 'unknown'));
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Results: {$passed} passed, {$failed} failed");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
