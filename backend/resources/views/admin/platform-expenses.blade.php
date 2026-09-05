@extends('admin.layout')

@section('title', 'Platform Expenses - Admin')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Platform Expenses</h1>
            <p class="text-sm text-gray-500 mt-1">Track hosting, server, API costs and all operating expenses</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.financial-overview') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                <i class="fas fa-chart-line mr-1"></i> Financial Overview
            </a>
            <a href="{{ route('admin.salaries') }}" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                <i class="fas fa-users mr-1"></i> Salaries
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
        <i class="fas fa-check-circle"></i>
        <span>{{ session('success') }}</span>
    </div>
    @endif
    @if(session('error'))
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
        <i class="fas fa-exclamation-circle"></i>
        <span>{{ session('error') }}</span>
    </div>
    @endif

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                    <i class="fas fa-dollar-sign text-green-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Monthly Total</p>
                    <p class="text-xl font-bold text-gray-900">Rs. {{ number_format($totalMonthly, 0) }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-red-100 flex items-center justify-center">
                    <i class="fas fa-exclamation-circle text-red-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Expired</p>
                    <p class="text-xl font-bold {{ $expired > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $expired }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-yellow-100 flex items-center justify-center">
                    <i class="fas fa-clock text-yellow-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Renewal in 7 Days</p>
                    <p class="text-xl font-bold {{ $renewalSoon > 0 ? 'text-yellow-600' : 'text-gray-900' }}">{{ $renewalSoon }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                    <i class="fas fa-list text-blue-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Active Services</p>
                    <p class="text-xl font-bold text-gray-900">{{ $expenses->total() }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.expenses', ['filter' => 'all']) }}" class="px-4 py-2 rounded-lg text-sm font-medium {{ $filter === 'all' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">All</a>
            <a href="{{ route('admin.expenses', ['filter' => 'active']) }}" class="px-4 py-2 rounded-lg text-sm font-medium {{ $filter === 'active' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Active</a>
            <a href="{{ route('admin.expenses', ['filter' => 'renewal']) }}" class="px-4 py-2 rounded-lg text-sm font-medium {{ $filter === 'renewal' ? 'bg-yellow-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Renewal Soon</a>
            <a href="{{ route('admin.expenses', ['filter' => 'expired']) }}" class="px-4 py-2 rounded-lg text-sm font-medium {{ $filter === 'expired' ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Expired</a>
            <a href="{{ route('admin.expenses', ['filter' => 'inactive']) }}" class="px-4 py-2 rounded-lg text-sm font-medium {{ $filter === 'inactive' ? 'bg-gray-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Inactive</a>
        </div>
    </div>

    {{-- Add New Expense --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="bg-primary-50 border-b border-primary-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900">
                <i class="fas fa-plus-circle text-primary-500 mr-2"></i> Add New Expense
            </h2>
        </div>
        <form action="{{ route('admin.expenses.store') }}" method="POST" class="p-6">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name *</label>
                    <input type="text" name="name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="e.g. AWS Hosting">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                    <select name="category" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        <option value="">Select Category</option>
                        @foreach(\App\Models\PlatformExpense::CATEGORIES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Provider</label>
                    <input type="text" name="provider" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="e.g. AWS, Cloudflare">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount (Rs.) *</label>
                    <input type="number" name="amount" step="0.01" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="0.00">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Billing Cycle *</label>
                    <select name="billing_cycle" required class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        @foreach(\App\Models\PlatformExpense::BILLING_CYCLES as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Next Renewal Date</label>
                    <input type="date" name="next_renewal_date" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Alert Days Before</label>
                    <input type="number" name="alert_days_before" value="7" min="1" max="90" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="Additional notes..."></textarea>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 font-medium">
                    <i class="fas fa-save mr-1"></i> Add Expense
                </button>
            </div>
        </form>
    </div>

    {{-- Expenses List --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="bg-gray-50 border-b border-gray-200 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900">
                <i class="fas fa-file-invoice-dollar text-gray-500 mr-2"></i> All Expenses
            </h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cycle</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Monthly</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Next Renewal</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($expenses as $expense)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-900">{{ $expense->name }}</div>
                            @if($expense->provider)
                                <div class="text-sm text-gray-500">{{ $expense->provider }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                {{ \App\Models\PlatformExpense::CATEGORIES[$expense->category] ?? $expense->category }}
                            </span>
                        </td>
                        <td class="px-6 py-4 font-medium text-gray-900">Rs. {{ number_format($expense->amount, 0) }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ \App\Models\PlatformExpense::BILLING_CYCLES[$expense->billing_cycle] ?? $expense->billing_cycle }}</td>
                        <td class="px-6 py-4 font-medium text-gray-900">Rs. {{ number_format($expense->monthly_equivalent, 0) }}</td>
                        <td class="px-6 py-4 text-sm">
                            @if($expense->next_renewal_date)
                                @if($expense->is_expired)
                                    <span class="text-red-600 font-medium">{{ $expense->next_renewal_date->format('M d, Y') }}</span>
                                    <span class="ml-1 text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded-full">EXPIRED</span>
                                @elseif($expense->is_renewal_soon)
                                    <span class="text-yellow-600 font-medium">{{ $expense->next_renewal_date->format('M d, Y') }}</span>
                                    <span class="ml-1 text-xs bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full">{{ max(0, (int) $expense->next_renewal_date->diffInDays(now())) }}d left</span>
                                @else
                                    <span class="text-gray-700">{{ $expense->next_renewal_date->format('M d, Y') }}</span>
                                @endif
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($expense->status === 'active')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Active</span>
                            @elseif($expense->status === 'inactive')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">Inactive</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">{{ ucfirst($expense->status) }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                @if($expense->status === 'active')
                                <form action="{{ route('admin.expenses.mark-paid', $expense) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-green-600 hover:text-green-800 text-sm" title="Mark as Paid">
                                        <i class="fas fa-check-circle"></i>
                                    </button>
                                </form>
                                @endif
                                <button onclick="openEditModal({{ $expense->id }}, @js($expense->name), @js($expense->category), @js($expense->provider ?? ''), {{ $expense->amount }}, @js($expense->billing_cycle), @js($expense->next_renewal_date?->format('Y-m-d') ?? ''), @js($expense->notes ?? ''), @js($expense->status), {{ $expense->alert_days_before }})" class="text-blue-600 hover:text-blue-800 text-sm" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form action="{{ route('admin.expenses.destroy', $expense) }}" method="POST" class="inline" onsubmit="return confirm('Delete this expense?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:text-red-800 text-sm" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-inbox text-4xl text-gray-300 mb-3 block"></i>
                            No expenses found. Add your first expense above.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($expenses->hasPages())
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $expenses->withQueryString()->links() }}
        </div>
        @endif
    </div>
</div>

{{-- Edit Modal --}}
<div id="editModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center">
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full mx-4 p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-semibold">Edit Expense</h3>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
        </div>
        <form id="editForm" method="POST">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-2 gap-4">
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Name</label><input type="text" id="edit_name" name="name" required class="w-full border border-gray-300 rounded-lg px-3 py-2"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Category</label><select id="edit_category" name="category" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    @foreach(\App\Models\PlatformExpense::CATEGORIES as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Provider</label><input type="text" id="edit_provider" name="provider" class="w-full border border-gray-300 rounded-lg px-3 py-2"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Amount</label><input type="number" id="edit_amount" name="amount" step="0.01" required class="w-full border border-gray-300 rounded-lg px-3 py-2"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Billing Cycle</label><select id="edit_billing_cycle" name="billing_cycle" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    @foreach(\App\Models\PlatformExpense::BILLING_CYCLES as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Next Renewal</label><input type="date" id="edit_next_renewal_date" name="next_renewal_date" class="w-full border border-gray-300 rounded-lg px-3 py-2"></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Status</label><select id="edit_status" name="status" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                    <option value="active">Active</option><option value="inactive">Inactive</option><option value="cancelled">Cancelled</option>
                </select></div>
                <div><label class="block text-sm font-medium text-gray-700 mb-1">Alert Days</label><input type="number" id="edit_alert_days_before" name="alert_days_before" min="1" max="90" class="w-full border border-gray-300 rounded-lg px-3 py-2"></div>
                <div class="col-span-2"><label class="block text-sm font-medium text-gray-700 mb-1">Notes</label><textarea id="edit_notes" name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2"></textarea></div>
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script>
function openEditModal(id, name, category, provider, amount, cycle, renewal, notes, status, alertDays) {
    document.getElementById('editForm').action = '/' + window.adminPrefix + '/expenses/' + id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_category').value = category;
    document.getElementById('edit_provider').value = provider;
    document.getElementById('edit_amount').value = amount;
    document.getElementById('edit_billing_cycle').value = cycle;
    document.getElementById('edit_next_renewal_date').value = renewal;
    document.getElementById('edit_notes').value = notes;
    document.getElementById('edit_status').value = status;
    document.getElementById('edit_alert_days_before').value = alertDays;
    var modal = document.getElementById('editModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}
function closeEditModal() {
    var modal = document.getElementById('editModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
</script>
@endsection
