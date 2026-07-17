<?php

namespace App\Services\Ai\Handlers;

use App\Models\AiAgent;
use App\Models\AiAgentTask;
use App\Services\Ai\GeminiService;
use App\Services\Ai\GroqService;

abstract class BaseHandler
{
    protected AiAgent $agent;

    public function __construct(AiAgent $agent)
    {
        $this->agent = $agent;
    }

    abstract public function handle(AiAgentTask $task): AiAgentTask;

    protected function ai(): GeminiService|GroqService
    {
        $provider = $this->agent->provider ?? config('services.ai.provider', 'gemini');
        $model = $this->agent->model ?: config('services.ai.model', 'gemini-2.0-flash');

        return match ($provider) {
            'groq' => new GroqService($model),
            default => new GeminiService($model),
        };
    }

    protected function markComplete(AiAgentTask $task, mixed $output): AiAgentTask
    {
        $task->update([
            'status' => 'completed',
            'output_data' => is_string($output) ? ['result' => $output] : $output,
            'completed_at' => now(),
        ]);
        return $task;
    }

    protected function markFailed(AiAgentTask $task, string $error): AiAgentTask
    {
        $task->update([
            'status' => 'failed',
            'error_message' => $error,
            'completed_at' => now(),
        ]);
        return $task;
    }

    protected function autoWork(): array
    {
        return [];
    }

    public function getAgent(): AiAgent
    {
        return $this->agent;
    }
}
