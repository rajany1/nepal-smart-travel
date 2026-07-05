@extends('admin.layout')
@section('title', 'Activity Log')

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-800">Activity Log</h3>
        <p class="text-sm text-gray-500 mt-1">Track all admin and moderator actions across the system.</p>
    </div>

    <!-- Filters -->
    <div class="px-6 py-3 border-b border-gray-100">
        <form method="GET" action="{{ route('admin.audit-logs') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Action</label>
                <select name="action" class="text-sm border border-gray-300 rounded-lg px-3 py-1.5">
                    <option value="">All</option>
                    @foreach($actions as $a)
                    <option value="{{ $a }}" {{ request('action') === $a ? 'selected' : '' }}>{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">User</label>
                <select name="user_id" class="text-sm border border-gray-300 rounded-lg px-3 py-1.5">
                    <option value="">All</option>
                    @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="text-sm border border-gray-300 rounded-lg px-3 py-1.5">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="text-sm border border-gray-300 rounded-lg px-3 py-1.5">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search description..." class="text-sm border border-gray-300 rounded-lg px-3 py-1.5 w-48">
            </div>
            <div class="flex items-end gap-2">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">&nbsp;</label>
                    <label class="inline-flex items-center gap-1.5 text-sm cursor-pointer px-2 py-1.5 rounded border border-gray-300 hover:border-red-400 {{ request('suspicious') ? 'bg-red-50 border-red-400' : '' }}">
                        <input type="checkbox" name="suspicious" value="1" {{ request('suspicious') ? 'checked' : '' }} onchange="this.form.submit()" class="h-4 w-4 text-red-600 border-gray-300 rounded">
                        <span class="text-xs {{ request('suspicious') ? 'text-red-700 font-semibold' : 'text-gray-600' }}"><i class="fas fa-exclamation-triangle"></i> Suspicious only</span>
                    </label>
                </div>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700"><i class="fas fa-search"></i> Filter</button>
                <a href="{{ route('admin.audit-logs') }}" class="px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Clear</a>
            </div>
        </form>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Time</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">User</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Action</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Description</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">IP Address</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($logs as $log)
                @php $suspicious = $log->metadata['suspicious'] ?? false; @endphp
                <tr class="hover:bg-gray-50 {{ $suspicious ? 'bg-red-50' : '' }}">
                    <td class="px-6 py-3 text-sm text-gray-500 whitespace-nowrap">
                        <span title="{{ $log->created_at }}">{{ $log->created_at->diffForHumans() }}</span>
                        <br><span class="text-xs text-gray-400">{{ $log->created_at->format('M d, H:i') }}</span>
                    </td>
                    <td class="px-6 py-3">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-full {{ $suspicious ? 'bg-red-200' : 'bg-indigo-100' }} flex items-center justify-center flex-shrink-0">
                                <span class="text-xs font-bold {{ $suspicious ? 'text-red-700' : 'text-indigo-600' }}">{{ strtoupper(substr($log->user?->name ?? '?', 0, 1)) }}</span>
                            </span>
                            <span class="text-sm text-gray-900">{{ $log->user?->name ?? 'System' }}</span>
                            @if($suspicious)
                            <span class="text-xs font-semibold text-red-600 bg-red-100 px-1.5 py-0.5 rounded" title="{{ $log->metadata['suspicious_reason'] ?? 'Suspicious activity' }}"><i class="fas fa-exclamation-triangle"></i> Suspicious</span>
                            @endif
                        </div>
                    </td>
                    <td class="px-6 py-3">
                        @php
                            $actionColors = [
                                'report.approved' => 'bg-green-100 text-green-800',
                                'report.rejected' => 'bg-red-100 text-red-800',
                                'report.deleted' => 'bg-red-100 text-red-800',
                                'user.toggle-status' => 'bg-orange-100 text-orange-800',
                                'user.make-admin' => 'bg-purple-100 text-purple-800',
                                'user.remove-admin' => 'bg-orange-100 text-orange-800',
                                'user.make-moderator' => 'bg-yellow-100 text-yellow-800',
                                'user.remove-moderator' => 'bg-orange-100 text-orange-800',
                                'user.assign-role' => 'bg-blue-100 text-blue-800',
                                'moderator.permissions-updated' => 'bg-cyan-100 text-cyan-800',
                                'settings.updated' => 'bg-gray-100 text-gray-800',
                                'place.created' => 'bg-teal-100 text-teal-800',
                                'place.deleted' => 'bg-red-100 text-red-800',
                                'place.feature' => 'bg-amber-100 text-amber-800',
                                'alert.created' => 'bg-rose-100 text-rose-800',
                                'alert.deleted' => 'bg-red-100 text-red-800',
                                'security.login-failed' => 'bg-red-100 text-red-800',
                                'security.unauthorized-login' => 'bg-red-200 text-red-900',
                                'security.unauthorized-access' => 'bg-red-100 text-red-800',
                                'security.permission-denied' => 'bg-orange-100 text-orange-800',
                            ];
                            $color = $actionColors[$log->action] ?? 'bg-slate-100 text-slate-700';
                        @endphp
                        <span class="text-xs font-medium px-2 py-0.5 rounded {{ $color }}">{{ $log->action }}</span>
                    </td>
                    <td class="px-6 py-3 text-sm text-gray-600 max-w-md truncate" title="{{ $log->description }}">{{ $log->description }}</td>
                    <td class="px-6 py-3 text-sm text-gray-400 font-mono">{{ $log->ip_address ?? '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                        <i class="fas fa-history text-3xl text-gray-300 mb-3 block"></i>
                        No activity found matching your filters.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
    <div class="px-6 py-4 border-t border-gray-100">
        {{ $logs->links() }}
    </div>
    @endif
</div>
@endsection