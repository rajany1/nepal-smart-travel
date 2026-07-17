@extends('admin.layout')

@section('title', 'AI Employees')

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
      <h3 class="text-lg font-semibold text-gray-800">Register New AI Agent</h3>
    </div>
    <div class="p-6">
      <form method="POST" action="{{ route('admin.ai.agents.store') }}">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Agent Name</label>
            <input name="name" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Agent Name" required>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Agent Type</label>
            <input name="agent_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="e.g. translator" required>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Provider</label>
            <select name="provider" id="provider" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
              <option value="gemini">Google Gemini</option>
              <option value="groq">Groq (Llama / Mixtral)</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Model</label>
            <input name="model" id="model-input" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="gemini-2.0-flash" list="model-suggestions">
            <datalist id="model-suggestions">
              <option value="gemini-2.0-flash" data-provider="gemini">Gemini 2.0 Flash</option>
              <option value="gemini-2.5-flash" data-provider="gemini">Gemini 2.5 Flash</option>
              <option value="gemini-1.5-pro" data-provider="gemini">Gemini 1.5 Pro</option>
              <option value="gemini-2.5-pro" data-provider="gemini">Gemini 2.5 Pro</option>
              <option value="llama-3.3-70b-versatile" data-provider="groq">Llama 3.3 70B (Groq)</option>
              <option value="llama-3.1-8b-instant" data-provider="groq">Llama 3.1 8B Instant (Groq)</option>
              <option value="llama-4-scout-17b-16e-instruct" data-provider="groq">Llama 4 Scout (Groq)</option>
              <option value="mixtral-8x7b-32768" data-provider="groq">Mixtral 8x7B (Groq)</option>
              <option value="qwen-2.5-32b" data-provider="groq">Qwen 2.5 32B (Groq)</option>
            </datalist>
          </div>
        </div>
        <div class="mb-4">
          <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
          <textarea name="description" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" rows="2" placeholder="Description"></textarea>
        </div>
        <button type="submit" class="bg-primary-600 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-primary-700 transition-colors">Register Agent</button>
      </form>
    </div>
  </div>

  <div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-100">
      <h3 class="text-lg font-semibold text-gray-800">Registered AI Agents</h3>
    </div>
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Provider</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Model</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Pending</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Last Active</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse($agents as $agent)
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-6 py-4 text-sm text-gray-600">{{ $agent->id }}</td>
            <td class="px-6 py-4 text-sm text-gray-800 font-medium">
              <form method="POST" action="{{ route('admin.ai.agents.update', $agent) }}" class="flex items-center gap-2">
                @csrf
                <input name="name" value="{{ $agent->name }}" class="w-40 px-2 py-1 border border-gray-200 rounded text-sm focus:ring-1 focus:ring-primary-500">
            </td>
            <td class="px-6 py-4 text-sm"><code class="text-xs bg-gray-100 px-2 py-0.5 rounded text-gray-700">{{ $agent->agent_type }}</code></td>
            <td class="px-6 py-4 text-sm">
              <select name="status" class="text-sm border border-gray-200 rounded px-2 py-1 focus:ring-1 focus:ring-primary-500">
                <option value="idle" {{ $agent->status=='idle'?'selected':'' }}>Idle</option>
                <option value="working" {{ $agent->status=='working'?'selected':'' }}>Working</option>
                <option value="error" {{ $agent->status=='error'?'selected':'' }}>Error</option>
                <option value="paused" {{ $agent->status=='paused'?'selected':'' }}>Paused</option>
              </select>
            </td>
            <td class="px-6 py-4 text-sm">
              <select name="provider" class="text-sm border border-gray-200 rounded px-2 py-1 focus:ring-1 focus:ring-primary-500">
                <option value="gemini" {{ ($agent->provider ?? 'gemini')=='gemini'?'selected':'' }}>Gemini</option>
                <option value="groq" {{ ($agent->provider ?? '')=='groq'?'selected':'' }}>Groq</option>
              </select>
            </td>
            <td class="px-6 py-4 text-sm">
              <input name="model" value="{{ $agent->model }}" list="model-list-{{ $agent->id }}" class="w-32 px-2 py-1 border border-gray-200 rounded text-sm focus:ring-1 focus:ring-primary-500">
              <datalist id="model-list-{{ $agent->id }}">
                <option value="gemini-2.0-flash">Gemini 2.0 Flash</option>
                <option value="gemini-2.5-flash">Gemini 2.5 Flash</option>
                <option value="gemini-1.5-pro">Gemini 1.5 Pro</option>
                <option value="gemini-2.5-pro">Gemini 2.5 Pro</option>
                <option value="llama-3.3-70b-versatile">Llama 3.3 70B</option>
                <option value="llama-3.1-8b-instant">Llama 3.1 8B</option>
                <option value="llama-4-scout-17b-16e-instruct">Llama 4 Scout</option>
                <option value="mixtral-8x7b-32768">Mixtral 8x7B</option>
                <option value="qwen-2.5-32b">Qwen 2.5 32B</option>
              </datalist>
            </td>
            <td class="px-6 py-4 text-sm text-gray-600">
              <span class="bg-primary-50 text-primary-600 text-xs font-medium px-2.5 py-0.5 rounded-full">{{ $agent->tasks_count }}</span>
            </td>
            <td class="px-6 py-4 text-sm text-gray-500">{{ $agent->last_active_at?->diffForHumans() ?? 'Never' }}</td>
            <td class="px-6 py-4 text-sm">
                <button type="submit" class="bg-primary-50 text-primary-600 rounded-lg px-3 py-1.5 text-xs font-medium hover:bg-primary-100 transition-colors">Update</button>
              </form>
              <a href="{{ route('admin.ai.agents.run', $agent) }}" class="bg-green-50 text-green-600 rounded-lg px-3 py-1.5 text-xs font-medium hover:bg-green-100 transition-colors">Run Now</a>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="9" class="px-6 py-12 text-center text-sm text-gray-400">No AI agents registered yet.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
