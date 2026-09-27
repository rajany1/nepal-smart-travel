@extends('admin.layout')

@section('title', 'Preview: ' . $document->title)

@section('content')
<div class="min-h-screen bg-slate-50 p-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-3">
                <div class="p-2.5 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl shadow-lg">
                    <i class="fas fa-eye text-white text-lg"></i>
                </div>
                Preview
                @if($document->status === \App\Models\LegalDocument::STATUS_DRAFT)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-semibold">
                        <span class="w-1.5 h-1.5 bg-amber-500 rounded-full"></span> Draft
                    </span>
                @elseif($document->status === \App\Models\LegalDocument::STATUS_PUBLISHED)
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-semibold">
                        <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span> Published
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-200 text-slate-600 rounded-full text-xs font-semibold">
                        <span class="w-1.5 h-1.5 bg-slate-500 rounded-full"></span> Archived
                    </span>
                @endif
            </h1>
            <p class="text-slate-500 mt-1 ml-12">
                {{ $types[$document->type] ?? $document->type }} &middot;
                v{{ $document->version ?? '1.0' }} &middot;
                /legal/{{ $document->slug ?? $document->type }}
            </p>
        </div>
        <div class="mt-4 sm:mt-0 flex items-center gap-3">
            <a href="{{ route('admin.legal-documents.versions', $document->id) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-white text-slate-700 rounded-xl font-medium shadow-sm hover:shadow-md border border-slate-200 transition-all">
                <i class="fas fa-clock-rotate-left text-sm"></i> History
            </a>
            <a href="{{ route('admin.legal-documents.edit', $document->id) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-white text-slate-700 rounded-xl font-medium shadow-sm hover:shadow-md border border-slate-200 transition-all">
                <i class="fas fa-pen text-sm"></i> Edit
            </a>
            <a href="{{ route('admin.legal-documents.index') }}"
               class="inline-flex items-center gap-2 px-5 py-2.5 bg-white text-slate-700 rounded-xl font-medium shadow-sm hover:shadow-md border border-slate-200 transition-all">
                <i class="fas fa-arrow-left text-sm"></i> Back
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
    @if(session('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl flex items-center gap-3">
            <div class="p-2 bg-red-100 rounded-lg">
                <i class="fas fa-exclamation-circle text-red-600"></i>
            </div>
            <p class="text-red-700 font-medium flex-1">{{ session('error') }}</p>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 md:p-10">
                <h1 class="font-serif text-3xl font-bold text-slate-900 mb-3">{{ $document->title }}</h1>

                @if($document->short_description)
                    <p class="text-slate-500 text-base mb-4">{{ $document->short_description }}</p>
                @endif

                <div class="flex flex-wrap items-center gap-2 pb-6 mb-6 border-b border-slate-100">
                    <span class="px-3 py-1 bg-indigo-50 text-indigo-600 rounded-lg text-xs font-semibold">v{{ $document->version ?? '1.0' }}</span>
                    @if($document->effective_date)
                        <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-lg text-xs font-semibold">
                            Effective {{ $document->effective_date->format('M j, Y') }}
                        </span>
                    @endif
                    @if($document->published_at)
                        <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-lg text-xs font-semibold">
                            Published {{ $document->published_at->format('M j, Y') }}
                        </span>
                    @endif
                    <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-lg text-xs font-semibold">
                        Updated {{ $document->updated_at?->format('M j, Y') ?? '—' }}
                    </span>
                </div>

                <div class="prose prose-slate max-w-none prose-headings:font-serif prose-a:text-indigo-600 prose-img:rounded-xl">
                    {!! \App\Services\HtmlSanitizer::clean($document->content) !!}
                </div>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <i class="fas fa-circle-info text-indigo-500"></i>
                        Document Info
                    </h2>
                </div>
                <div class="p-5 space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Type</span>
                        <span class="font-medium text-slate-900">{{ $types[$document->type] ?? $document->type }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Slug</span>
                        <code class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded text-xs">{{ $document->slug ?? $document->type }}</code>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Status</span>
                        @if($document->status === \App\Models\LegalDocument::STATUS_PUBLISHED)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-semibold">
                                <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span> Published
                            </span>
                        @elseif($document->status === \App\Models\LegalDocument::STATUS_ARCHIVED)
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-slate-200 text-slate-600 rounded-full text-xs font-semibold">
                                <span class="w-1.5 h-1.5 bg-slate-500 rounded-full"></span> Archived
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-semibold">
                                <span class="w-1.5 h-1.5 bg-amber-500 rounded-full"></span> Draft
                            </span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Created</span>
                        <span class="text-slate-900">{{ $document->created_at?->diffForHumans() ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Updated</span>
                        <span class="text-slate-900">{{ $document->updated_at?->diffForHumans() ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Public URL</span>
                        @if($document->isPublishedStatus())
                            <a href="{{ route('web.legal.show', $document->slug ?? $document->type) }}" target="_blank"
                               class="text-indigo-600 font-medium hover:underline">
                                /legal/{{ $document->slug ?? $document->type }} <i class="fas fa-arrow-up-right-from-square text-xs"></i>
                            </a>
                        @else
                            <span class="text-slate-400">Not public yet</span>
                        @endif
                    </div>
                    @if($document->safe_reference_url)
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-500">Reference URL</span>
                            <a href="{{ $document->safe_reference_url }}" target="_blank" rel="noopener noreferrer"
                               class="text-indigo-600 font-medium hover:underline truncate max-w-[160px]" title="{{ $document->safe_reference_url }}">
                                {{ $document->safe_reference_url }} <i class="fas fa-arrow-up-right-from-square text-xs"></i>
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            @if($document->isDraft())
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-5 border-b border-slate-100">
                        <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                            <i class="fas fa-bolt text-amber-500"></i>
                            Actions
                        </h2>
                    </div>
                    <div class="p-5 space-y-3">
                        <a href="{{ route('admin.legal-documents.edit', $document->id) }}"
                           class="w-full py-2.5 px-4 bg-indigo-50 text-indigo-600 rounded-xl font-medium hover:bg-indigo-100 transition-colors flex items-center justify-center gap-2">
                            <i class="fas fa-pen"></i> Edit Draft
                        </a>
                        <form action="{{ route('admin.legal-documents.publish', $document->id) }}" method="POST">
                            @csrf
                            <button type="submit"
                                    class="w-full py-2.5 px-4 bg-emerald-50 text-emerald-600 rounded-xl font-medium hover:bg-emerald-100 transition-colors flex items-center justify-center gap-2">
                                <i class="fas fa-globe"></i> Publish Now
                            </button>
                        </form>
                    </div>
                </div>
            @elseif($document->isPublishedStatus())
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-5 border-b border-slate-100">
                        <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                            <i class="fas fa-bolt text-amber-500"></i>
                            Actions
                        </h2>
                    </div>
                    <div class="p-5 space-y-3">
                        <a href="{{ route('admin.legal-documents.edit', $document->id) }}"
                           class="w-full py-2.5 px-4 bg-indigo-50 text-indigo-600 rounded-xl font-medium hover:bg-indigo-100 transition-colors flex items-center justify-center gap-2">
                            <i class="fas fa-pen"></i> Edit (creates draft)
                        </a>
                        <form action="{{ route('admin.legal-documents.archive', $document->id) }}" method="POST"
                              onsubmit="return confirm('Archive this document? It will be removed from the public Legal Center but kept as history.')">
                            @csrf
                            <button type="submit"
                                    class="w-full py-2.5 px-4 bg-amber-50 text-amber-600 rounded-xl font-medium hover:bg-amber-100 transition-colors flex items-center justify-center gap-2">
                                <i class="fas fa-box-archive"></i> Archive
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            <div class="bg-slate-100 rounded-2xl p-4 text-xs text-slate-500 leading-relaxed">
                <i class="fas fa-shield-halved text-slate-400 mr-1"></i>
                Content is sanitized with an HTML allowlist on save and on render.
            </div>
        </div>
    </div>
</div>
@endsection
