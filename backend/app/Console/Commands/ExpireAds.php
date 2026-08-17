<?php

namespace App\Console\Commands;

use App\Models\AdCampaign;
use Illuminate\Console\Command;

class ExpireAds extends Command
{
    protected $signature = 'ads:expire';
    protected $description = 'Auto-pause ad campaigns whose end time has passed (system lock)';

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
        return Command::SUCCESS;
    }
}
