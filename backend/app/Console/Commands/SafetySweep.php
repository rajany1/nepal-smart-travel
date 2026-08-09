<?php

namespace App\Console\Commands;

use App\Services\ContentSafetyService;
use Illuminate\Console\Command;

class SafetySweep extends Command
{
    protected $signature = 'ai:safety-sweep {--limit=30 : max entities scanned per run}';

    protected $description = 'Review AI agent: 24/7 sweep that censors bad words across the app and escalates repeat offenders';

    public function handle(ContentSafetyService $safety): int
    {
        $report = $safety->sweepBatch((int) $this->option('limit'));

        $this->info(json_encode([
            'scanned' => $report['scanned'],
            'censored' => $report['censored'],
            'violations' => $report['violations'],
            'reactivated' => $report['reactivated'],
        ]));

        return self::SUCCESS;
    }
}