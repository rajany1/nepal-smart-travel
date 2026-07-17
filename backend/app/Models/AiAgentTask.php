<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAgentTask extends Model
{
    protected $fillable = [
        'ai_agent_id', 'type', 'status', 'input_data',
        'output_data', 'error_message', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'input_data' => 'array',
            'output_data' => 'array',
        ];
    }

    public function agent()
    {
        return $this->belongsTo(AiAgent::class, 'ai_agent_id');
    }
}
