<?php

namespace App\Services\Ai\Handlers;

use App\Models\AiAgentTask;
use App\Models\AiAgent;
use App\Models\AiAgentTask as Task;
use Illuminate\Support\Facades\Log;

class ManagerAiHandler extends BaseHandler
{
    public function handle(AiAgentTask $task): AiAgentTask
    {
        $input = $task->input_data;
        $action = $input['action'] ?? 'orchestrate';

        if ($action === 'orchestrate') {
            return $this->orchestrate($task);
        }
        if ($action === 'assess') {
            return $this->assessWorkload($task);
        }
        if ($action === 'delegate' && isset($input['agent_type'])) {
            return $this->delegateTask($task, $input['agent_type'], $input['task_data'] ?? []);
        }

        return $this->markFailed($task, 'Unknown action: ' . $action);
    }

    protected function orchestrate(AiAgentTask $task): AiAgentTask
    {
        $delegated = [];

        $workTypes = [
            ['agent_type' => 'report_manager', 'type' => 'process-pending', 'data' => ['action' => 'process-pending']],
            ['agent_type' => 'review_moderator', 'type' => 'auto-moderate', 'data' => ['action' => 'auto']],
            ['agent_type' => 'translation', 'type' => 'auto-translate', 'data' => ['type' => 'auto']],
        ];

        foreach ($workTypes as $work) {
            $target = AiAgent::where('agent_type', $work['agent_type'])->where('status', '!=', 'paused')->first();
            if (!$target) continue;

            $pendingCount = Task::where('ai_agent_id', $target->id)->where('status', 'pending')->count();
            if ($pendingCount > 3) continue;

            $subTask = Task::create([
                'ai_agent_id' => $target->id,
                'type' => $work['type'],
                'status' => 'pending',
                'input_data' => $work['data'],
            ]);

            $delegated[] = [
                'agent' => $target->name,
                'task_id' => $subTask->id,
                'type' => $work['type'],
            ];
        }

        $names = array_map(fn($d) => $d['agent'], $delegated);
        $msg = count($delegated) . ' task(s) delegated to: ' . implode(', ', $names);
        return $this->markComplete($task, [
            'orchestrated' => count($delegated),
            'delegated_tasks' => $delegated,
            'message' => $msg,
        ]);
    }

    protected function assessWorkload(AiAgentTask $task): AiAgentTask
    {
        $agents = AiAgent::all();
        $report = [];

        foreach ($agents as $agent) {
            $pendingTasks = Task::where('ai_agent_id', $agent->id)->where('status', 'pending')->count();
            $report[] = [
                'agent' => $agent->name,
                'agent_type' => $agent->agent_type,
                'status' => $agent->status,
                'pending_tasks' => $pendingTasks,
            ];
        }

        $msg = count($report) . ' agents assessed, ' . collect($report)->sum('pending_tasks') . ' pending tasks total';
        return $this->markComplete($task, ['agents' => $report, 'message' => $msg]);
    }

    protected function delegateTask(AiAgentTask $task, string $agentType, array $taskData): AiAgentTask
    {
        $target = AiAgent::where('agent_type', $agentType)->first();
        if (!$target) {
            return $this->markFailed($task, "No agent found with type: {$agentType}");
        }

        $newTask = Task::create([
            'ai_agent_id' => $target->id,
            'type' => $taskData['type'] ?? 'general',
            'status' => 'pending',
            'input_data' => $taskData['data'] ?? [],
        ]);

        $msg = "Delegated task to {$target->name} (task #{$newTask->id})";
        return $this->markComplete($task, [
            'delegated_to' => $target->name,
            'task_id' => $newTask->id,
            'message' => $msg,
        ]);
    }
}
