@extends('admin.layout')

@section('title', 'Edit: ' . $document->title)

@section('content')
<div class="min-h-screen bg-slate-50 p-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-3">
                <div class="p-2.5 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl shadow-lg">
                    <i class="fas fa-pen text-white text-lg"></i>
                </div>
                Edit Document
            </h1>
            <p class="text-slate-500 mt-1 ml-12">Editing: <span class="font-medium text-slate-700">{{ $document->title }}</span></p>
        </div>
        <a href="{{ route('admin.legal-documents.index') }}" 
           class="mt-4 sm:mt-0 inline-flex items-center gap-2 px-5 py-2.5 bg-white text-slate-700 rounded-xl font-medium shadow-sm hover:shadow-md border border-slate-200 transition-all">
            <i class="fas fa-arrow-left text-sm"></i> Back to Documents
        </a>
    </div>

    {{-- Errors --}}
    @if($errors->any())
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-xl">
            <div class="flex items-center gap-3">
                <div class="p-2 bg-red-100 rounded-lg">
                    <i class="fas fa-exclamation-circle text-red-600"></i>
                </div>
                <div>
                    <p class="font-semibold text-red-700">Please fix the following errors:</p>
                    <ul class="mt-1 text-sm text-red-600 list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <form action="{{ route('admin.legal-documents.update', $document->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Main Content --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Document Details --}}
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-5 border-b border-slate-100">
                        <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                            <i class="fas fa-file-lines text-indigo-500"></i>
                            Document Details
                        </h2>
                    </div>
                    <div class="p-5 space-y-5">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Document Type</label>
                            <div class="w-full px-4 py-3 bg-slate-100 border border-slate-200 rounded-xl text-slate-600 font-medium">
                                {{ $types[$document->type] ?? $document->type }}
                            </div>
                            <input type="hidden" name="type" value="{{ $document->type }}">
                        </div>

                        <div>
                            <label for="title" class="block text-sm font-semibold text-slate-700 mb-2">Title *</label>
                            <input type="text" name="title" id="title" 
                                   class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                                   value="{{ old('title', $document->title) }}" 
                                   required>
                        </div>

                        <div>
                            <label for="version" class="block text-sm font-semibold text-slate-700 mb-2">Version</label>
                            <input type="text" name="version" id="version" 
                                   class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                                   value="{{ old('version', $document->version) }}">
                        </div>
                    </div>
                </div>

                {{-- Content Editor --}}
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-5 border-b border-slate-100">
                        <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                            <i class="fas fa-pen-fancy text-indigo-500"></i>
                            Content
                        </h2>
                        <p class="text-sm text-slate-500 mt-1">Edit the document content. Changes will be saved when you click "Update Document".</p>
                    </div>
                    <div class="p-5">
                        <div id="editor-container" style="min-height: 450px; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;"></div>
                        <input type="hidden" name="content" id="content" value="{{ old('content', $document->content) }}">
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                {{-- Document Info --}}
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-5 border-b border-slate-100">
                        <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                            <i class="fas fa-circle-info text-indigo-500"></i>
                            Document Info
                        </h2>
                    </div>
                    <div class="p-5 space-y-4">
                        <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                            <span class="text-sm font-medium text-slate-600">Status</span>
                            @if($document->is_published)
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-semibold">
                                    <span class="w-1.5 h-1.5 bg-emerald-500 rounded-full"></span> Published
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-semibold">
                                    <span class="w-1.5 h-1.5 bg-amber-500 rounded-full"></span> Draft
                                </span>
                            @endif
                        </div>
                        
                        <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                            <span class="text-sm font-medium text-slate-600">Created</span>
                            <span class="text-sm text-slate-900">{{ $document->created_at?->diffForHumans() ?? '-' }}</span>
                        </div>
                        
                        <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                            <span class="text-sm font-medium text-slate-600">Updated</span>
                            <span class="text-sm text-slate-900">{{ $document->updated_at?->diffForHumans() ?? '-' }}</span>
                        </div>

                        <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                            <span class="text-sm font-medium text-slate-600">Publish</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="is_published" value="0">
                                <input type="checkbox" name="is_published" id="is_published" 
                                       class="sr-only peer" 
                                       value="1" {{ old('is_published', $document->is_published) ? 'checked' : '' }}>
                                <div class="w-11 h-6 bg-slate-200 peer-focus:ring-2 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>

                        <button type="submit" 
                                class="w-full py-3 px-4 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-xl font-semibold shadow-lg shadow-indigo-200 hover:shadow-xl hover:shadow-indigo-300 transition-all duration-200 flex items-center justify-center gap-2">
                            <i class="fas fa-save"></i> Update Document
                        </button>
                    </div>
                </div>

                {{-- Quick Actions --}}
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-5 border-b border-slate-100">
                        <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                            <i class="fas fa-bolt text-amber-500"></i>
                            Quick Actions
                        </h2>
                    </div>
                    <div class="p-5 space-y-3">
                        @if(!$document->is_published)
                            <form action="{{ route('admin.legal-documents.publish', $document->id) }}" method="POST">
                                @csrf
                                <button type="submit" 
                                        class="w-full py-2.5 px-4 bg-emerald-50 text-emerald-600 rounded-xl font-medium hover:bg-emerald-100 transition-colors flex items-center justify-center gap-2">
                                    <i class="fas fa-globe"></i> Publish Now
                                </button>
                            </form>
                        @else
                            <form action="{{ route('admin.legal-documents.unpublish', $document->id) }}" method="POST">
                                @csrf
                                <button type="submit" 
                                        class="w-full py-2.5 px-4 bg-amber-50 text-amber-600 rounded-xl font-medium hover:bg-amber-100 transition-colors flex items-center justify-center gap-2">
                                    <i class="fas fa-eye-slash"></i> Unpublish
                                </button>
                            </form>
                        @endif

                        <form action="{{ route('admin.legal-documents.delete', $document->id) }}" method="POST"
                              onsubmit="return confirm('Are you sure you want to delete this document? This action cannot be undone.')">
                            @csrf
                            <button type="submit" 
                                    class="w-full py-2.5 px-4 bg-red-50 text-red-600 rounded-xl font-medium hover:bg-red-100 transition-colors flex items-center justify-center gap-2">
                                <i class="fas fa-trash"></i> Delete Document
                            </button>
                        </form>
                    </div>
                </div>

                {{-- Tips --}}
                <div class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl shadow-lg p-5 text-white">
                    <div class="flex items-center gap-2 mb-3">
                        <i class="fas fa-lightbulb"></i>
                        <h3 class="font-semibold">Pro Tip</h3>
                    </div>
                    <p class="text-sm text-white/90 leading-relaxed">
                        Use the rich text editor to format your content. The exact formatting you create will be displayed in the app.
                    </p>
                </div>
            </div>
        </div>
    </form>
</div>

{{-- Quill.js WYSIWYG Editor --}}
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<style>
    .ql-toolbar.ql-snow {
        border: 1px solid #e2e8f0 !important;
        border-radius: 12px 12px 0 0 !important;
        background: #f8fafc;
    }
    .ql-container.ql-snow {
        border: 1px solid #e2e8f0 !important;
        border-top: none !important;
        border-radius: 0 0 12px 12px !important;
        font-family: inherit;
    }
    .ql-editor {
        min-height: 400px;
        font-size: 15px;
        line-height: 1.7;
        color: #334155;
    }
    .ql-editor.ql-blank::before {
        color: #94a3b8;
        font-style: normal;
    }
</style>
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
    const quill = new Quill('#editor-container', {
        theme: 'snow',
        placeholder: 'Start writing your legal document content here...',
        modules: {
            toolbar: [
                [{ 'header': [2, 3, 4, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['blockquote'],
                ['link'],
                ['clean']
            ]
        }
    });

    const contentInput = document.getElementById('content');
    if (contentInput.value) {
        quill.root.innerHTML = contentInput.value;
    }

    document.querySelector('form').addEventListener('submit', function() {
        contentInput.value = quill.root.innerHTML;
    });

    quill.on('text-change', function() {
        contentInput.value = quill.root.innerHTML;
    });
</script>
@endsection
