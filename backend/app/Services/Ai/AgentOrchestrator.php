<?php

namespace App\Services\Ai;

use App\Models\AiAgent;
use App\Models\AiAgentTask;
use Illuminate\Support\Facades\Log;

class AgentOrchestrator
{
    public function runPendingTasks(): array
    {
        $results = [];

        $pendingTasks = AiAgentTask::with('agent')
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get();

        foreach ($pendingTasks as $task) {
            $results[] = $this->executeTask($task);
        }

        return $results;
    }

    public function runAutoWork(): array
    {
        $results = [];

        $agents = AiAgent::whereIn('agent_type', ['translator', 'review_moderator'])->get();

        foreach ($agents as $agent) {
            $handlerClass = $this->resolveHandler($agent->agent_type);
            if (!$handlerClass) continue;

            $task = AiAgentTask::create([
                'ai_agent_id' => $agent->id,
                'type' => 'auto-work',
                'status' => 'pending',
                'input_data' => ['action' => 'auto'],
            ]);

            try {
                $handler = new $handlerClass($agent);
                $result = $handler->handle($task);
                $results[] = [
                    'agent' => $agent->name,
                    'task' => $task->id,
                    'status' => $result->status,
                    'output' => $result->output_data,
                ];
            } catch (\Exception $e) {
                Log::error("Auto-work failed for {$agent->name}: " . $e->getMessage());
                $task->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            }
        }

        return $results;
    }

    public function runPendingReports(): array
    {
        $results = [];
        $agent = AiAgent::where('agent_type', 'report_manager')->first();
        if (!$agent) return $results;

        $handlerClass = $this->resolveHandler('report_manager');
        if (!$handlerClass) return $results;

        $task = AiAgentTask::create([
            'ai_agent_id' => $agent->id,
            'type' => 'process-pending',
            'status' => 'pending',
            'input_data' => ['action' => 'process-pending'],
        ]);

        try {
            $handler = new $handlerClass($agent);
            $result = $handler->handle($task);
            $results[] = [
                'agent' => $agent->name,
                'task' => $task->id,
                'status' => $result->status,
                'output' => $result->output_data,
            ];
        } catch (\Exception $e) {
            Log::error("Report processing failed: " . $e->getMessage());
            $task->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
        }

        return $results;
    }

    public function executeTask(AiAgentTask $task): array
    {
        $agent = $task->agent;
        if (!$agent) {
            $task->update(['status' => 'failed', 'error_message' => 'Agent not found']);
            return ['task' => $task->id, 'status' => 'failed'];
        }

        $handlerClass = $this->resolveHandler($agent->agent_type);
        if (!$handlerClass) {
            $task->update(['status' => 'failed', 'error_message' => "No handler for agent_type: {$agent->agent_type}"]);
            return ['task' => $task->id, 'status' => 'failed'];
        }

        $task->update(['started_at' => now()]);

        try {
            $handler = new $handlerClass($agent);
            $result = $handler->handle($task);
            $agent->update(['last_active_at' => now(), 'status' => 'idle']);

            return [
                'task' => $task->id,
                'agent' => $agent->name,
                'status' => $result->status,
                'output' => $result->output_data,
            ];
        } catch (\Exception $e) {
            $task->update(['status' => 'failed', 'error_message' => $e->getMessage(), 'completed_at' => now()]);
            $agent->update(['last_active_at' => now(), 'status' => 'error']);

            return ['task' => $task->id, 'agent' => $agent->name, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    public function runOrchestrate(): array
    {
        $manager = AiAgent::where('agent_type', 'manager')->first();
        if (!$manager) return [];

        $handlerClass = $this->resolveHandler('manager');
        if (!$handlerClass) return [];

        $task = AiAgentTask::create([
            'ai_agent_id' => $manager->id,
            'type' => 'orchestrate',
            'status' => 'pending',
            'input_data' => ['action' => 'orchestrate'],
        ]);

        try {
            $handler = new $handlerClass($manager);
            $result = $handler->handle($task);
            $delegated = $result->output_data['delegated_tasks'] ?? [];

            return [
                'task' => $task->id,
                'status' => $result->status,
                'delegated' => count($delegated),
            ];
        } catch (\Exception $e) {
            Log::error("Orchestration failed: " . $e->getMessage());
            $task->update(['status' => 'failed', 'error_message' => $e->getMessage()]);
            return ['task' => $task->id, 'status' => 'failed'];
        }
    }

    protected function resolveHandler(string $agentType): ?string
    {
        return match ($agentType) {
            'translator' => \App\Services\Ai\Handlers\TranslationHandler::class,
            'translation' => \App\Services\Ai\Handlers\TranslationHandler::class,
            'review_moderator' => \App\Services\Ai\Handlers\ReviewModeratorHandler::class,
            'report_manager' => \App\Services\Ai\Handlers\ReportManagerHandler::class,
            'manager' => \App\Services\Ai\Handlers\ManagerAiHandler::class,
            default => null,
        };
    }
}
