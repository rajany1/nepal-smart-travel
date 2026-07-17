<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAgent extends Model
{
    protected $fillable = [
        'name', 'agent_type', 'description', 'system_prompt', 'status', 'model',
        'provider', 'capabilities', 'config', 'last_active_at',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'config' => 'array',
            'last_active_at' => 'datetime',
        ];
    }

    public function tasks()
    {
        return $this->hasMany(AiAgentTask::class);
    }
}
