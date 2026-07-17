<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiAgent;
use App\Models\AiAgentTask;
use App\Services\Ai\AgentOrchestrator;
use Illuminate\Http\Request;

class AiAgentTaskController extends Controller
{
    public function index()
    {
        $tasks = AiAgentTask::with('agent')->latest()->paginate(50);
        $agents = AiAgent::all();
        return view('admin.ai_agents_tasks', compact('tasks', 'agents'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'ai_agent_id' => 'required|exists:ai_agents,id',
            'type' => 'required|string|max:100',
            'input_data' => 'nullable|json',
        ]);

        if (isset($data['input_data']) && is_string($data['input_data'])) {
            $data['input_data'] = json_decode($data['input_data'], true);
        }

        $task = AiAgentTask::create(array_merge($data, ['status' => 'pending']));

        $orchestrator = app(AgentOrchestrator::class);
        $orchestrator->executeTask($task);

        return redirect()->route('admin.ai.tasks')->with('success', 'Task created and processed.');
    }

    public function retry(AiAgentTask $task, AgentOrchestrator $orchestrator)
    {
        $task->update(['status' => 'pending', 'error_message' => null, 'completed_at' => null]);
        $result = $orchestrator->executeTask($task);

        return redirect()->route('admin.ai.tasks')->with('info', "Task retried with status: {$result['status']}");
    }
}
