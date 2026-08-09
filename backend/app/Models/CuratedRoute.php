<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CuratedRoute extends Model
{
    protected $fillable = [
        'title', 'slug', 'description', 'image', 'duration_days',
        'best_season', 'waypoints', 'is_active',
    ];

    protected $casts = [
        'waypoints' => 'array',
        'is_active' => 'boolean',
    ];

    public function waypointPlaces(): array
    {
        if (empty($this->waypoints)) return [];
        return Place::whereIn('id', $this->waypoints)->where('is_active', true)->get()->all();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
