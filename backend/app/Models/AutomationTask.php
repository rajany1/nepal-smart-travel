<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationTask extends Model
{
    protected $fillable = [
        'task_key',
        'enabled',
        'last_status',
        'last_run_at',
        'last_finished_at',
        'last_duration_ms',
        'last_output',
        'last_error',
        'last_trigger',
        'last_triggered_by',
        'run_count',
        'fail_count',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_run_at' => 'datetime',
            'last_finished_at' => 'datetime',
            'last_duration_ms' => 'integer',
            'last_triggered_by' => 'integer',
            'run_count' => 'integer',
            'fail_count' => 'integer',
        ];
    }
}
