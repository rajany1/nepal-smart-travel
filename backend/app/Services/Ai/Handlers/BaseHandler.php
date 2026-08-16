<?php

namespace App\Services\Ai\Handlers;

use App\Models\AiAgent;
use App\Models\AiAgentTask;
use App\Services\Ai\AiFallbackRouter;
use App\Services\Ai\AiProviderInterface;
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

    /**
     * Returns a fallback router: the agent's own provider first, then the
     * configured text chain for the other providers. Agents stay resilient
     * when their primary provider hits quota limits.
     */
    protected function ai(): AiProviderInterface
    {
        $provider = $this->agent->provider ?? config('services.ai.provider', 'gemini');
        $model = $this->agent->model ?: config('services.ai.model', 'gemini-2.0-flash');

        $attempts = match ($provider) {
            'groq' => [['label' => 'groq:' . $model, 'provider' => new GroqService($model)]],
            default => [['label' => 'gemini:' . $model, 'provider' => new GeminiService($model)]],
        };

        $chain = AiFallbackRouter::textChain();
        $primaryPrefix = $provider === 'groq' ? 'groq:' : 'gemini:';

        foreach ($chain->getAttempts() as $attempt) {
            if (str_starts_with((string) ($attempt['label'] ?? ''), $primaryPrefix)) {
                continue;
            }
            $attempts[] = $attempt;
        }

        return new AiFallbackRouter($attempts, null, $chain->getKeyPool());
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
