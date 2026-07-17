<?php

namespace App\Console\Commands;

use App\Services\Ai\AgentOrchestrator;
use Illuminate\Console\Command;

class ProcessPendingReports extends Command
{
    protected $signature = 'ai:process-reports';
    protected $description = 'Process pending reports with Report Manager AI';

    public function handle(AgentOrchestrator $orchestrator): int
    {
        $this->info('Report Manager AI analyzing pending reports..');
        $results = $orchestrator->runPendingReports();
        $count = collect($results)->sum(fn($r) => count($r['output']['items'] ?? []));
        $this->info(" ✓ Analyzed {$count} reports");

        return Command::SUCCESS;
    }
}
