@extends('admin.layout')

@section('title', 'Employee Salaries - Admin')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Employee Salaries</h1>
            <p class="text-sm text-gray-500 mt-1">Monthly salary tracking for all employees</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.expenses') }}" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                <i class="fas fa-arrow-left mr-1"></i> Expenses
            </a>
            <a href="{{ route('admin.financial-overview') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                <i class="fas fa-chart-line mr-1"></i> Overview
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
        <i class="fas fa-check-circle"></i>
        <span>{{ session('success') }}</span>
    </div>
    @endif

    {{-- Month/Year Selector --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <form action="{{ route('admin.salaries') }}" method="GET" class="flex items-center gap-4">
            <label class="text-sm font-medium text-gray-700">Period:</label>
            <select name="month" class="border border-gray-300 rounded-lg px-3 py-2">
                @for($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" {{ $m == $month ? 'selected' : '' }}>{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                @endfor
            </select>
            <select name="year" class="border border-gray-300 rounded-lg px-3 py-2">
                @for($y = date('Y') - 2; $y <= date('Y') + 1; $y++)
                    <option value="{{ $y }}" {{ $y == $year ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
            <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">Filter</button>
        </form>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-yellow-100 flex items-center justify-center">
                    <i class="fas fa-clock text-yellow-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Pending</p>
                    <p class="text-xl font-bold text-yellow-600">Rs. {{ number_format($totalPending, 0) }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Paid</p>
                    <p class="text-xl font-bold text-green-600">Rs. {{ number_format($totalPaid, 0) }}</p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                    <i class="fas fa-users text-blue-600"></i>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Total Employees</p>
                    <p class="text-xl font-bold text-gray-900">{{ $salaries->count() }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- Add Salary --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
        <div class="bg-purple-50 border-b border-purple-100 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900">
                <i class="fas fa-user-plus text-purple-500 mr-2"></i> Add Salary Record
            </h2>
        </div>
        <form action="{{ route('admin.salaries.store') }}" method="POST" class="p-6">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Employee Name *</label>
                    <input type="text" name="employee_name" required class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="Full name">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Position</label>
                    <input type="text" name="position" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="e.g. Developer">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                    <input type="text" name="department" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="e.g. Engineering">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Base Salary (Rs.) *</label>
                    <input type="number" name="base_salary" step="0.01" required class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="0.00">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bonus (Rs.)</label>
                    <input type="number" name="bonus" step="0.01" value="0" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Deductions (Rs.)</label>
                    <input type="number" name="deductions" step="0.01" value="0" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Period Start *</label>
                    <input type="date" name="period_start" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Period End *</label>
                    <input type="date" name="period_end" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2"></textarea>
                </div>
            </div>
            <div class="mt-4">
                <button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-medium">
                    <i class="fas fa-save mr-1"></i> Add Record
                </button>
            </div>
        </form>
    </div>

    {{-- Salary List --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="bg-gray-50 border-b border-gray-200 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900">
                <i class="fas fa-file-invoice text-gray-500 mr-2"></i> Salary Records — {{ date('F Y', mktime(0, 0, 0, $month, 1, $year)) }}
            </h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Position</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Base</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bonus</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Deductions</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Net Salary</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($salaries as $salary)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-900">{{ $salary->employee_name }}</div>
                            @if($salary->department)
                                <div class="text-sm text-gray-500">{{ $salary->department }}</div>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $salary->position ?? '-' }}</td>
                        <td class="px-6 py-4 text-gray-900">Rs. {{ number_format($salary->base_salary, 0) }}</td>
                        <td class="px-6 py-4 text-green-600">+Rs. {{ number_format($salary->bonus, 0) }}</td>
                        <td class="px-6 py-4 text-red-600">-Rs. {{ number_format($salary->deductions, 0) }}</td>
                        <td class="px-6 py-4 font-bold text-gray-900">Rs. {{ number_format($salary->net_salary, 0) }}</td>
                        <td class="px-6 py-4">
                            @if($salary->payment_status === 'paid')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    Paid {{ $salary->payment_date?->format('M d') }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Pending</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2">
                                <button onclick="openEditModal({{ $salary->id }}, '{{ addslashes($salary->employee_name) }}', '{{ addslashes($salary->position ?? '') }}', '{{ addslashes($salary->department ?? '') }}', {{ $salary->base_salary }}, {{ $salary->bonus }}, {{ $salary->deductions }}, '{{ $salary->period_start->format('Y-m-d') }}', '{{ $salary->period_end->format('Y-m-d') }}', '{{ addslashes($salary->notes ?? '') }}')" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    <i class="fas fa-edit"></i>
                                </button>
                                @if($salary->payment_status !== 'paid')
                                <form action="{{ route('admin.salaries.mark-paid', $salary) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="text-green-600 hover:text-green-800 text-sm font-medium">
                                        <i class="fas fa-check-circle"></i>
                                    </button>
                                </form>
                                @endif
                                <form action="{{ route('admin.salaries.delete', $salary) }}" method="POST" class="inline" onsubmit="return confirm('Delete this salary record?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-sm font-medium">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-users text-4xl text-gray-300 mb-3 block"></i>
                            No salary records for this month.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<div id="editModal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-6 border-b border-gray-200">
            <h3 class="text-xl font-bold text-gray-900">Edit Salary Record</h3>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
        </div>
        <form id="editForm" method="POST" class="p-6">
            @csrf @method('PUT')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Employee Name *</label>
                    <input type="text" name="employee_name" id="edit_employee_name" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Position</label>
                    <input type="text" name="position" id="edit_position" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                    <input type="text" name="department" id="edit_department" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Base Salary (Rs.) *</label>
                    <input type="number" name="base_salary" id="edit_base_salary" step="0.01" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bonus (Rs.)</label>
                    <input type="number" name="bonus" id="edit_bonus" step="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Deductions (Rs.)</label>
                    <input type="number" name="deductions" id="edit_deductions" step="0.01" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Period Start *</label>
                    <input type="date" name="period_start" id="edit_period_start" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Period End *</label>
                    <input type="date" name="period_end" id="edit_period_end" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" id="edit_notes" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 font-medium"><i class="fas fa-save mr-1"></i> Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(id, name, position, department, base, bonus, deductions, start, end, notes) {
    document.getElementById('editForm').action = '/' + window.adminPrefix + '/salaries/' + id;
    document.getElementById('edit_employee_name').value = name;
    document.getElementById('edit_position').value = position;
    document.getElementById('edit_department').value = department;
    document.getElementById('edit_base_salary').value = base;
    document.getElementById('edit_bonus').value = bonus;
    document.getElementById('edit_deductions').value = deductions;
    document.getElementById('edit_period_start').value = start;
    document.getElementById('edit_period_end').value = end;
    document.getElementById('edit_notes').value = notes;
    const modal = document.getElementById('editModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}
function closeEditModal() {
    const modal = document.getElementById('editModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
</script>
@endsection
