@extends('admin.layout')
@section('title', 'Place Corrections')

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="px-6 py-3 border-b border-gray-100 flex items-center gap-1">
        <a href="{{ route('admin.places') }}" class="px-3 py-1.5 text-sm font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">
            <i class="fas fa-database mr-1"></i> Database
        </a>
        <a href="{{ route('admin.places.osm') }}" class="px-3 py-1.5 text-sm font-medium rounded-lg bg-gray-100 text-gray-700 hover:bg-gray-200">
            <i class="fas fa-globe-asia mr-1"></i> OSM Live
        </a>
        <a href="{{ route('admin.places.corrections') }}" class="px-3 py-1.5 text-sm font-medium rounded-lg bg-amber-600 text-white">
            <i class="fas fa-flag mr-1"></i> Corrections
            @if($counts['pending'] > 0)
            <span class="ml-1 px-1.5 py-0.5 text-xs bg-white/20 rounded-full">{{ $counts['pending'] }}</span>
            @endif
        </a>
    </div>
    <div class="px-6 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-4">
        <h3 class="font-semibold text-gray-800">Place Correction Requests</h3>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.places.corrections', ['status' => 'pending']) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $status === 'pending' ? 'bg-amber-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                Pending ({{ $counts['pending'] }})
            </a>
            <a href="{{ route('admin.places.corrections', ['status' => 'applied']) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $status === 'applied' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                Applied ({{ $counts['applied'] }})
            </a>
            <a href="{{ route('admin.places.corrections', ['status' => 'rejected']) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $status === 'rejected' ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                Rejected ({{ $counts['rejected'] }})
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="px-6 py-3 bg-green-50 border-b border-green-100 text-green-700 text-sm">
        <i class="fas fa-check-circle mr-1"></i> {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="px-6 py-3 bg-red-50 border-b border-red-100 text-red-700 text-sm">
        <i class="fas fa-exclamation-circle mr-1"></i> {{ session('error') }}
    </div>
    @endif

    <div class="overflow-x-auto">
<div id="liveTable">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">#</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Place</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">User Report</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Suggested Fix</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Submitted</th>
                    <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($corrections as $c)
                <tr class="hover:bg-gray-50 {{ $c->status === 'pending' ? 'bg-amber-50/40' : '' }}">
                    <td class="px-6 py-4 text-sm text-gray-500">#{{ $c->id }}</td>
                    <td class="px-6 py-4">
                        <p class="text-sm font-medium text-gray-900">{{ $c->place_name }}</p>
                        <p class="text-xs text-gray-500">
                            @if($c->place_id)
                            <a href="{{ route('admin.places.view', $c->place_id) }}" class="text-blue-600 hover:underline">Place #{{ $c->place_id }}</a>
                            @endif
                            @if($c->osm_id)<span class="text-gray-400">Â· {{ $c->osm_id }}</span>@endif
                        </p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ $c->description ?? 'â€”' }}</p>
                    </td>
                    <td class="px-6 py-4">
                        <span class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded">{{ str_replace('_', ' ', ucwords($c->correction_type)) }}</span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $c->user?->name ?? 'Unknown' }}</td>
                    <td class="px-6 py-4 text-xs text-gray-600">
                        @if($c->suggested_name)<p><b>Name:</b> {{ $c->suggested_name }}</p>@endif
                        @if($c->suggested_latitude !== null)<p><b>Loc:</b> {{ number_format($c->suggested_latitude, 5) }}, {{ number_format($c->suggested_longitude, 5) }}</p>@endif
                        @if(!$c->suggested_name && $c->suggested_latitude === null)<span class="text-gray-400">â€”</span>@endif
                    </td>
                    <td class="px-6 py-4">
                        @if($c->status === 'pending')
                        <span class="text-xs bg-amber-100 text-amber-700 px-2 py-1 rounded font-medium">Pending</span>
                        @elseif($c->status === 'applied')
                        <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded font-medium">Applied</span>
                        @else
                        <span class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded font-medium">Rejected</span>
                        @endif
                        @if($c->admin_note)
                        <p class="text-xs text-gray-400 mt-1">"{{ $c->admin_note }}"</p>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-xs text-gray-500">{{ $c->created_at?->format('M d, H:i') }}</td>
                    <td class="px-6 py-4 text-right">
                        @if($c->status === 'pending')
                        <div class="flex gap-2 justify-end">
                            <form method="POST" action="{{ route('admin.places.corrections.apply', $c->id) }}" class="inline" onsubmit="return confirm('Apply this correction (updates place if it exists)?');">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 text-xs font-medium bg-green-50 text-green-600 rounded-lg hover:bg-green-100 transition" title="Apply">
                                    <i class="fas fa-check mr-1"></i>Apply
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.places.corrections.reject', $c->id) }}" class="inline" onsubmit="return confirm('Reject this correction?');">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition" title="Reject">
                                    <i class="fas fa-times mr-1"></i>Reject
                                </button>
                            </form>
                        </div>
                        @else
                        <span class="text-xs text-gray-400">Reviewed {{ $c->reviewed_at?->format('M d, H:i') }}</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                        <i class="fas fa-flag text-3xl text-gray-300 mb-3 block"></i>
                        No {{ $status }} corrections found
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($corrections->hasPages())
    <div class="px-6 py-4 border-t border-gray-100">{{ $corrections->links() }}</div>
</div>
    @endif
</div>
@endsection
