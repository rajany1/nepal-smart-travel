<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationRun extends Model
{
    protected $fillable = [
        'task_key',
        'status',
        'trigger',
        'triggered_by',
        'started_at',
        'finished_at',
        'duration_ms',
        'output',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'duration_ms' => 'integer',
            'triggered_by' => 'integer',
        ];
    }

    /** Keep only the newest runs so a noisy failing task cannot bloat the table. */
    public static function prune(int $keep = 500): void
    {
        $cutoff = static::query()->orderByDesc('id')->skip($keep)->value('id');

        if ($cutoff) {
            static::query()->where('id', '<=', $cutoff)->delete();
        }
    }
}
