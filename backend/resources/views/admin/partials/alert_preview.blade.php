<div class="bg-white border-2 rounded-xl overflow-hidden shadow-lg max-w-sm mx-auto my-4" style="border-color: {{ $severity === 'critical' ? '#ef4444' : ($severity === 'high' ? '#f97316' : '#009688') }};">
    <div class="flex items-center gap-3 px-4 py-3 {{ $severity_class }} border-b">
        <i class="fas {{ $severity_icon }} text-xl"></i>
        <div>
            <p class="font-semibold text-sm">{{ $alert_type_label }} Alert</p>
            <p class="text-xs opacity-90">{{ ucfirst($severity) }} Priority</p>
        </div>
        <span class="ml-auto px-2 py-1 text-xs font-medium rounded-full bg-white/30">{{ strtoupper($language) }}</span>
    </div>
    
    <div class="p-4">
        <h3 class="font-bold text-gray-900 text-base mb-2 leading-tight">{{ $title }}</h3>
        <p class="text-gray-700 text-sm leading-relaxed whitespace-pre-line">{{ $description }}</p>
    </div>
    
    <div class="px-4 py-3 bg-gray-50 border-t border-gray-100 flex items-center gap-3">
        <i class="fas fa-map-marker-alt text-primary-600"></i>
        <span class="text-sm font-medium text-gray-700">{{ $district }}</span>
    </div>
</div>