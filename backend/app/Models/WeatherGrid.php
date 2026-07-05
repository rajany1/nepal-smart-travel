<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WeatherGrid extends Model
{
    protected $table = 'weather_grid';

    protected $fillable = [
        'grid_lat',
        'grid_lng',
        'weather_code',
        'temperature',
        'precipitation',
        'wind_speed',
        'humidity',
        'fetched_at',
    ];

    protected $casts = [
        'fetched_at' => 'datetime',
    ];
}
