@extends('admin.layout')

@section('title', 'AI Agent Tasks')

@section('content')
<div class="p-6">
  @if(session('success'))
    <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3 text-sm">{{ session('success') }}</div>
  @endif
  @if(session('info'))
    <div class="mb-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 text-sm">{{ session('info') }}</div>
  @endif

  <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
    <div class="px-6 py-4 border-b border-gray-100">
      <h3 class="text-lg font-semibold text-gray-800">Create New Task</h3>
    </div>
    <div class="p-6">
      <form method="POST" action="{{ route('admin.ai.tasks.store') }}">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Agent</label>
            <select name="ai_agent_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
              <option value="">Select Agent...</option>
              @foreach($agents as $agent)
                <option value="{{ $agent->id }}">{{ $agent->name }} ({{ $agent->agent_type }})</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Task Type</label>
            <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" required>
              <option value="">Select type...</option>
              <option value="translate">Translate</option>
              <option value="moderate">Moderate</option>
              <option value="report">Report</option>
              <option value="manager">Manager</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Input Data (JSON)</label>
            <input name="input_data" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder='{} or leave empty'>
          </div>
        </div>
        <button type="submit" class="bg-primary-600 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-primary-700 transition-colors">Create & Process Task</button>
      </form>
    </div>
  </div>

  <div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-100">
      <h3 class="text-lg font-semibold text-gray-800">Task History</h3>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Agent</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Message</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Completed</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse($tasks as $task)
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-6 py-4 text-sm text-gray-600">{{ $task->id }}</td>
            <td class="px-6 py-4 text-sm text-gray-800 font-medium">{{ $task->agent?->name ?? 'Unknown' }}</td>
            <td class="px-6 py-4 text-sm"><code class="text-xs bg-gray-100 px-2 py-0.5 rounded text-gray-700">{{ $task->type }}</code></td>
            <td class="px-6 py-4 text-sm">
              @php
                $statusColors = [
                  'completed' => 'bg-green-100 text-green-700',
                  'failed' => 'bg-red-100 text-red-700',
                  'processing' => 'bg-blue-100 text-blue-700',
                  'pending' => 'bg-amber-100 text-amber-700',
                ];
                $color = $statusColors[$task->status] ?? 'bg-gray-100 text-gray-700';
              @endphp
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $color }}">
                {{ $task->status }}
              </span>
            </td>
            <td class="px-6 py-4 text-sm text-gray-600 max-w-xs truncate">
              @php
                $msg = $task->output_data['message'] ?? $task->error_message ?? '-';
              @endphp
              {{ $msg }}
            </td>
            <td class="px-6 py-4 text-sm text-gray-500">{{ $task->created_at->diffForHumans() }}</td>
            <td class="px-6 py-4 text-sm text-gray-500">{{ $task->completed_at?->diffForHumans() ?? '-' }}</td>
            <td class="px-6 py-4 text-sm">
              @if($task->status === 'failed')
                <a href="{{ route('admin.ai.tasks.retry', $task) }}" class="bg-amber-50 text-amber-600 rounded-lg px-3 py-1.5 text-xs font-medium hover:bg-amber-100 transition-colors">Retry</a>
              @endif
              @if($task->output_data || $task->error_message)
                <button class="bg-gray-50 text-gray-600 rounded-lg px-3 py-1.5 text-xs font-medium hover:bg-gray-100 transition-colors"
                  onclick="showTaskDetails({{ $task->id }})">Details</button>
              @endif
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="8" class="px-6 py-12 text-center text-sm text-gray-400">No tasks yet.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if(method_exists($tasks, 'links'))
      <div class="px-6 py-4 border-t border-gray-100">{{ $tasks->links() }}</div>
    @endif
  </div>
</div>
@endsection

@php
  $taskDetailsJs = $tasks->map(fn($t) => [
    'id' => $t->id,
    'agent' => $t->agent?->name ?? 'Unknown',
    'type' => $t->type,
    'status' => $t->status,
    'message' => $t->output_data['message'] ?? null,
    'output' => $t->output_data,
    'error' => $t->error_message,
    'created' => $t->created_at->format('Y-m-d H:i:s'),
    'completed' => $t->completed_at?->format('Y-m-d H:i:s') ?? '-',
  ])->values();
@endphp

@section('scripts')
<script>
const taskDetails = @json($taskDetailsJs);

function showTaskDetails(id) {
    const t = taskDetails.find(x => x.id === id);
    if (!t) return;
    const lines = [
        '═══════════════════════════════',
        `  Task #${t.id}  |  ${t.agent}`,
        '═══════════════════════════════',
        `  Status  : ${t.status.toUpperCase()}`,
        `  Type    : ${t.type}`,
        `  Created : ${t.created}`,
        `  Done    : ${t.completed}`,
    ];
    if (t.message) lines.push(`  Message : ${t.message}`);
    if (t.error) lines.push(``, `  ⚠ ERROR:`, `  ${t.error}`);
    if (t.output && Object.keys(t.output).length) {
        lines.push(``, `  Output:`);
        for (const [k, v] of Object.entries(t.output)) {
            if (k === 'message') continue;
            lines.push(`    ${k}: ${typeof v === 'object' ? JSON.stringify(v) : v}`);
        }
    }
    lines.push('───────────────────────────────');
    alert(lines.join('\n'));
}
</script>
@endsection
