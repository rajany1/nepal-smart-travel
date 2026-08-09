@extends('admin.layout')

@section('title', "Moderation History - {$user->name}")

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <a href="{{ route('admin.moderation', ['tab' => 'users']) }}" class="text-sm text-primary-600 hover:underline"><i class="fas fa-arrow-left"></i> Back to Content Safety</a>
        <h2 class="text-2xl font-bold text-slate-800 mt-1">{{ $user->name }}</h2>
        <p class="text-sm text-slate-500">{{ $user->email }}</p>
    </div>
    <span class="text-xs px-3 py-1 rounded-full border {{ $user->status === 'active' ? 'bg-emerald-100 text-emerald-700 border-emerald-200' : ($user->status === 'suspended' ? 'bg-amber-100 text-amber-700 border-amber-200' : 'bg-red-100 text-red-700 border-red-200') }}">
        {{ ucfirst($user->status) }}
        @if($user->status === 'suspended' && $user->suspended_until)
            — until {{ $user->suspended_until->format('M j, Y g:i A') }}
        @endif
    </span>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-2xl shadow p-5">
        <div class="text-3xl font-bold text-slate-800">{{ $totalViolations }}</div>
        <div class="text-xs text-slate-500 mt-1">Total Violations</div>
    </div>
    @foreach(['warning' => 'Warnings', 'suspend' => 'Suspensions', 'block' => 'Blocks'] as $level => $label)
        <div class="bg-white rounded-2xl shadow p-5">
            <div class="text-3xl font-bold {{ $level === 'warning' ? 'text-amber-500' : ($level === 'suspend' ? 'text-orange-600' : 'text-red-600') }}">
                {{ $strikes->where('level', $level)->count() }}
            </div>
            <div class="text-xs text-slate-500 mt-1">{{ $label }}</div>
        </div>
    @endforeach
</div>

<div class="grid lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-2xl shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-800"><i class="fas fa-gavel text-red-500 mr-1"></i> Why this user was punished</h3>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($strikes as $strike)
                <div class="px-6 py-4">
                    <div class="flex items-center gap-2">
                        <span class="text-xs px-2.5 py-1 rounded-full border {{ $strike->level === 'warning' ? 'bg-amber-100 text-amber-700 border-amber-200' : ($strike->level === 'suspend' ? 'bg-orange-100 text-orange-700 border-orange-200' : 'bg-red-100 text-red-700 border-red-200') }}">
                            {{ ucfirst($strike->level) }}
                        </span>
                        <span class="text-xs text-slate-400">{{ $strike->created_at->format('M j, Y g:i A') }}</span>
                        <span class="text-xs text-slate-400 ml-auto">{{ $strike->issued_by ? 'By admin' : 'By system (AI agent)' }}</span>
                    </div>
                    <p class="text-sm text-slate-700 mt-2">{{ $strike->reason }}</p>
                    @if($strike->level === 'suspend' && $strike->expires_at)
                        <p class="text-xs text-amber-600 mt-1">Suspended until {{ $strike->expires_at->format('M j, Y g:i A') }}</p>
                    @endif
                </div>
            @empty
                <div class="px-6 py-10 text-center text-slate-400">No strikes recorded. This user is clean.</div>
            @endforelse
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-800"><i class="fas fa-file-lines text-red-500 mr-2"></i> What they posted</h3>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($violations as $v)
                <div class="px-6 py-4">
                    <div class="flex items-center gap-2 text-xs text-slate-500">
                        <span class="px-2 py-0.5 rounded bg-slate-100">{{ $v->entity_type }} #{{ $v->entity_id }} · {{ $v->field }}</span>
                        <span class="text-slate-400">{{ $v->created_at->format('M j, g:i A') }}</span>
                    </div>
                    <p class="text-sm text-slate-500 line-through decoration-red-400 mt-2">{{ \Illuminate\Support\Str::limit($v->original_text, 140) }}</p>
                    <p class="text-sm text-emerald-700 mt-1">{{ \Illuminate\Support\Str::limit($v->censored_text, 140) }}</p>
                    @if($v->found_words)
                        <div class="text-[10px] text-red-500 mt-1">words: {{ implode(', ', $v->found_words) }}</div>
                    @endif
                </div>
            @empty
                <div class="px-6 py-10 text-center text-slate-400">No violations on record.</div>
            @endforelse
            <div class="px-6 py-4 border-t border-slate-100">{{ $violations->links() }}</div>
        </div>
    </div>
</div>

<div class="mt-6 flex flex-wrap gap-3">
    @if($user->status !== 'banned')
        <button type="button" onclick="openStrike('{{ $user->id }}', 'suspend')" class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold rounded-xl px-4 py-2.5">
            <i class="fas fa-pause"></i> Suspend
        </button>
    @endif
    @if($user->status !== 'banned' && $user->status !== 'suspended')
        <button type="button" onclick="openStrike('{{ $user->id }}', 'block')" class="inline-flex items-center gap-2 bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-xl px-4 py-2.5">
            <i class="fas fa-ban"></i> Block
        </button>
    @endif
    @if($user->status !== 'active')
        <form method="POST" action="{{ route('admin.moderation.activate', $user) }}">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-xl px-4 py-2.5">
                <i class="fas fa-undo"></i> Activate
            </button>
        </form>
    @endif
</div>

<!-- Strike modal -->
<div id="strikeModal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
        <h3 class="text-lg font-bold text-slate-800 mb-1">{{ $user->name }}</h3>
        <form method="POST" action="{{ route('admin.moderation.strike', $user) }}" id="strikeForm">
            @csrf
            <input type="hidden" name="level" id="strikeLevel">
            <label class="block text-sm font-medium text-slate-700 mb-1">Reason (kept on record)</label>
            <textarea name="reason" rows="3" required
                      class="w-full rounded-xl border border-slate-300 px-4 py-2.5 focus:ring-2 focus:ring-red-500 outline-none"
                      placeholder="e.g. Repeatedly posting abusive language despite warnings"></textarea>
            <div class="flex justify-end gap-3 mt-4">
                <button type="button" onclick="closeStrike()" class="px-4 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white text-sm font-semibold rounded-lg px-5 py-2" id="strikeSubmit">Apply</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openStrike(userId, level) {
        document.getElementById('strikeLevel').value = level;
        document.getElementById('strikeSubmit').textContent = level === 'suspend' ? 'Suspend User' : 'Block User';
        document.getElementById('strikeModal').classList.remove('hidden');
    }
    function closeStrike() {
        document.getElementById('strikeModal').classList.add('hidden');
    }
</script>
@endsection