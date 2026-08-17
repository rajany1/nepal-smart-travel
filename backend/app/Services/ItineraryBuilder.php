<?php

namespace App\Services;

use App\Models\Place;

/**
 * Deterministic itinerary planner (no LLM). Builds day-wise plans from the
 * best-rated places in a district and orders each day's stops with a greedy
 * nearest-neighbour walk so the route is geographically sensible.
 */
class ItineraryBuilder
{
    protected const STOPS_PER_DAY = 3;
    protected const MAX_DAYS = 3;
    protected const MAX_PLACES = 8;

    public function topDistricts(int $limit = 5): \Illuminate\Support\Collection
    {
        return Place::active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNotNull('district')
            ->where('district', '!=', '')
            ->selectRaw('district, COUNT(*) as places_count')
            ->groupBy('district')
            ->get()
            ->sortByDesc('places_count')
            ->take($limit)
            ->values();
    }

    /** @return array[] day plans; empty when the district has no places */
    public function buildForDistrict(string $district): array
    {
        $places = Place::active()
            ->with('category:id,name')
            ->where('district', $district)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('average_rating')
            ->take(self::MAX_PLACES)
            ->get(['id', 'name', 'category_id', 'description', 'average_rating', 'latitude', 'longitude']);

        if ($places->isEmpty()) {
            return [];
        }

        $selected = $places->take(self::MAX_DAYS * self::STOPS_PER_DAY)->values();
        $chunks = $selected->chunk(self::STOPS_PER_DAY)->values()->take(self::MAX_DAYS);

        $days = [];
        $day = 1;
        foreach ($chunks as $chunk) {
            $ordered = $this->orderByProximity($chunk);
            $theme = $this->dominantCategory($ordered);

            $days[] = [
                'day' => $day++,
                'theme' => ucfirst($theme) . ' highlights',
                'stops' => $ordered->map(fn (Place $p) => [
                    'place_id' => $p->id,
                    'name' => $p->name,
                    'tip' => 'Visit this ' . ($p->category?->name ?? 'spot') . ' — rated ' . ($p->average_rating ?? 'n/a'),
                ])->toArray(),
            ];
        }

        return $days;
    }

    /**
     * Greedy nearest-neighbour ordering from the first (highest rated) stop.
     */
    protected function orderByProximity(\Illuminate\Support\Collection $places): \Illuminate\Support\Collection
    {
        $unvisited = $places->values();
        $ordered = collect();

        $current = $unvisited->shift();
        if ($current) {
            $ordered->push($current);
        }

        while ($unvisited->isNotEmpty()) {
            $nearestIndex = null;
            $nearestDist = PHP_FLOAT_MAX;

            foreach ($unvisited as $i => $candidate) {
                $dist = $this->haversine(
                    (float) $current->latitude,
                    (float) $current->longitude,
                    (float) $candidate->latitude,
                    (float) $candidate->longitude
                );
                if ($dist < $nearestDist) {
                    $nearestDist = $dist;
                    $nearestIndex = $i;
                }
            }

            if ($nearestIndex === null) {
                break;
            }

            $current = $unvisited->splice($nearestIndex, 1)->first();
            $ordered->push($current);
        }

        return $ordered;
    }

    /**
     * Most frequent category among a day's stops (ties resolved by rating).
     */
    protected function dominantCategory(\Illuminate\Support\Collection $stops): string
    {
        $counts = $stops->countBy(fn (Place $p) => $p->category?->name ?? 'attraction');
        if ($counts->isEmpty()) {
            return 'attraction';
        }
        $max = $counts->max();
        $candidates = $counts->filter(fn ($n) => $n === $max)->keys();
        $dominant = $stops->firstWhere(fn (Place $p) => $candidates->contains($p->category?->name ?? 'attraction'));
        return $dominant?->category?->name ?? 'attraction';
    }

    protected function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
