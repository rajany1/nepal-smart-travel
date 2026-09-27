@extends('admin.layout')

@section('title', 'Version History: ' . $document->title)

@section('content')
<div class="min-h-screen bg-slate-50 p-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-3">
                <div class="p-2.5 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl shadow-lg">
                    <i class="fas fa-clock-rotate-left text-white text-lg"></i>
                </div>
                Version History
            </h1>
            <p class="text-slate-500 mt-1 ml-12">
                <span class="font-medium text-slate-700">{{ $document->title }}</span>
                &middot; /legal/{{ $document->slug ?? $document->type }}
                &middot; {{ $versions->count() }} version{{ $versions->count() === 1 ? '' : 's' }}
            </p>
        </div>
        <div class="mt-4 sm:mt-0 flex items-center gap-3">
            <a href="{{ route('admin.legal-documents.edit', $document->id) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-white text-slate-700 rounded-xl font-medium shadow-sm hover:shadow-md border border-slate-200 transition-all">
                <i class="fas fa-pen text-sm"></i> Edit
            </a>
            <a href="{{ route('admin.legal-documents.index') }}"
               class="inline-flex items-center gap-2 px-5 py-2.5 bg-white text-slate-700 rounded-xl font-medium shadow-sm hover:shadow-md border border-slate-200 transition-all">
                <i class="fas fa-arrow-left text-sm"></i> Back to Documents
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-xl flex items-center gap-3">
            <div class="p-2 bg-emerald-100 rounded-lg">
                <i class="fas fa-check-circle text-emerald-600"></i>
            </div>
            <p class="text-emerald-700 font-medium flex-1">{{ session('success') }}</p>
        </div>
    @endif

    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-slate-100">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Version</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Effective</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Published</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($versions as $version)
                        <tr class="hover:bg-slate-50/70 transition-colors {{ $version->id === $document->id ? 'bg-indigo-50/50' : '' }}">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <span class="px-2.5 py-1 bg-slate-100 text-slate-700 text-xs font-bold rounded-lg">v{{ $version->version ?? '1.0' }}</span>
                                    @if($version->id === $document->id)
                                        <span class="text-[11px] font-semibold text-indigo-500">viewing</span>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-400 mt-1">#{{ $version->id }}</div>
                            </td>
                            <td class="px-4 py-4">
                                @if($version->status === \App\Models\LegalDocument::STATUS_PUBLISHED)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-semibold">
                                        <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span> Published
                                    </span>
                                @elseif($version->status === \App\Models\LegalDocument::STATUS_ARCHIVED)
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
                                {{ $version->effective_date?->format('M j, Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-4 text-sm text-slate-600">
                                {{ $version->creator?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-4 text-sm text-slate-500">
                                {{ $version->updated_at?->diffForHumans() ?? '—' }}
                            </td>
                            <td class="px-4 py-4 text-sm text-slate-500">
                                {{ $version->published_at?->format('M j, Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-4">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.legal-documents.preview', $version->id) }}" target="_blank"
                                       class="px-2.5 py-1.5 bg-slate-100 text-slate-600 rounded-lg text-xs font-medium hover:bg-slate-200 transition-colors">
                                        <i class="fas fa-eye text-[11px]"></i> View
                                    </a>
                                    @if($version->status === \App\Models\LegalDocument::STATUS_DRAFT)
                                        <a href="{{ route('admin.legal-documents.edit', $version->id) }}"
                                           class="px-2.5 py-1.5 bg-indigo-50 text-indigo-600 rounded-lg text-xs font-medium hover:bg-indigo-100 transition-colors">
                                            <i class="fas fa-pen text-[11px]"></i> Edit
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="inline-flex p-4 bg-slate-100 rounded-2xl mb-4">
                                    <i class="fas fa-clock-rotate-left text-4xl text-slate-300"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-slate-700 mb-2">No versions found</h3>
                                <p class="text-slate-500">This document has no version history yet.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 bg-slate-100 rounded-2xl p-4 text-xs text-slate-500 leading-relaxed max-w-3xl">
        <i class="fas fa-circle-info text-slate-400 mr-1"></i>
        Only one version can be <strong>published</strong> at a time. Publishing a draft automatically archives the previous
        published version. Archived versions are kept here permanently and can be re-published; drafts can be deleted.
    </div>
</div>
@endsection
