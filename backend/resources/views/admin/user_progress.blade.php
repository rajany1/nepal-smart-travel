@extends('admin.layout')
@section('title', 'Progress: ' . $user->name)

@section('content')
<div class="space-y-6">
    <!-- User Header -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-full bg-indigo-600 grid place-items-center text-white text-xl font-bold shadow">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <div>
                <h3 class="text-xl font-bold text-slate-900">{{ $user->name }}</h3>
                <p class="text-sm text-slate-500">{{ $user->email }} · {{ $user->roleName ?? 'user' }}</p>
                <p class="text-xs text-slate-400">Joined {{ $user->created_at?->format('M d, Y') }}</p>
            </div>
            <a href="{{ route('admin.users') }}" class="ml-auto text-sm text-indigo-600 hover:text-indigo-800">
                <i class="fas fa-arrow-left"></i> Back to Users
            </a>
        </div>
    </div>

    <!-- XP & Level Overview -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total XP</p>
            <p class="text-3xl font-bold text-indigo-600 mt-1">{{ number_format($user->total_xp ?? 0) }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Current Level</p>
            <p class="text-3xl font-bold text-slate-900 mt-1">{{ $user->current_level ?? 1 }}</p>
            <p class="text-sm text-slate-500">{{ app(\App\Services\AchievementService::class)->getLevelName($user->current_level ?? 1) }}</p>
        </div>
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-5">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Level Progress</p>
            <div class="mt-2">
                <div class="w-full bg-slate-200 rounded-full h-2.5">
                    <div class="bg-indigo-600 h-2.5 rounded-full" style="width: {{ $levelProgress * 100 }}%"></div>
                </div>
                <p class="text-xs text-slate-500 mt-1">{{ number_format($user->total_xp ?? 0) }} / {{ $nextLevelXp }} XP</p>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center">
            <p class="text-2xl font-bold text-slate-900">{{ $stats['total_reports'] }}</p>
            <p class="text-xs text-slate-500">Reports</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center">
            <p class="text-2xl font-bold text-green-600">{{ $stats['approved_reports'] }}</p>
            <p class="text-xs text-slate-500">Approved</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center">
            <p class="text-2xl font-bold text-slate-900">{{ $stats['total_alerts'] }}</p>
            <p class="text-xs text-slate-500">Alerts</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 text-center">
            <p class="text-2xl font-bold text-slate-900">{{ $stats['total_comments'] }}</p>
            <p class="text-xs text-slate-500">Comments</p>
        </div>
    </div>

    <!-- Achievements / Badges -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h4 class="font-semibold text-slate-900">Badges & Achievements</h4>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                @foreach($achievements as $badge)
                <div class="relative border rounded-xl p-4 text-center transition {{ $badge['unlocked'] ? ($badge['is_suspicious'] ? 'border-amber-300 bg-amber-50' : 'border-green-200 bg-green-50') : 'border-slate-200 bg-slate-50 opacity-60' }}">
                    @if($badge['is_suspicious'])
                    <div class="absolute top-2 right-2 group">
                        <i class="fas fa-exclamation-triangle text-amber-500 text-sm cursor-help"></i>
                        <div class="absolute right-0 top-6 w-48 bg-amber-100 text-amber-800 text-xs rounded-lg p-2 shadow hidden group-hover:block z-10">
                            {{ $badge['suspicious_reason'] ?? 'Flagged as suspicious' }}
                        </div>
                    </div>
                    @endif
                    <i class="fas fa-{{ $badge['icon'] }} text-2xl {{ $badge['unlocked'] ? 'text-indigo-600' : 'text-slate-400' }}"></i>
                    <p class="text-sm font-semibold text-slate-900 mt-2">{{ $badge['name'] }}</p>
                    <p class="text-xs text-slate-500 mt-0.5">{{ $badge['description'] }}</p>
                    <p class="text-xs mt-2">
                        @if($badge['unlocked'])
                        <span class="text-green-600 font-medium">
                            <i class="fas fa-check-circle"></i> Unlocked
                        </span>
                        @else
                        <span class="text-slate-400"><i class="fas fa-lock"></i> Locked</span>
                        @endif
                    </p>
                    @if($badge['unlocked'] && $badge['is_suspicious'])
                    <form method="POST" action="{{ route('admin.user-achievements.clear', \App\Models\UserAchievement::where('user_id', $user->id)->whereHas('achievement', fn($q) => $q->where('name', $badge['id']))->first()?->id) }}" class="mt-2" onsubmit="return confirm('Clear suspicious flag on &quot;{{ $badge['name'] }}&quot;?')">
                        @csrf
                        <button type="submit" class="text-xs bg-amber-100 text-amber-700 px-2 py-1 rounded-lg hover:bg-amber-200">
                            <i class="fas fa-check"></i> Clear Suspicious
                        </button>
                    </form>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- XP History -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
            <h4 class="font-semibold text-slate-900">XP History</h4>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Action</th>
                        <th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase">Description</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($xpHistory as $txn)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-3 text-sm text-slate-500">{{ $txn->created_at->format('M d, H:i') }}</td>
                        <td class="px-6 py-3">
                            <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full">{{ $txn->action_type }}</span>
                        </td>
                        <td class="px-6 py-3 text-sm text-slate-700">{{ $txn->description ?? '—' }}</td>
                        <td class="px-6 py-3 text-right font-semibold {{ $txn->amount >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $txn->amount >= 0 ? '+' : '' }}{{ $txn->amount }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-slate-400">No XP transactions yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($xpHistory->hasPages())
        <div class="px-6 py-4 border-t border-slate-100">{{ $xpHistory->links() }}</div>
        @endif
    </div>

    <!-- Manual XP Adjust -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        <h4 class="font-semibold text-slate-900 mb-4">Manual XP Adjustment</h4>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <form method="POST" action="{{ route('admin.users.adjust-xp', $user) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Amount (positive = add, negative = deduct)</label>
                    <input type="number" name="amount" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm" placeholder="e.g. 50 or -50">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Reason</label>
                    <input type="text" name="reason" required class="w-full border border-slate-300 rounded-xl px-3 py-2 text-sm" placeholder="Why this adjustment?">
                </div>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-xl text-sm font-semibold">
                    <i class="fas fa-coins"></i> Apply XP Adjustment
                </button>
            </form>
            <form method="POST" action="{{ route('admin.users.recalculate-level', $user) }}" class="flex flex-col justify-center items-center border border-dashed border-slate-300 rounded-xl p-6">
                @csrf
                <p class="text-sm text-slate-600 mb-3 text-center">Recalculate user's level based on current total XP</p>
                <button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white px-5 py-2 rounded-xl text-sm font-semibold">
                    <i class="fas fa-sync"></i> Recalculate Level
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
