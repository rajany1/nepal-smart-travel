<?php

namespace App\Console\Commands;

use App\Models\AiAgent;
use App\Models\AiAgentTask;
use App\Services\Ai\AgentOrchestrator;
use Illuminate\Console\Command;

class TestManagerAi extends Command
{
    protected $signature = 'ai:test-manager';
    protected $description = 'Test the Manager AI (Big Boss)';

    public function handle(AgentOrchestrator $orchestrator): int
    {
        $manager = AiAgent::where('role', 'manager')->first();
        if (!$manager) {
            $this->error('Manager AI not found. Run db:seed first.');
            return Command::FAILURE;
        }

        // Scenario 1: Assess workload
        $this->info('Scenario 1: Assess workforce workload');
        $task = AiAgentTask::create([
            'ai_agent_id' => $manager->id,
            'type' => 'assess',
            'status' => 'pending',
            'input_data' => ['action' => 'assess'],
        ]);
        $result = $orchestrator->executeTask($task);
        $this->line(json_encode($result, JSON_PRETTY_PRINT));

        // Scenario 2: Delegate to translator
        $this->info('Scenario 2: Delegate task to Translator');
        $task2 = AiAgentTask::create([
            'ai_agent_id' => $manager->id,
            'type' => 'delegate',
            'status' => 'pending',
            'input_data' => [
                'action' => 'delegate',
                'agent_role' => 'translator',
                'task_data' => ['type' => 'translate', 'data' => ['text' => 'Hello']],
            ],
        ]);
        $result2 = $orchestrator->executeTask($task2);
        $this->line(json_encode($result2, JSON_PRETTY_PRINT));

        // Scenario 3: Delegate to review moderator
        $this->info('Scenario 3: Delegate task to Review Moderator');
        $task3 = AiAgentTask::create([
            'ai_agent_id' => $manager->id,
            'type' => 'delegate',
            'status' => 'pending',
            'input_data' => [
                'action' => 'delegate',
                'agent_role' => 'review_moderator',
                'task_data' => ['type' => 'moderate', 'data' => ['action' => 'auto-moderate']],
            ],
        ]);
        $result3 = $orchestrator->executeTask($task3);
        $this->line(json_encode($result3, JSON_PRETTY_PRINT));

        $this->info('All Manager AI scenarios completed.');
        return Command::SUCCESS;
    }
}
