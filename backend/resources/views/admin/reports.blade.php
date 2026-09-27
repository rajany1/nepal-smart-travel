@extends('admin.layout')
@section('title', 'Reports Management')

@section('content')
@isset($queueCounts)
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
        <p class="text-xs uppercase tracking-wider text-amber-600 font-semibold">Queue Pending</p>
        <p class="mt-2 text-2xl font-bold text-amber-700">{{ $queueCounts['pending'] }}</p>
    </div>
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
        <p class="text-xs uppercase tracking-wider text-emerald-600 font-semibold">Queue Approved</p>
        <p class="mt-2 text-2xl font-bold text-emerald-700">{{ $queueCounts['approved'] }}</p>
    </div>
    <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4">
        <p class="text-xs uppercase tracking-wider text-rose-600 font-semibold">Queue Rejected</p>
        <p class="mt-2 text-2xl font-bold text-rose-700">{{ $queueCounts['rejected'] }}</p>
    </div>
</div>
@endisset
<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-100">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <h3 class="font-semibold text-gray-800">All Reports</h3>
                <span class="text-xs text-gray-400" id="lastUpdated">Updated just now</span>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.reports', ['status' => 'all']) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $status === 'all' ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">All</a>
                <a href="{{ route('admin.reports', ['status' => 'pending']) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $status === 'pending' ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Pending</a>
                <a href="{{ route('admin.reports', ['status' => 'approved']) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $status === 'approved' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Approved</a>
                <a href="{{ route('admin.reports', ['status' => 'rejected']) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $status === 'rejected' ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Rejected</a>
            </div>
        </div>
        {{-- Advanced filters --}}
        <form method="GET" action="{{ route('admin.reports') }}" class="mt-4 flex flex-wrap items-end gap-3" id="reportFilters">
            <input type="hidden" name="status" value="{{ $status }}">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Priority</label>
                <select name="priority" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 bg-white">
                    <option value="">All priorities</option>
                    <option value="critical" {{ request('priority') === 'critical' ? 'selected' : '' }}>Critical</option>
                    <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>High</option>
                    <option value="medium" {{ request('priority') === 'medium' ? 'selected' : '' }}>Medium</option>
                    <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Low</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Category</label>
                <select name="category_id" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 bg-white">
                    <option value="">All categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->id }}" {{ request('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">GPS Status</label>
                <select name="gps" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 bg-white">
                    <option value="">All</option>
                    <option value="verified" {{ request('gps') === 'verified' ? 'selected' : '' }}>Verified</option>
                    <option value="mismatched" {{ request('gps') === 'mismatched' ? 'selected' : '' }}>Mismatch</option>
                    <option value="no_gps_data" {{ request('gps') === 'no_gps_data' ? 'selected' : '' }}>No GPS</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Date Range</label>
                <select name="date_range" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 bg-white">
                    <option value="">All time</option>
                    <option value="today" {{ request('date_range') === 'today' ? 'selected' : '' }}>Today</option>
                    <option value="7days" {{ request('date_range') === '7days' ? 'selected' : '' }}>Last 7 days</option>
                    <option value="30days" {{ request('date_range') === '30days' ? 'selected' : '' }}>Last 30 days</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Title or reporter..." class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 bg-white w-48">
            </div>
            <button type="submit" class="px-4 py-1.5 text-sm font-medium bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition">
                <i class="fas fa-filter mr-1"></i>Filter
            </button>
            @if(request()->hasAny(['priority', 'category_id', 'gps', 'date_range', 'search']))
            <a href="{{ route('admin.reports', ['status' => $status]) }}" class="px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700 transition">
                <i class="fas fa-times mr-1"></i>Clear
            </a>
            @endif
        </form>
    </div>
    <div class="overflow-x-auto">
        <form id="reportsBulkForm" method="POST" action="">
            @csrf
        </form>
<div id="liveTable">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase w-10">
                        <input type="checkbox" id="selectAllReports" onchange="toggleAllReports(this)" class="rounded border-gray-300" title="Select all reports on this page" aria-label="Select all reports">
                    </th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">ID</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Title</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Reporter</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Priority</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">GPS Verify</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Stored confidence</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">AI Message</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($reports as $report)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-4">
                        <input type="checkbox" name="ids[]" value="{{ $report->id }}" form="reportsBulkForm" class="report-checkbox rounded border-gray-300" onchange="updateBulkBar()" aria-label="Select report #{{ $report->id }}">
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        <span class="cursor-pointer hover:text-gray-700" onclick="navigator.clipboard.writeText('#{{ $report->id }}').then(function(){var t=document.createElement('div');t.style.cssText='position:fixed;top:20px;right:20px;z-index:9999;background:#00695C;color:#fff;padding:8px 14px;border-radius:9999px;font-size:12px;box-shadow:0 4px 12px rgba(0,0,0,.2);';t.textContent='ID copied';document.body.appendChild(t);setTimeout(function(){t.remove()},1500)})" title="Click to copy ID">#{{ $report->id }} <i class="fas fa-copy text-[10px] text-gray-400"></i></span>
                    </td>
                    <td class="px-6 py-4">
                        <p class="text-sm font-medium text-gray-900 max-w-[200px] truncate">{{ $report->title }}</p>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $report->user?->name ?? 'Anonymous' }}</td>
                    <td class="px-6 py-4"><span class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded">{{ $report->category?->name ?? 'N/A' }}</span></td>
                    <td class="px-6 py-4">
                        <span class="text-xs font-medium px-2 py-1 rounded 
                            {{ $report->priority === 'critical' ? 'bg-red-100 text-red-800' : '' }}
                            {{ $report->priority === 'high' ? 'bg-orange-100 text-orange-800' : '' }}
                            {{ $report->priority === 'medium' ? 'bg-yellow-100 text-yellow-800' : '' }}
                            {{ $report->priority === 'low' ? 'bg-gray-100 text-gray-800' : '' }}">
                            {{ ucfirst($report->priority) }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        @php
                            $gpsStatus = $report->gps_verification_status ?? 'none';
                            $gpsLabel = $gpsStatus === 'verified' ? 'Verified' : ($gpsStatus === 'mismatched' ? 'Mismatch' : ($gpsStatus === 'no_gps_data' ? 'No GPS' : 'N/A'));
                            $gpsColor = $gpsStatus === 'verified' ? 'bg-green-100 text-green-800' : ($gpsStatus === 'mismatched' ? 'bg-red-100 text-red-800' : ($gpsStatus === 'no_gps_data' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-500'));
                            $gpsIcon = $gpsStatus === 'verified' ? 'fa-check-circle' : ($gpsStatus === 'mismatched' ? 'fa-exclamation-triangle' : ($gpsStatus === 'no_gps_data' ? 'fa-question-circle' : 'fa-minus-circle'));
                        @endphp
                        <span class="text-xs font-medium px-2 py-1 rounded whitespace-nowrap {{ $gpsColor }}" title="{{ $gpsStatus === 'verified' ? 'GPS matched ('.$report->gps_distance_km.'km)' : ($gpsStatus === 'mismatched' ? 'GPS distance: '.$report->gps_distance_km.'km' : ($gpsStatus === 'no_gps_data' ? 'Photo had no GPS EXIF data' : 'No photo uploaded or not verified')) }}">
                            <i class="fas {{ $gpsIcon }} mr-1"></i>{{ $gpsLabel }}
                            @if($report->is_live_capture)
                                <i class="fas fa-camera ml-1" title="In-app camera capture"></i>
                            @endif
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="text-xs font-medium px-2 py-1 rounded-full 
                            {{ $report->status === 'approved' ? 'bg-green-100 text-green-800' : '' }}
                            {{ $report->status === 'pending' ? 'bg-amber-100 text-amber-800' : '' }}
                            {{ $report->status === 'rejected' ? 'bg-red-100 text-red-800' : '' }}">
                            {{ ucfirst($report->status) }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        @if($report->authenticity_score !== null)
                        @php
                            $trust = (float) $report->authenticity_score;
                            $trustColor = $trust >= 0.75 ? 'bg-green-100 text-green-800' : ($trust >= 0.5 ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800');
                        @endphp
                        <span class="text-xs font-medium px-2 py-1 rounded {{ $trustColor }}" title="Combined stored confidence across text (35%), location (25%) and image (40%) checks — not a Vision AI image score (0-100%)">
                            {{ round($trust * 100) }}%
                        </span>
                        @else
                        <span class="text-xs text-gray-400">â€”</span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        @if($report->moderation_message)
                        <p class="text-xs text-gray-600 max-w-[260px] truncate" title="{{ $report->moderation_message }}">
                            {{ $report->moderation_message }}
                        </p>
                        @else
                        <p class="text-xs text-gray-400">â€”</p>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $report->created_at->format('M d, Y') }}</td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            @if($report->status === 'pending')
                            <form method="POST" action="{{ route('admin.reports.approve', $report->id) }}" class="inline" onsubmit="return confirm('Approve this report?\n\nThis will publish an alert and award XP to the reporter.')">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 text-xs font-medium bg-green-100 text-green-700 rounded-lg hover:bg-green-200 transition">
                                    <i class="fas fa-check mr-1"></i>Approve
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.reports.reject', $report->id) }}" class="inline" onsubmit="return confirm('Reject this report?');">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 text-xs font-medium bg-red-100 text-red-700 rounded-lg hover:bg-red-200 transition">
                                    <i class="fas fa-times mr-1"></i>Reject
                                </button>
                            </form>
                            @endif
                            <form method="POST" action="{{ route('admin.reports.delete', $report->id) }}" class="inline" onsubmit="return confirm('Delete this report?');">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 text-xs font-medium bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200 transition">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            <a href="{{ route('admin.reports.view', array_merge(['id' => $report->id], $listParams ?? [])) }}" class="px-3 py-1.5 text-xs font-medium bg-primary-100 text-primary-700 rounded-lg hover:bg-primary-200 transition">
                                <i class="fas fa-eye mr-1"></i>View
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="12" class="px-6 py-12 text-center text-gray-500">
                        <div class="flex flex-col items-center">
                            <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-100 mb-3"><i class="fas fa-inbox text-2xl text-gray-300"></i></span>
                            @if($status === 'pending')
                                <p class="font-medium text-gray-700">No pending reports</p>
                                <p class="text-sm text-gray-400 mt-1">All reports have been reviewed. You're all caught up.</p>
                            @else
                                <p class="font-medium text-gray-700">No reports found</p>
                                <p class="text-sm text-gray-400 mt-1">Try adjusting your filters or search criteria.</p>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <!-- Bulk Action Bar -->
    <div id="bulkBar" class="hidden px-6 py-3 bg-red-50 border-t border-red-100 flex items-center justify-between">
        <span class="text-sm text-red-700"><span id="selectedCount">0</span> selected</span>
        <div class="flex gap-2">
            <button type="button" onclick="openBulkDeleteModal()" class="px-3 py-1.5 text-xs font-medium bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                <i class="fas fa-trash mr-1"></i>Delete Selected (<span id="selectedCountBtn">0</span>)
            </button>
        </div>
    </div>
    <!-- Bulk delete confirmation -->
    <div id="bulkDeleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="bulkDeleteModalTitle">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeBulkDeleteModal()"></div>
            <div class="relative inline-block bg-white rounded-xl shadow-2xl text-left overflow-hidden transform transition-all sm:max-w-md w-full">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 id="bulkDeleteModalTitle" class="font-semibold text-gray-800"><i class="fas fa-exclamation-triangle text-red-500 mr-2"></i>Delete Reports</h3>
                    <button type="button" onclick="closeBulkDeleteModal()" class="text-gray-400 hover:text-gray-600" aria-label="Cancel"><i class="fas fa-times"></i></button>
                </div>
                <div class="p-6">
                    <p class="text-sm text-gray-600 mb-5">Are you sure you want to delete <span id="modalSelectedCount" class="font-semibold text-gray-900">0</span> selected reports? This cannot be undone.</p>
                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="closeBulkDeleteModal()" class="px-4 py-2 text-sm font-medium bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                        <button type="button" onclick="confirmBulkDelete()" class="px-4 py-2 text-sm font-medium bg-red-600 text-white rounded-lg hover:bg-red-700 transition"><i class="fas fa-trash mr-1"></i>Delete</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @if($reports->hasPages())
    <div class="px-6 py-4 border-t border-gray-100">
        {{ $reports->links() }}
</div>
    </div>
    @endif
</div>

<script>
function selectedReportIds() {
    return Array.from(document.querySelectorAll('.report-checkbox:checked')).map(cb => cb.value);
}

function toggleAllReports(source) {
    document.querySelectorAll('.report-checkbox').forEach(cb => cb.checked = source.checked);
    updateBulkBar();
}

function updateBulkBar() {
    const count = document.querySelectorAll('.report-checkbox:checked').length;
    const bar = document.getElementById('bulkBar');
    document.getElementById('selectedCount').textContent = count;
    document.getElementById('selectedCountBtn').textContent = count;
    if (count > 0) {
        bar.classList.remove('hidden');
    } else {
        bar.classList.add('hidden');
    }
}

function openBulkDeleteModal() {
    const count = selectedReportIds().length;
    if (count === 0) return;
    document.getElementById('modalSelectedCount').textContent = count;
    document.getElementById('bulkDeleteModal').classList.remove('hidden');
}

function closeBulkDeleteModal() {
    document.getElementById('bulkDeleteModal').classList.add('hidden');
}

function confirmBulkDelete() {
    const ids = selectedReportIds();
    if (ids.length === 0) {
        closeBulkDeleteModal();
        return;
    }
    const form = document.getElementById('reportsBulkForm');
    form.action = '{{ route('admin.reports.bulk-delete') }}';
    closeBulkDeleteModal();
    form.submit();
}
</script>
@endsection
