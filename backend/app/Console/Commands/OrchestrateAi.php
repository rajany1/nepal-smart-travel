<?php

namespace App\Console\Commands;

use App\Services\Ai\AgentOrchestrator;
use Illuminate\Console\Command;

class OrchestrateAi extends Command
{
    protected $signature = 'ai:orchestrate';
    protected $description = 'Manager AI orchestrates all AI employees — delegates reports, moderation, translation tasks';

    public function handle(AgentOrchestrator $orchestrator): int
    {
        $this->info('Manager AI orchestrating work...');

        $result = $orchestrator->runOrchestrate();
        $this->line("  Delegated {$result['delegated']} task(s)");

        $this->info('Processing pending sub-tasks...');
        $taskResults = $orchestrator->runPendingTasks();
        $this->line('  Processed ' . count($taskResults) . ' pending task(s)');

        foreach ($taskResults as $r) {
            $status = $r['status'] ?? 'unknown';
            $this->line("    [{$status}] {$r['agent']} — Task #{$r['task']}");
        }

        return Command::SUCCESS;
    }
}
