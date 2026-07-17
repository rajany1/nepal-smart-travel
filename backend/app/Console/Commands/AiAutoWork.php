<?php

namespace App\Console\Commands;

use App\Services\Ai\AgentOrchestrator;
use Illuminate\Console\Command;

class AiAutoWork extends Command
{
    protected $signature = 'ai:auto-work';
    protected $description = 'Run auto-work for AI agents (translation, moderation)';

    public function handle(AgentOrchestrator $orchestrator): int
    {
        $this->info('Translation AI working..');
        $translations = $orchestrator->runAutoWork();
        $count = collect($translations)->sum(fn($r) => count($r['output']['items'] ?? []));
        $this->info(" ✓ Translated {$count} items");

        return Command::SUCCESS;
    }
}
