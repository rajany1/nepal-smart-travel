<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class TestDummy extends Command
{
    protected $signature = 'test:dummy';
    protected $description = 'Dummy test command';

    public function handle(): int
    {
        $this->info('Dummy test OK');
        return Command::SUCCESS;
    }
}
