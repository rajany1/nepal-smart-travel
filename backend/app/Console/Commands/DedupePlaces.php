<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Place;
use Illuminate\Support\Facades\DB;

class DedupePlaces extends Command
{
    protected $signature = 'places:dedupe {--dry-run : Report duplicates without merging}';

    protected $description = 'Merge duplicate places (same name + rounded coordinates) keeping the best record';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $duplicates = Place::selectRaw('LOWER(TRIM(name)) as name_key, ROUND(latitude,3) as lat_key, ROUND(longitude,3) as lng_key, COUNT(*) as cnt')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->groupBy('name_key', 'lat_key', 'lng_key')
            ->having('cnt', '>', 1)
            ->get();

        $this->info("Found {$duplicates->count()} duplicate groups" . ($dryRun ? ' (dry-run, no changes)' : ''));

        $merged = 0;
        $removed = 0;

        foreach ($duplicates as $dup) {
            $places = Place::whereRaw('LOWER(TRIM(name)) = ?', [$dup->name_key])
                ->whereRaw('ROUND(latitude,3) = ?', [$dup->lat_key])
                ->whereRaw('ROUND(longitude,3) = ?', [$dup->lng_key])
                ->orderByDesc('is_verified')
                ->orderByDesc('is_active')
                ->orderByDesc('average_rating')
                ->orderByRaw('CHAR_LENGTH(COALESCE(description, "")) DESC')
                ->orderBy('id')
                ->get();

            $keep = $places->shift();
            if (!$keep || $places->isEmpty()) continue;

            foreach ($places as $dupPlace) {
                if (!$dryRun) {
                    DB::transaction(function () use ($dupPlace, $keep) {
                        DB::table('place_images')->where('place_id', $dupPlace->id)
                            ->update(['place_id' => $keep->id]);
                        try {
                            DB::table('place_reviews')->where('place_id', $dupPlace->id)
                                ->update(['place_id' => $keep->id]);
                        } catch (\Throwable $e) {
                            $this->warn("  ⚠ Could not move reviews from #{$dupPlace->id}: {$e->getMessage()}");
                        }
                        DB::table('model_translations')->where('translatable_type', 'App\\Models\\Place')
                            ->where('translatable_id', $dupPlace->id)
                            ->update(['translatable_id' => $keep->id]);
                        $dupPlace->delete();
                    });
                    $removed++;
                }
                $this->line("  ↦ #{$dupPlace->id} ('{$dupPlace->name}') → merged into #{$keep->id}");
            }
            $merged++;
        }

        $this->info($dryRun
            ? "Dry-run complete: {$merged} groups, {$removed} duplicates would be merged."
            : "Done: merged {$removed} duplicate places across {$merged} groups.");
        return 0;
    }
}
