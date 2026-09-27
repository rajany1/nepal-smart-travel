@extends('admin.layout')

@section('title', 'Legal & Policies')

@section('content')
<div class="min-h-screen bg-slate-50 p-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-3">
                <div class="p-2.5 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl shadow-lg">
                    <i class="fas fa-file-contract text-white text-lg"></i>
                </div>
                Legal Documents
            </h1>
            <p class="text-slate-500 mt-1 ml-12">Draft, preview, publish and version your app's legal documents.</p>
        </div>
        <div class="mt-4 sm:mt-0 flex items-center gap-3">
            <a href="{{ route('web.legal.index') }}" target="_blank"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-white text-slate-700 rounded-xl font-medium shadow-sm hover:shadow-md border border-slate-200 transition-all">
                <i class="fas fa-arrow-up-right-from-square text-sm"></i> View Legal Center
            </a>
            <a href="{{ route('admin.legal-documents.create') }}"
               class="inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-xl font-medium shadow-lg shadow-indigo-200 hover:shadow-xl hover:shadow-indigo-300 transition-all duration-200">
                <i class="fas fa-plus text-sm"></i> New Document
            </a>
        </div>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center gap-3" x-data="{ show: true }" x-show="show" x-transition>
            <div class="p-2 bg-emerald-100 rounded-lg">
                <i class="fas fa-check-circle text-emerald-600"></i>
            </div>
            <p class="text-emerald-700 font-medium flex-1">{{ session('success') }}</p>
            <button onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-emerald-600">
                <i class="fas fa-times"></i>
            </button>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl flex items-center gap-3">
            <div class="p-2 bg-red-100 rounded-lg">
                <i class="fas fa-exclamation-circle text-red-600"></i>
            </div>
            <p class="text-red-700 font-medium flex-1">{{ session('error') }}</p>
        </div>
    @endif

    {{-- Filters --}}
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 mb-6 overflow-hidden">
        <div class="p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2">
                @php
                    $tabs = [
                        'all' => 'All',
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'archived' => 'Archived',
                    ];
                @endphp
                @foreach($tabs as $key => $label)
                    <a href="{{ route('admin.legal-documents.index', array_filter(['status' => $key === 'all' ? null : $key, 'search' => $search ?: null])) }}"
                       class="px-4 py-2 rounded-full text-sm font-medium transition-colors {{ $status === $key ? 'bg-indigo-600 text-white shadow' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        {{ $label }}
                        <span class="ml-1.5 text-xs {{ $status === $key ? 'text-indigo-200' : 'text-slate-400' }}">{{ $counts[$key] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>
            <form method="GET" action="{{ route('admin.legal-documents.index') }}" class="flex items-center gap-2">
                @if($status !== 'all')
                    <input type="hidden" name="status" value="{{ $status }}">
                @endif
                <input type="text" name="search" value="{{ $search }}" placeholder="Search title or slug..."
                       class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 w-56">
                <button type="submit" class="px-4 py-2.5 bg-indigo-50 text-indigo-600 rounded-xl text-sm font-medium hover:bg-indigo-100 transition-colors">
                    <i class="fas fa-search"></i> Search
                </button>
                @if($search || $status !== 'all')
                    <a href="{{ route('admin.legal-documents.index') }}" class="px-3 py-2.5 text-slate-500 text-sm hover:text-slate-700">Clear</a>
                @endif
            </form>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-y border-slate-100">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Document</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Version</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Effective Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($documents as $row)
                        @php $doc = $row['document']; @endphp
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-6 py-4">
                                <div class="font-semibold text-slate-900">{{ $doc->title }}</div>
                                <div class="text-xs text-slate-400 mt-0.5">
                                    /legal/{{ $doc->slug ?? $doc->type }}
                                    @if($doc->slug !== $doc->type)
                                        <span class="text-slate-300">&middot;</span> {{ $types[$doc->type] ?? $doc->type }}
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-4">
                                <span class="px-2.5 py-1 bg-slate-100 text-slate-600 text-xs font-semibold rounded-lg">v{{ $doc->version ?? '1.0' }}</span>
                                @if($row['hasDraft'])
                                    <a href="{{ route('admin.legal-documents.edit', $row['draft']->id) }}"
                                       class="ml-1.5 px-2 py-1 bg-amber-50 text-amber-600 text-[11px] font-semibold rounded-lg hover:bg-amber-100 inline-block">
                                        Draft pending
                                    </a>
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                @if($doc->status === 'published')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-semibold">
                                        <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span> Published
                                    </span>
                                @elseif($doc->status === 'archived')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-slate-200 text-slate-600 rounded-full text-xs font-semibold">
                                        <span class="w-1.5 h-1.5 bg-slate-500 rounded-full"></span> Archived
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-semibold">
                                        <span class="w-1.5 h-1.5 bg-amber-500 rounded-full"></span> Draft
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-sm text-slate-600">
                                {{ $doc->effective_date?->format('M j, Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-4 text-sm text-slate-500">
                                {{ $doc->updated_at?->diffForHumans() ?? 'Never' }}
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center justify-end gap-1.5 flex-wrap">
                                    <a href="{{ route('admin.legal-documents.preview', $doc->id) }}" target="_blank"
                                       class="px-2.5 py-1.5 bg-slate-100 text-slate-600 rounded-lg text-xs font-medium hover:bg-slate-200 transition-colors"
                                       title="{{ $doc->status === 'draft' ? 'Preview draft' : 'View document' }}">
                                        <i class="fas {{ $doc->status === 'draft' ? 'fa-eye' : 'fa-arrow-up-right-from-square' }} text-[11px]"></i>
                                        {{ $doc->status === 'draft' ? 'Preview' : 'View' }}
                                    </a>
                                    <a href="{{ route('admin.legal-documents.edit', $doc->id) }}"
                                       class="px-2.5 py-1.5 bg-indigo-50 text-indigo-600 rounded-lg text-xs font-medium hover:bg-indigo-100 transition-colors">
                                        <i class="fas fa-pen text-[11px]"></i> Edit
                                    </a>

                                    @if($doc->status !== 'published')
                                        <form action="{{ route('admin.legal-documents.publish', $doc->id) }}" method="POST">
                                            @csrf
                                            <button type="submit"
                                                    class="px-2.5 py-1.5 bg-emerald-50 text-emerald-600 rounded-lg text-xs font-medium hover:bg-emerald-100 transition-colors">
                                                <i class="fas fa-globe text-[11px]"></i> Publish
                                            </button>
                                        </form>
                                    @else
                                        <form action="{{ route('admin.legal-documents.archive', $doc->id) }}" method="POST"
                                              onsubmit="return confirm('Archive this document? It will be removed from the public Legal Center but kept as history.')">
                                            @csrf
                                            <button type="submit"
                                                    class="px-2.5 py-1.5 bg-amber-50 text-amber-600 rounded-lg text-xs font-medium hover:bg-amber-100 transition-colors">
                                                <i class="fas fa-box-archive text-[11px]"></i> Archive
                                            </button>
                                        </form>
                                    @endif

                                    <a href="{{ route('admin.legal-documents.versions', $doc->id) }}"
                                       class="px-2.5 py-1.5 bg-purple-50 text-purple-600 rounded-lg text-xs font-medium hover:bg-purple-100 transition-colors"
                                       title="Version history ({{ $row['versionsCount'] }})">
                                        <i class="fas fa-clock-rotate-left text-[11px]"></i> History
                                    </a>

                                    @if($doc->status === 'draft')
                                        <form action="{{ route('admin.legal-documents.delete', $doc->id) }}" method="POST"
                                              onsubmit="return confirm('Delete this draft? This cannot be undone.')">
                                            @csrf
                                            <button type="submit"
                                                    class="p-1.5 bg-red-50 text-red-500 rounded-lg hover:bg-red-100 transition-colors"
                                                    title="Delete draft">
                                                <i class="fas fa-trash text-[11px]"></i>
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="inline-flex p-4 bg-slate-100 rounded-2xl mb-4">
                                    <i class="fas fa-file-contract text-4xl text-slate-300"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-slate-700 mb-2">
                                    {{ $search || $status !== 'all' ? 'No documents match your filters' : 'No legal documents yet' }}
                                </h3>
                                <p class="text-slate-500 mb-6 max-w-md mx-auto">
                                    {{ $search || $status !== 'all' ? 'Try a different search or clear the filters.' : 'Create your first legal document to get started.' }}
                                </p>
                                @unless($search || $status !== 'all')
                                    <a href="{{ route('admin.legal-documents.create') }}"
                                       class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-xl font-medium shadow-lg">
                                        <i class="fas fa-plus"></i> Create First Document
                                    </a>
                                @endunless
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($documents->hasPages())
            <div class="px-4 py-3 border-t border-slate-100">
                {{ $documents->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
