<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuratedRoute extends Model
{
    protected $fillable = [
        'title', 'slug', 'route_type', 'difficulty', 'description', 'image', 'duration_days',
        'best_season', 'max_altitude_m', 'total_distance_km', 'elevation_gain_m',
        'starting_point', 'ending_point', 'waypoints', 'track', 'is_active',
    ];

    protected $casts = [
        'waypoints' => 'array',
        'track' => 'array',
        'is_active' => 'boolean',
        'max_altitude_m' => 'integer',
        'total_distance_km' => 'float',
        'elevation_gain_m' => 'integer',
    ];

    public const TYPES = ['itinerary', 'trekking'];
    public const DIFFICULTIES = ['easy', 'moderate', 'challenging', 'hard'];

    public function waypointPlaces(): array
    {
        if (empty($this->waypoints)) return [];
        return Place::whereIn('id', $this->waypoints)->where('is_active', true)->get()->all();
    }

    /**
     * GPS track points as [['lat' => .., 'lng' => .., 'name' => '?'], ...].
     * Accepts both ['lat' => x, 'lng' => y] maps and [lat, lng] pairs.
     */
    public function trackPoints(): array
    {
        if (empty($this->track) || !is_array($this->track)) return [];
        $points = [];
        foreach ($this->track as $p) {
            if (is_array($p) && isset($p['lat']) && isset($p['lng'])) {
                $points[] = [
                    'lat' => (float) $p['lat'],
                    'lng' => (float) $p['lng'],
                    'name' => $p['name'] ?? null,
                ];
            } elseif (is_array($p) && count($p) >= 2) {
                $vals = array_values($p);
                $points[] = [
                    'lat' => (float) $vals[0],
                    'lng' => (float) $vals[1],
                    'name' => $vals[2] ?? null,
                ];
            }
        }
        return $points;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeType($query, string $type)
    {
        return $query->where('route_type', $type);
    }

    public function scopeDifficulty($query, string $difficulty)
    {
        return $query->where('difficulty', $difficulty);
    }

    public function difficultyLabel(): string
    {
        return match ($this->difficulty) {
            'easy' => 'Easy',
            'moderate' => 'Moderate',
            'challenging' => 'Challenging',
            'hard' => 'Hard',
            default => $this->difficulty ? ucfirst($this->difficulty) : '—',
        };
    }
}
