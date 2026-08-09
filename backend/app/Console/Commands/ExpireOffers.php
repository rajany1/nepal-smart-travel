<?php

namespace App\Console\Commands;

use App\Models\RewardOffer;
use Illuminate\Console\Command;

class ExpireOffers extends Command
{
    protected $signature = 'offers:expire';
    protected $description = 'Auto-pause reward offers whose end time has passed (system lock)';

    public function handle(): int
    {
        $count = RewardOffer::expireEnded();
        $this->info('Expired offers paused by system: ' . $count);
        return Command::SUCCESS;
    }
}
