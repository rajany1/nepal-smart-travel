<?php

namespace App\Console\Commands;

use App\Jobs\ImportOsmDistrictJob;
use App\Services\OsmImportService;
use Illuminate\Console\Command;

class OsmRefreshCommand extends Command
{
    protected $signature = 'osm:refresh';

    protected $description = 'Refresh previously imported OSM districts via the queue (scheduled weekly)';

    public function handle(OsmImportService $service): int
    {
        $target = $service->importedDistricts();
        if (empty($target)) {
            $this->warn('No previously imported districts found. Run osm:import --all first.');
            return 1;
        }

        $this->info('Dispatching weekly refresh for ' . count($target) . ' districts...');

        foreach ($target as $name) {
            $service->setStatus($name, 'pending', ['dispatched_at' => now()->toIso8601String()]);
            ImportOsmDistrictJob::dispatch($name, true);
        }

        $this->info('Refresh jobs queued.');
        return 0;
    }
}