<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiAgent;
use App\Models\AiAgentTask;
use App\Services\Ai\AgentOrchestrator;
use Illuminate\Http\Request;

class AiAgentController extends Controller
{
    public function index()
    {
        $agents = AiAgent::withCount(['tasks' => fn($q) => $q->where('status', 'pending')])->get();
        return view('admin.ai_agents', compact('agents'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'agent_type' => 'required|string|max:100|unique:ai_agents,agent_type',
            'description' => 'nullable|string',
            'model' => 'nullable|string|max:100',
            'provider' => 'nullable|string|in:gemini,groq',
            'system_prompt' => 'nullable|string',
            'capabilities' => 'nullable|json',
            'config' => 'nullable|json',
        ]);

        if (isset($data['capabilities']) && is_string($data['capabilities'])) {
            $data['capabilities'] = json_decode($data['capabilities'], true);
        }
        if (isset($data['config']) && is_string($data['config'])) {
            $data['config'] = json_decode($data['config'], true);
        }

        AiAgent::create($data);

        return redirect()->route('admin.ai.agents')->with('success', 'AI Agent created successfully.');
    }

    public function update(Request $request, AiAgent $agent)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|string|in:idle,working,error,paused',
            'model' => 'nullable|string|max:100',
            'provider' => 'nullable|string|in:gemini,groq',
            'system_prompt' => 'nullable|string',
        ]);

        $agent->update($data);

        return redirect()->route('admin.ai.agents')->with('success', 'AI Agent updated.');
    }

    public function run(AiAgent $agent, AgentOrchestrator $orchestrator)
    {
        $task = AiAgentTask::create([
            'ai_agent_id' => $agent->id,
            'type' => 'manual-run',
            'status' => 'pending',
            'input_data' => ['action' => 'assess'],
        ]);

        $result = $orchestrator->executeTask($task);

        return redirect()->route('admin.ai.agents')->with('info',
            "Agent task completed with status: {$result['status']}");
    }
}
