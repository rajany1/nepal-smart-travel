<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\WeatherGrid;

class WeatherController extends Controller
{
    public function grid(Request $request)
    {
        $query = WeatherGrid::query();

        if ($request->has(['min_lat', 'max_lat', 'min_lng', 'max_lng'])) {
            $buffer = 0.1;
            $query->whereBetween('grid_lat', [
                $request->float('min_lat') - $buffer,
                $request->float('max_lat') + $buffer,
            ])->whereBetween('grid_lng', [
                $request->float('min_lng') - $buffer,
                $request->float('max_lng') + $buffer,
            ]);
        }

        $latest = WeatherGrid::max('fetched_at');

        $data = $query->where('fetched_at', $latest)
            ->get(['grid_lat', 'grid_lng', 'weather_code', 'temperature', 'precipitation', 'wind_speed', 'humidity']);

        return response()->json([
            'success' => true,
            'fetched_at' => $latest,
            'count' => $data->count(),
            'data' => $data->map(fn($row) => [
                'lat' => (float) $row->grid_lat,
                'lng' => (float) $row->grid_lng,
                'code' => (int) $row->weather_code,
                'temp' => $row->temperature !== null ? (float) $row->temperature : null,
                'precip' => $row->precipitation !== null ? (float) $row->precipitation : null,
                'wind' => $row->wind_speed !== null ? (float) $row->wind_speed : null,
                'humid' => $row->humidity !== null ? (float) $row->humidity : null,
            ]),
        ]);
    }
}
