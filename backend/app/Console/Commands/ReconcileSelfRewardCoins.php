<?php

namespace App\Console\Commands;

use App\Services\CoinReconciliationService;
use Illuminate\Console\Command;

class ReconcileSelfRewardCoins extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coins:reconcile-self-rewards
        {--limit=200 : Maximum number of reward events to scan}
        {--days=30 : Only scan reward events from the last N days}
        {--dry-run : Report what would be reversed without writing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Detect self-reward coin credits (report owner rewarded on their own report) and reverse them with compensating ledger transactions';

    /**
     * Execute the console command.
     */
    public function handle(CoinReconciliationService $service): int
    {
        $report = $service->reconcileSelfRewards(
            limit: max(1, (int) $this->option('limit')),
            withinDays: max(1, (int) $this->option('days')),
            dryRun: (bool) $this->option('dry-run'),
        );

        $this->info("Scanned {$report['scanned']} self-reward candidate event(s)");

        $sections = [
            'reversed' => 'Reversed',
            'would_reverse' => 'Would reverse',
            'already_reversed' => 'Already reversed',
            'deferred' => 'Deferred (insufficient balance, retry next run)',
            'unmatched' => 'Unmatched (no ledger credit found)',
            'ambiguous' => 'Ambiguous (left untouched)',
            'errors' => 'Errors',
        ];

        foreach ($sections as $key => $label) {
            $items = $report[$key];
            $this->line(sprintf('  %-58s %d', $label . ':', count($items)));

            foreach ($items as $item) {
                $this->line('    - ' . json_encode($item));
            }
        }

        return $report['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
