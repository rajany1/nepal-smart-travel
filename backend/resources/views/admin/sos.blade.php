@extends('admin.layout')
@section('title', 'SOS Emergency Management')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">SOS Emergency Alerts</h2>
            <p class="text-sm text-slate-500 mt-1">Monitor and manage SOS alerts, false alarms, and user restrictions.</p>
        </div>
    </div>

    {{-- Stats --}}
    @php
        $totalSos = \App\Models\SosAlert::count();
        $activeSos = \App\Models\SosAlert::where('status', 'active')->count();
        $falseCount = \App\Models\SosReport::count();
        $restrictedUsers = \App\Models\User::where('sos_restricted_until', '>', now())->count();
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl p-5 border border-slate-200">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-red-100 flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-red-600"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-slate-900">{{ number_format($totalSos) }}</p>
                    <p class="text-xs text-slate-500">Total SOS</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-slate-200">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-orange-100 flex items-center justify-center">
                    <i class="fas fa-bell text-orange-600"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-slate-900">{{ number_format($activeSos) }}</p>
                    <p class="text-xs text-slate-500">Active SOS</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-slate-200">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-yellow-100 flex items-center justify-center">
                    <i class="fas fa-flag text-yellow-600"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-slate-900">{{ number_format($falseCount) }}</p>
                    <p class="text-xs text-slate-500">False Reports</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-2xl p-5 border border-slate-200">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-purple-100 flex items-center justify-center">
                    <i class="fas fa-ban text-purple-600"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-slate-900">{{ number_format($restrictedUsers) }}</p>
                    <p class="text-xs text-slate-500">Restricted Users</p>
                </div>
            </div>
        </div>
    </div>

    {{-- SOS Alerts Table --}}
    <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="font-semibold text-slate-800">All SOS Alerts</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="text-left px-6 py-3 font-semibold text-slate-600">ID</th>
                        <th class="text-left px-6 py-3 font-semibold text-slate-600">User</th>
                        <th class="text-left px-6 py-3 font-semibold text-slate-600">Type</th>
                        <th class="text-left px-6 py-3 font-semibold text-slate-600">Status</th>
                        <th class="text-left px-6 py-3 font-semibold text-slate-600">Strikes</th>
                        <th class="text-left px-6 py-3 font-semibold text-slate-600">Restricted Until</th>
                        <th class="text-left px-6 py-3 font-semibold text-slate-600">Started</th>
                        <th class="text-left px-6 py-3 font-semibold text-slate-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($alerts as $sos)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4 font-mono text-xs text-slate-500">#{{ $sos->id }}</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                @if($sos->user)
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center text-white text-xs font-bold">
                                        {{ strtoupper(substr($sos->user->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-slate-800">{{ $sos->user->name }}</p>
                                        <p class="text-xs text-slate-400">{{ $sos->user->phone ?? 'N/A' }}</p>
                                    </div>
                                @else
                                    <span class="text-slate-400">Deleted user</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-red-100 text-red-700">
                                {{ strtoupper($sos->emergency_type) }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @if($sos->status === 'active')
                                <span class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-red-100 text-red-700 flex items-center gap-1 w-fit">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                                    Active
                                </span>
                            @elseif($sos->status === 'resolved')
                                <span class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-green-100 text-green-700">Resolved</span>
                            @else
                                <span class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 text-slate-600">{{ ucfirst($sos->status) }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($sos->user && $sos->user->sos_false_count > 0)
                                <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-orange-100 text-orange-700">
                                    {{ $sos->user->sos_false_count }} / 3
                                </span>
                            @else
                                <span class="text-slate-400 text-xs">0</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($sos->user && $sos->user->sos_restricted_until && $sos->user->sos_restricted_until->isFuture())
                                <span class="text-xs text-red-600 font-medium">
                                    {{ $sos->user->sos_restricted_until->diffForHumans() }}
                                </span>
                            @else
                                <span class="text-slate-400 text-xs">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-xs text-slate-500">
                            {{ $sos->started_at?->diffForHumans() ?? 'N/A' }}
                        </td>
                        <td class="px-6 py-4">
                            @if($sos->user)
                            <div class="flex items-center gap-2">
                                @if($sos->user->sos_restricted_until && $sos->user->sos_restricted_until->isFuture())
                                    <form method="POST" action="{{ route('admin.sos.unrestrict', $sos->id) }}" class="inline" onsubmit="return confirm('Lift SOS restriction for this user?');">
                                        @csrf
                                        <button class="px-3 py-1.5 bg-emerald-50 text-emerald-700 rounded-lg text-xs font-semibold hover:bg-emerald-100 transition-colors">
                                            <i class="fas fa-unlock mr-1"></i>Unrestrict
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.sos.restrict', $sos->id) }}" class="inline" onsubmit="return confirm('Restrict this user from using SOS for 24 hours?');">
                                        @csrf
                                        <input type="hidden" name="hours" value="24">
                                        <button class="px-3 py-1.5 bg-red-50 text-red-700 rounded-lg text-xs font-semibold hover:bg-red-100 transition-colors">
                                            <i class="fas fa-ban mr-1"></i>Restrict 24h
                                        </button>
                                    </form>
                                @endif
                            </div>
                            @else
                                <span class="text-slate-400 text-xs">-</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center">
                                <i class="fas fa-shield-alt text-4xl text-slate-300 mb-3"></i>
                                <p class="text-slate-500 font-medium">No SOS alerts yet</p>
                                <p class="text-xs text-slate-400 mt-1">When users trigger SOS, it will appear here.</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($alerts->hasPages())
        <div class="px-6 py-4 border-t border-slate-100">{{ $alerts->links() }}</div>
        @endif
    </div>
</div>
@endsection
