@extends('admin.layout')
@section('title', 'User Subscriptions')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div><h3 class="text-2xl font-bold text-slate-900">User Subscriptions</h3><p class="text-sm text-slate-500 mt-1">Assign and manage premium subscriptions for users.</p></div>
        <div class="flex gap-2">
            <a href="{{ route('admin.subscription.users', ['filter' => 'active']) }}" class="px-4 py-2 rounded-lg text-sm font-medium transition {{ ($filter ?? 'active') === 'active' ? 'bg-primary-100 text-primary-700' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">Active</a>
            <a href="{{ route('admin.subscription.users', ['filter' => 'cancelled']) }}" class="px-4 py-2 rounded-lg text-sm font-medium transition {{ ($filter ?? '') === 'cancelled' ? 'bg-primary-100 text-primary-700' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">Cancelled</a>
            <a href="{{ route('admin.subscription.users', ['filter' => 'all']) }}" class="px-4 py-2 rounded-lg text-sm font-medium transition {{ ($filter ?? '') === 'all' ? 'bg-primary-100 text-primary-700' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">All</a>
            <button onclick="document.getElementById('assignModal').classList.remove('hidden')" class="bg-primary-600 hover:bg-primary-700 text-white px-5 py-2 rounded-xl text-sm font-semibold shadow transition flex items-center gap-2"><i class="fas fa-plus"></i> Assign</button>
        </div>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50">
                    <tr><th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">User</th><th class="px-6 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Plan</th><th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th><th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Starts</th><th class="px-6 py-3 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Ends</th><th class="px-6 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($subscriptions as $sub)
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4"><p class="font-semibold text-sm">{{ $sub->user->name }}</p><p class="text-xs text-slate-400">{{ $sub->user->email }}</p></td>
                        <td class="px-6 py-4"><span class="font-medium">{{ $sub->plan->name }}</span><br><span class="text-xs text-slate-400">Rs. {{ number_format($sub->plan->price) }}/{{ $sub->plan->billing_interval }}</span></td>
                        <td class="px-6 py-4 text-center">
                            @switch($sub->status)
                                @case('active')<span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Active</span>@break
                                @case('trialing')<span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Trialing</span>@break
                                @case('cancelled')<span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full">Cancelled</span>@break
                                @case('expired')<span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full">Expired</span>@break
                            @endswitch
                        </td>
                        <td class="px-6 py-4 text-center text-sm">{{ $sub->starts_at?->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-6 py-4 text-center text-sm">{{ $sub->ends_at?->format('Y-m-d') ?? 'Unlimited' }}</td>
                        <td class="px-6 py-4 text-right">
                            @if(($sub->status === 'active' || $sub->status === 'trialing') && $sub->plan?->slug !== 'free')
                            <form method="POST" action="{{ route('admin.subscription.users.cancel', $sub) }}" onsubmit="return confirm('Cancel this subscription?')" class="inline">
                                @csrf
                                <button class="px-3 py-1.5 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100"><i class="fas fa-ban"></i> Cancel</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-6 py-12 text-center text-slate-400"><i class="fas fa-crown text-3xl mb-3"></i><p class="text-sm">No subscriptions yet.</p></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $subscriptions->links() }}</div>
</div>

<div id="assignModal" class="hidden fixed inset-0 z-50 bg-black/40 grid place-items-center" onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
        <div class="flex items-center justify-between mb-4"><h4 class="text-lg font-bold">Assign Subscription</h4><button onclick="document.getElementById('assignModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600"><i class="fas fa-times"></i></button></div>
        <form method="POST" action="{{ route('admin.subscription.users.assign') }}" class="space-y-4">
            @csrf
            <div><label class="block text-xs font-semibold text-slate-600 mb-1">User ID or Email</label>
                <select name="user_id" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                    @foreach(\App\Models\User::orderBy('name')->get() as $u)
                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                    @endforeach
                </select>
            </div>
            <div><label class="block text-xs font-semibold text-slate-600 mb-1">Plan</label>
                <select name="subscription_plan_id" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                    @foreach($plans as $p)
                    <option value="{{ $p->id }}">{{ $p->name }} — Rs. {{ number_format($p->price) }}/{{ $p->billing_interval }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                    <select name="status" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm">
                        <option value="active">Active</option>
                        <option value="trialing">Trial (7 days)</option>
                    </select>
                </div>
                <div><label class="block text-xs font-semibold text-slate-600 mb-1">Duration (months)</label><input type="number" name="duration_months" min="1" value="1" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm"></div>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="document.getElementById('assignModal').classList.add('hidden')" class="px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold bg-primary-600 text-white rounded-lg hover:bg-primary-700">Assign</button>
            </div>
        </form>
    </div>
</div>
@endsection
