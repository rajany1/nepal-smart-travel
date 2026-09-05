@extends('admin.layout')

@section('title', 'Legal Documents')

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
            <p class="text-slate-500 mt-1 ml-12">Manage your app's privacy policy, terms, and legal content.</p>
        </div>
        <a href="{{ route('admin.legal-documents.create') }}" 
           class="mt-4 sm:mt-0 inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-xl font-medium shadow-lg shadow-indigo-200 hover:shadow-xl hover:shadow-indigo-300 transition-all duration-200">
            <i class="fas fa-plus text-sm"></i> New Document
        </a>
    </div>

    {{-- Success Message --}}
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

    {{-- Documents Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
        @forelse($documents as $doc)
            <div class="group bg-white rounded-2xl shadow-sm hover:shadow-xl border border-slate-100 hover:border-indigo-200 transition-all duration-300 overflow-hidden">
                {{-- Card Header --}}
                <div class="p-5 pb-3">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="p-2.5 {{ $doc->is_published ? 'bg-gradient-to-br from-emerald-400 to-teal-500' : 'bg-gradient-to-br from-amber-400 to-orange-500' }} rounded-xl shadow-md">
                                <i class="fas {{ $doc->is_published ? 'fa-globe' : 'fa-pen-to-square' }} text-white text-sm"></i>
                            </div>
                            <div>
                                <span class="text-xs font-semibold uppercase tracking-wider {{ $doc->is_published ? 'text-emerald-600' : 'text-amber-600' }}">
                                    {{ $doc->is_published ? 'Published' : 'Draft' }}
                                </span>
                            </div>
                        </div>
                        <span class="px-2.5 py-1 bg-slate-100 text-slate-600 text-xs font-medium rounded-lg">
                            v{{ $doc->version ?? '1.0' }}
                        </span>
                    </div>
                    
                    <h3 class="mt-4 text-lg font-bold text-slate-900 group-hover:text-indigo-600 transition-colors">
                        {{ $doc->title }}
                    </h3>
                    
                    <div class="mt-2 flex items-center gap-2 text-sm text-slate-500">
                        <span class="px-2 py-0.5 bg-slate-100 rounded-md text-xs font-medium">
                            {{ $types[$doc->type] ?? $doc->type }}
                        </span>
                        <span>&middot;</span>
                        <span>{{ $doc->updated_at?->diffForHumans() ?? 'Never' }}</span>
                    </div>
                </div>

                {{-- Card Actions --}}
                <div class="px-5 pb-5 pt-2">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.legal-documents.edit', $doc->id) }}" 
                           class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-indigo-50 text-indigo-600 rounded-xl font-medium hover:bg-indigo-100 transition-colors">
                            <i class="fas fa-pen text-xs"></i> Edit
                        </a>
                        
                        @if(!$doc->is_published)
                            <form action="{{ route('admin.legal-documents.publish', $doc->id) }}" method="POST" class="flex-1">
                                @csrf
                                <button type="submit" 
                                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-50 text-emerald-600 rounded-xl font-medium hover:bg-emerald-100 transition-colors">
                                    <i class="fas fa-globe text-xs"></i> Publish
                                </button>
                            </form>
                        @else
                            <form action="{{ route('admin.legal-documents.unpublish', $doc->id) }}" method="POST" class="flex-1">
                                @csrf
                                <button type="submit" 
                                        class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-amber-50 text-amber-600 rounded-xl font-medium hover:bg-amber-100 transition-colors">
                                    <i class="fas fa-eye-slash text-xs"></i> Unpublish
                                </button>
                            </form>
                        @endif
                        
                        <form action="{{ route('admin.legal-documents.delete', $doc->id) }}" method="POST" 
                              onsubmit="return confirm('Are you sure you want to delete this document? This action cannot be undone.')">
                            @csrf
                            <button type="submit" 
                                    class="p-2.5 bg-red-50 text-red-500 rounded-xl hover:bg-red-100 transition-colors"
                                    title="Delete">
                                <i class="fas fa-trash text-xs"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            {{-- Empty State --}}
            <div class="col-span-full">
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-12 text-center">
                    <div class="inline-flex p-4 bg-slate-100 rounded-2xl mb-4">
                        <i class="fas fa-file-contract text-4xl text-slate-300"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-700 mb-2">No legal documents yet</h3>
                    <p class="text-slate-500 mb-6 max-w-md mx-auto">
                        Create your first legal document to get started. Add privacy policy, terms & conditions, and more.
                    </p>
                    <a href="{{ route('admin.legal-documents.create') }}" 
                       class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-xl font-medium shadow-lg">
                        <i class="fas fa-plus"></i> Create First Document
                    </a>
                </div>
            </div>
        @endforelse
    </div>
</div>
@endsection
