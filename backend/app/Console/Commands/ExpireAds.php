<?php

namespace App\Console\Commands;

use App\Models\AdCampaign;
use App\Services\FraudDetectionService;
use Illuminate\Console\Command;

class ExpireAds extends Command
{
    protected $signature = 'ads:expire';
    protected $description = 'Auto-pause expired campaigns and decay fraud scores';

    public function handle(): int
    {
        $count = AdCampaign::where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->update([
                'status' => 'paused',
                'paused_by' => 'system',
                'rejection_reason' => null,
            ]);
        $this->info('Expired ad campaigns paused by system: ' . $count);

        $decayed = app(FraudDetectionService::class)->decayScores();
        if ($decayed > 0) {
            $this->info('Fraud scores decayed for ' . $decayed . ' campaigns');
        }

        return Command::SUCCESS;
    }
}
