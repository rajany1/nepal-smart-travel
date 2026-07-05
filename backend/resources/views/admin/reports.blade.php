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
    <div class="px-6 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-4">
        <h3 class="font-semibold text-gray-800">All Reports</h3>
        <div class="flex gap-2">
            <a href="{{ route('admin.reports', ['status' => 'all']) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $status === 'all' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">All</a>
            <a href="{{ route('admin.reports', ['status' => 'pending']) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $status === 'pending' ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Pending</a>
            <a href="{{ route('admin.reports', ['status' => 'approved']) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $status === 'approved' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Approved</a>
            <a href="{{ route('admin.reports', ['status' => 'rejected']) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $status === 'rejected' ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Rejected</a>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">ID</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Title</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Reporter</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Priority</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">GPS Verify</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($reports as $report)
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm text-gray-500">#{{ $report->id }}</td>
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
                    <td class="px-6 py-4 text-sm text-gray-500">{{ $report->created_at->format('M d, Y') }}</td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            @if($report->status === 'pending')
                            <form method="POST" action="{{ route('admin.reports.approve', $report->id) }}" class="inline">
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
                            <a href="{{ route('admin.reports.view', $report->id) }}" class="px-3 py-1.5 text-xs font-medium bg-indigo-100 text-indigo-700 rounded-lg hover:bg-indigo-200 transition">
                                <i class="fas fa-eye mr-1"></i>View
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                        <i class="fas fa-inbox text-3xl text-gray-300 mb-3 block"></i>
                        No reports found
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($reports->hasPages())
    <div class="px-6 py-4 border-t border-gray-100">
        {{ $reports->links() }}
    </div>
    @endif
</div>
@endsection