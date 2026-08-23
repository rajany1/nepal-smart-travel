@extends('admin.layout')

@section('title', 'Translator')

@section('content')
<div class="p-6">
  @if(session('success'))
    <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-3 text-sm">{{ session('success') }}</div>
  @endif
  @if(session('info'))
    <div class="mb-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 text-sm">{{ session('info') }}</div>
  @endif

  <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
      <h3 class="text-lg font-semibold text-gray-800">Add / Edit Word</h3>
      <span class="text-xs text-gray-500">App UI (English â†’ Nepali) dictionary. Words go live on the app's next fetch.</span>
    </div>
    <div class="p-6">
      <form method="POST" action="{{ route('admin.translator.store') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        @csrf
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">English word</label>
          <input name="term" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="e.g. Settings" required>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Nepali word</label>
          <input name="nepali" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="e.g. à¤¸à¥‡à¤Ÿà¤¿à¤™à¥à¤¸" required>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Context <span class="text-gray-400">(optional)</span></label>
          <input name="context" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="e.g. ui / place">
        </div>
        <div class="flex items-end">
          <label class="flex items-center gap-2 mr-4 text-sm text-gray-700 mb-2">
            <input type="checkbox" name="is_active" value="1" checked class="rounded"> Active
          </label>
          <button type="submit" class="bg-primary-600 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-primary-700 transition-colors">Save Word</button>
        </div>
      </form>
    </div>
  </div>

  <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-6">
    <div class="px-6 py-4 border-b border-gray-100">
      <h3 class="text-lg font-semibold text-gray-800">Bulk Import</h3>
    </div>
    <div class="p-6">
      <form method="POST" action="{{ route('admin.translator.import') }}">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div class="md:col-span-3">
            <label class="block text-sm font-medium text-gray-700 mb-1">One pair per line â€” <code class="text-xs bg-gray-100 px-1.5 py-0.5 rounded">English word = Nepali word</code></label>
            <textarea name="bulk_text" rows="5" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Home = à¤—à¥ƒà¤¹&#10;Alerts = à¤¸à¥‚à¤šà¤¨à¤¾à¤¹à¤°à¥‚&#10;Emergency = à¤†à¤ªà¤¤à¤•à¤¾à¤²à¥€à¤¨"></textarea>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Context <span class="text-gray-400">(optional)</span></label>
            <input name="bulk_context" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="ui">
            <button type="submit" class="mt-3 w-full bg-gray-800 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-gray-900 transition-colors">Import Lines</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
      <h3 class="text-lg font-semibold text-gray-800">Dictionary ({{ $translations->total() }} words)</h3>
      <form method="GET" action="{{ route('admin.translator') }}" class="flex items-center gap-2">
        <input name="search" value="{{ $search }}" placeholder="Search words..." class="px-3 py-2 border border-gray-300 rounded-lg text-sm w-64 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
        <button type="submit" class="bg-primary-600 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-primary-700 transition-colors">Search</button>
        @if($search)
          <a href="{{ route('admin.translator') }}" class="text-sm text-gray-500 hover:text-gray-700">Clear</a>
        @endif
      </form>
    </div>
    <div class="overflow-x-auto">
<div id="liveTable">
      <table class="w-full">
        <thead class="bg-gray-50">
          <tr>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">English</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Nepali</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Context</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
            <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @forelse($translations as $t)
          <tr class="hover:bg-gray-50 transition-colors {{ $t->is_active ? '' : 'opacity-60' }}">
            <td class="px-6 py-4 text-sm text-gray-600">{{ $t->id }}</td>
            <td class="px-6 py-4 text-sm">
              <form method="POST" action="{{ route('admin.translator.update', $t) }}" class="flex items-center gap-2">
                @csrf
                <input name="term" value="{{ $t->term }}" class="w-44 px-2 py-1 border border-gray-200 rounded text-sm focus:ring-1 focus:ring-primary-500 font-medium text-gray-800">
            </td>
            <td class="px-6 py-4 text-sm">
                <input name="nepali" value="{{ $t->nepali }}" class="w-44 px-2 py-1 border border-gray-200 rounded text-sm focus:ring-1 focus:ring-primary-500 text-gray-800">
            </td>
            <td class="px-6 py-4 text-sm">
                <input name="context" value="{{ $t->context }}" class="w-24 px-2 py-1 border border-gray-200 rounded text-sm focus:ring-1 focus:ring-primary-500 text-gray-600">
            </td>
            <td class="px-6 py-4 text-sm">
                <select name="is_active" class="text-sm border border-gray-200 rounded px-2 py-1 focus:ring-1 focus:ring-primary-500">
                  <option value="1" {{ $t->is_active ? 'selected' : '' }}>Active</option>
                  <option value="0" {{ !$t->is_active ? 'selected' : '' }}>Inactive</option>
                </select>
              </form>
            </td>
            <td class="px-6 py-4 text-sm whitespace-nowrap">
              <form method="POST" action="{{ route('admin.translator.update', $t) }}" class="inline">
                @csrf
                <button type="submit" class="text-xs bg-blue-50 text-blue-600 px-2.5 py-1 rounded hover:bg-blue-100 transition-colors">Update</button>
              </form>
              <form method="POST" action="{{ route('admin.translator.toggle', $t) }}" class="inline">
                @csrf
                <button type="submit" class="text-xs {{ $t->is_active ? 'bg-amber-50 text-amber-600 hover:bg-amber-100' : 'bg-green-50 text-green-600 hover:bg-green-100' }} px-2.5 py-1 rounded transition-colors">{{ $t->is_active ? 'Deactivate' : 'Activate' }}</button>
              </form>
              <form method="POST" action="{{ route('admin.translator.delete', $t) }}" class="inline" onsubmit="return confirm('Delete this word?');">
                @csrf
                <button type="submit" class="text-xs bg-red-50 text-red-600 px-2.5 py-1 rounded hover:bg-red-100 transition-colors">Delete</button>
              </form>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500">No words found{{ $search ? " matching \"{$search}\"" : '' }}.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    @if($translations->hasPages())
      <div class="px-6 py-4 border-t border-gray-100">{{ $translations->links() }}</div>
</div>
    @endif
  </div>
</div>
@endsection
