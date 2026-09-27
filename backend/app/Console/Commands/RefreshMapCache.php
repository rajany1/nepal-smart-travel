<?php

namespace App\Console\Commands;

use App\Services\MapCacheService;
use Illuminate\Console\Command;

class RefreshMapCache extends Command
{
    protected $signature = 'map:refresh-cache';
    protected $description = 'Refresh Nepal boundary GeoJSON cache in Redis (ADM0/ADM1/ADM2)';

    public function handle(): int
    {
        $this->info('Refreshing map boundary cache...');

        MapCacheService::refreshAll();

        $this->info('Done! Cached keys:');
        $this->line('  ' . MapCacheService::boundaryKey());
        $this->line('  ' . MapCacheService::provincesKey());
        $this->line('  ' . MapCacheService::districtsKey());
        $this->line('TTL: ' . MapCacheService::ttl() . ' seconds (' . round(MapCacheService::ttl() / 86400, 1) . ' days)');

        return self::SUCCESS;
    }
}
