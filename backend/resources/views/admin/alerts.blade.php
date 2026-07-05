@extends('admin.layout')
@section('title', 'Alerts Management')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-4">
            <h3 class="font-semibold text-gray-800">All Alerts</h3>
            <div class="flex gap-2">
                <a href="{{ route('admin.alerts', ['severity' => 'all']) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $severity === 'all' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">All</a>
                <a href="{{ route('admin.alerts', ['severity' => 'critical']) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $severity === 'critical' ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Critical</a>
                <a href="{{ route('admin.alerts', ['severity' => 'high']) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $severity === 'high' ? 'bg-orange-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">High</a>
                <a href="{{ route('admin.alerts', ['severity' => 'info']) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $severity === 'info' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Info</a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Title</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Severity</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">District</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Date</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($alerts as $alert)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <p class="text-sm font-medium text-gray-900 max-w-[250px] truncate">{{ $alert->title }}</p>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ ucfirst(str_replace('_', ' ', $alert->alert_type)) }}</td>
                        <td class="px-6 py-4">
                            <span class="text-xs font-medium px-2 py-1 rounded-full 
                                {{ $alert->severity === 'critical' ? 'bg-red-100 text-red-800' : '' }}
                                {{ $alert->severity === 'high' ? 'bg-orange-100 text-orange-800' : '' }}
                                {{ $alert->severity === 'medium' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                {{ $alert->severity === 'low' || $alert->severity === 'info' ? 'bg-blue-100 text-blue-800' : '' }}">
                                {{ ucfirst($alert->severity) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $alert->affected_district ?? '-' }}</td>
                        <td class="px-6 py-4 text-sm text-gray-500">{{ $alert->created_at->format('M d, Y') }}</td>
                        <td class="px-6 py-4 text-right">
                            <form method="POST" action="{{ route('admin.alerts.delete', $alert->id) }}" class="inline" onsubmit="return confirm('Delete this alert?');">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-bell-slash text-3xl text-gray-300 mb-3 block"></i>
                            No alerts found
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($alerts->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">{{ $alerts->links() }}</div>
        @endif
    </div>

    <!-- Create Alert Form -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="font-semibold text-gray-800">Create New Alert</h3>
        </div>
        <div class="p-6">
            <form method="POST" action="{{ route('admin.alerts.create') }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                        <input type="text" name="title" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" rows="3" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Alert Type</label>
                        <select name="alert_type" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="weather">Weather</option>
                            <option value="landslide">Landslide</option>
                            <option value="earthquake">Earthquake</option>
                            <option value="strike">Strike/Bandh</option>
                            <option value="emergency">Emergency</option>
                            <option value="traffic">Traffic</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Severity</label>
                        <select name="severity" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="info">Info</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Affected District</label>
                        <input type="text" name="affected_district" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <button type="submit" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                        <i class="fas fa-plus mr-1"></i> Create Alert
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection