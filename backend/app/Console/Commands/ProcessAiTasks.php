<?php

namespace App\Console\Commands;

use App\Services\Ai\AgentOrchestrator;
use Illuminate\Console\Command;

class ProcessAiTasks extends Command
{
    protected $signature = 'ai:process-tasks';
    protected $description = 'Process all pending AI agent tasks';

    public function handle(AgentOrchestrator $orchestrator): int
    {
        $results = $orchestrator->runPendingTasks();
        $this->info('Processed ' . count($results) . ' pending task(s)');

        foreach ($results as $r) {
            $status = $r['status'] ?? 'unknown';
            $this->line("  [{$status}] Task #{$r['task']} by {$r['agent']}");
        }

        return Command::SUCCESS;
    }
}
