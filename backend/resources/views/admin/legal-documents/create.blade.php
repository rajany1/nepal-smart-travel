@extends('admin.layout')

@section('title', 'Create Legal Document')

@section('content')
<div class="min-h-screen bg-slate-50 p-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-3">
                <div class="p-2.5 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl shadow-lg">
                    <i class="fas fa-plus-circle text-white text-lg"></i>
                </div>
                Create Legal Document
            </h1>
            <p class="text-slate-500 mt-1 ml-12">Write and publish legal content for your app.</p>
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

    <form action="{{ route('admin.legal-documents.store') }}" method="POST">
        @csrf
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
                            <label for="type" class="block text-sm font-semibold text-slate-700 mb-2">Document Type *</label>
                            <select name="type" id="type" 
                                    class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                                    required>
                                <option value="">Select a type...</option>
                                @foreach($types as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="title" class="block text-sm font-semibold text-slate-700 mb-2">Title *</label>
                            <input type="text" name="title" id="title" 
                                   class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                                   value="{{ old('title') }}" 
                                   placeholder="e.g., Privacy Policy"
                                   required>
                        </div>

                        <div>
                            <label for="version" class="block text-sm font-semibold text-slate-700 mb-2">Version</label>
                            <input type="text" name="version" id="version" 
                                   class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                                   value="{{ old('version', '1.0') }}" 
                                   placeholder="e.g., 1.0">
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
                        <p class="text-sm text-slate-500 mt-1">Write your legal document content using the rich text editor below.</p>
                    </div>
                    <div class="p-5">
                        <div id="editor-container" style="min-height: 450px; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden;"></div>
                        <input type="hidden" name="content" id="content" value="{{ old('content') }}">
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                {{-- Publish Settings --}}
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-5 border-b border-slate-100">
                        <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                            <i class="fas fa-gear text-indigo-500"></i>
                            Publish Settings
                        </h2>
                    </div>
                    <div class="p-5 space-y-4">
                        <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                            <div>
                                <p class="font-medium text-slate-900">Publish immediately</p>
                                <p class="text-xs text-slate-500">Make this document visible to users</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="hidden" name="is_published" value="0">
                                <input type="checkbox" name="is_published" id="is_published" 
                                       class="sr-only peer" 
                                       value="1" {{ old('is_published') ? 'checked' : '' }}>
                                <div class="w-11 h-6 bg-slate-200 peer-focus:ring-2 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                            </label>
                        </div>

                        <button type="submit" 
                                class="w-full py-3 px-4 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-xl font-semibold shadow-lg shadow-indigo-200 hover:shadow-xl hover:shadow-indigo-300 transition-all duration-200 flex items-center justify-center gap-2">
                            <i class="fas fa-save"></i> Save Document
                        </button>
                    </div>
                </div>

                {{-- Tips --}}
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-5 border-b border-slate-100">
                        <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                            <i class="fas fa-lightbulb text-amber-500"></i>
                            Formatting Tips
                        </h2>
                    </div>
                    <div class="p-5">
                        <ul class="space-y-2.5 text-sm text-slate-600">
                            <li class="flex items-start gap-2">
                                <code class="px-1.5 py-0.5 bg-slate-100 text-indigo-600 rounded text-xs font-mono">&lt;h2&gt;</code>
                                <span>Main headings</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <code class="px-1.5 py-0.5 bg-slate-100 text-indigo-600 rounded text-xs font-mono">&lt;h3&gt;</code>
                                <span>Sub-headings</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <code class="px-1.5 py-0.5 bg-slate-100 text-indigo-600 rounded text-xs font-mono">&lt;ul&gt;</code>
                                <span>Bullet lists</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <code class="px-1.5 py-0.5 bg-slate-100 text-indigo-600 rounded text-xs font-mono">&lt;p&gt;</code>
                                <span>Paragraphs</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <code class="px-1.5 py-0.5 bg-slate-100 text-indigo-600 rounded text-xs font-mono">&lt;strong&gt;</code>
                                <span>Bold text</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <code class="px-1.5 py-0.5 bg-slate-100 text-indigo-600 rounded text-xs font-mono">&lt;em&gt;</code>
                                <span>Italic text</span>
                            </li>
                        </ul>
                    </div>
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
