<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiAgent;
use App\Models\AiAgentTask;
use App\Services\Ai\AgentOrchestrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AiAgentController extends Controller
{
    private function requireAdmin(Request $request): void
    {
        $user = Auth::user();
        if (!$user || !$user->isAdmin() && !$user->isModerator()) {
            abort(403, 'Unauthorized');
        }

        $routeName = $request->route()?->getName();
        if ($routeName) {
            $routePerms = \App\Models\Permission::where('route_name', $routeName)->get();
            if ($routePerms->isNotEmpty() && !$routePerms->contains(fn($p) => $user->hasPermission($p->name))) {
                abort(403, 'You do not have permission for this page.');
            }
        }
    }

    public function index(Request $request)
    {
        $this->requireAdmin($request);

        $agents = AiAgent::withCount(['tasks' => fn($q) => $q->where('status', 'pending')])->get();
        return view('admin.ai_agents', compact('agents'));
    }

    public function store(Request $request)
    {
        $this->requireAdmin($request);

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
        $this->requireAdmin($request);

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

    public function run(Request $request, AiAgent $agent, AgentOrchestrator $orchestrator)
    {
        $this->requireAdmin($request);

        $task = AiAgentTask::create([
            'ai_agent_id' => $agent->id,
            'type' => 'manual-run',
            'status' => 'pending',
            'input_data' => ['action' => 'auto'],
        ]);

        $result = $orchestrator->executeTask($task);

        return redirect()->route('admin.ai.agents')->with('info',
            "Agent task completed with status: {$result['status']}");
    }
}
