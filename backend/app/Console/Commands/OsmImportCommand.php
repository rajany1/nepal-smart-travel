<?php

namespace App\Console\Commands;

use App\Jobs\ImportOsmDistrictJob;
use App\Services\OsmImportService;
use Illuminate\Console\Command;

class OsmImportCommand extends Command
{
    protected $signature = 'osm:import
        {--district= : Single district to import (sync)}
        {--all : Import all 77 districts via the queue}
        {--refresh : Refresh only previously imported districts}
        {--sync : Run synchronously even for --all (no queue)}';

    protected $description = 'Import OpenStreetMap places into the database, district by district';

    public function handle(OsmImportService $service): int
    {
        $district = $this->option('district');
        $all = (bool) $this->option('all');
        $refresh = (bool) $this->option('refresh');
        $sync = (bool) $this->option('sync');

        $districts = $service->districts();

        if ($district) {
            if (!in_array($district, $districts, true)) {
                $this->error("District '{$district}' not found. Use --district with one of: " . implode(', ', $districts));
                return 1;
            }
            $this->info("Importing district: {$district} (sync)");
            $result = $service->importDistrict($district);
            $this->line(json_encode($result));
            $this->line('Status: ' . json_encode($service->getStatus($district)));
            return 0;
        }

        if ($all) {
            $target = $districts;
        } elseif ($refresh) {
            $target = $service->importedDistricts();
            if (empty($target)) {
                $this->warn('No previously imported districts found. Run osm:import --all first.');
                return 1;
            }
        } else {
            $this->error('Specify --district=NAME, --all, or --refresh.');
            return 1;
        }

        $this->info('Dispatching ' . count($target) . ' district import jobs...');

        foreach ($target as $name) {
            $service->setStatus($name, 'pending', ['dispatched_at' => now()->toIso8601String()]);
            if ($sync) {
                $this->line("Importing {$name} (sync)...");
                $result = $service->importDistrict($name);
                $this->line("  {$name}: " . json_encode($result));
            } else {
                ImportOsmDistrictJob::dispatch($name);
                $this->line("  queued: {$name}");
            }
        }

        $this->info('Done. Run a queue worker (php artisan queue:work) to process the jobs.');
        return 0;
    }
}