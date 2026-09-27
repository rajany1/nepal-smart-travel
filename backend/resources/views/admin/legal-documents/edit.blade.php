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
            <p class="text-slate-500 mt-1 ml-12">Editing draft of <span class="font-medium text-slate-700">{{ $document->title }}</span></p>
        </div>
        <div class="mt-4 sm:mt-0 flex items-center gap-3">
            <a href="{{ route('admin.legal-documents.versions', $document->id) }}"
               class="inline-flex items-center gap-2 px-4 py-2.5 bg-white text-slate-700 rounded-xl font-medium shadow-sm hover:shadow-md border border-slate-200 transition-all">
                <i class="fas fa-clock-rotate-left text-sm"></i> History ({{ $versionsCount }})
            </a>
            <a href="{{ route('admin.legal-documents.index') }}"
               class="inline-flex items-center gap-2 px-5 py-2.5 bg-white text-slate-700 rounded-xl font-medium shadow-sm hover:shadow-md border border-slate-200 transition-all">
                <i class="fas fa-arrow-left text-sm"></i> Back to Documents
            </a>
        </div>
    </div>

    {{-- Flash messages --}}
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

    {{-- Live version note --}}
    @if($published)
        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-xl flex items-start gap-3">
            <div class="p-2 bg-blue-100 rounded-lg">
                <i class="fas fa-circle-info text-blue-600"></i>
            </div>
            <div class="flex-1">
                <p class="font-semibold text-blue-800">Version {{ $published->version ?? '1.0' }} is currently live.</p>
                <p class="text-sm text-blue-700 mt-0.5">This draft stays private until you publish it. Publishing will archive the live version and replace it — the old version is kept in history.</p>
            </div>
            <a href="{{ route('web.legal.show', $published->slug ?? $published->type) }}" target="_blank"
               class="text-sm font-medium text-blue-600 hover:text-blue-800 whitespace-nowrap">
                View live <i class="fas fa-arrow-up-right-from-square text-xs"></i>
            </a>
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
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Document Type</label>
                                <div class="w-full px-4 py-3 bg-slate-100 border border-slate-200 rounded-xl text-slate-500 font-medium">
                                    {{ $types[$document->type] ?? $document->type }}
                                </div>
                                <p class="mt-1 text-xs text-slate-400">Type cannot be changed.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Slug</label>
                                <div class="w-full px-4 py-3 bg-slate-100 border border-slate-200 rounded-xl text-slate-500 font-medium truncate">
                                    /legal/{{ $document->slug ?? $document->type }}
                                </div>
                                <p class="mt-1 text-xs text-slate-400">Slug cannot be changed.</p>
                            </div>
                        </div>

                        <div>
                            <label for="title" class="block text-sm font-semibold text-slate-700 mb-2">Title *</label>
                            <input type="text" name="title" id="title"
                                   class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                                   value="{{ old('title', $document->title) }}"
                                   required>
                        </div>

                        <div>
                            <label for="short_description" class="block text-sm font-semibold text-slate-700 mb-2">Short Description</label>
                            <textarea name="short_description" id="short_description" rows="2" maxlength="500"
                                      class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                                      placeholder="One-line summary shown on the Legal Center cards">{{ old('short_description', $document->short_description) }}</textarea>
                            <p class="mt-1 text-xs text-slate-500">Shown on the Legal Center overview page (max 500 characters).</p>
                        </div>

                        <div>
                            <label for="reference_url" class="block text-sm font-semibold text-slate-700 mb-2">Reference URL (optional)</label>
                            <input type="text" name="reference_url" id="reference_url" maxlength="500"
                                   class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                                   value="{{ old('reference_url', $document->reference_url) }}"
                                   placeholder="https://... | mailto:... | /legal/privacy">
                            <p class="mt-1 text-xs text-slate-500">Optional external/reference link shown on the document page. Allowed: https://, http://, mailto:, or a site path starting with a single /. Leave blank to remove.</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="version" class="block text-sm font-semibold text-slate-700 mb-2">Version</label>
                                <input type="text" name="version" id="version"
                                       class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                                       value="{{ old('version', $document->version) }}"
                                       placeholder="e.g., 1.1">
                                <p class="mt-1 text-xs text-slate-500">Must be unique for this document type.</p>
                            </div>
                            <div>
                                <label for="effective_date" class="block text-sm font-semibold text-slate-700 mb-2">Effective Date</label>
                                <input type="date" name="effective_date" id="effective_date"
                                       class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                                       value="{{ old('effective_date', $document->effective_date?->format('Y-m-d')) }}">
                            </div>
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
                        <p class="text-sm text-slate-500 mt-1">Edit the document content. "Save Draft" keeps changes private; "Publish" saves them and makes them live immediately.</p>
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
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-semibold">
                                <span class="w-1.5 h-1.5 bg-amber-500 rounded-full"></span> Draft
                            </span>
                        </div>

                        <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                            <span class="text-sm font-medium text-slate-600">Public URL</span>
                            <span class="text-sm text-slate-900 truncate max-w-[140px]">/legal/{{ $document->slug ?? $document->type }}</span>
                        </div>

                        <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                            <span class="text-sm font-medium text-slate-600">Created</span>
                            <span class="text-sm text-slate-900">{{ $document->created_at?->diffForHumans() ?? '-' }}</span>
                        </div>

                        <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                            <span class="text-sm font-medium text-slate-600">Updated</span>
                            <span class="text-sm text-slate-900">{{ $document->updated_at?->diffForHumans() ?? '-' }}</span>
                        </div>

                        <button type="submit" name="action" value="save"
                                class="w-full py-3 px-4 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-xl font-semibold shadow-lg shadow-indigo-200 hover:shadow-xl hover:shadow-indigo-300 transition-all duration-200 flex items-center justify-center gap-2">
                            <i class="fas fa-save"></i> Save Draft
                        </button>

                        <a href="{{ route('admin.legal-documents.preview', $document->id) }}" target="_blank"
                           class="w-full py-2.5 px-4 bg-slate-100 text-slate-700 rounded-xl font-medium hover:bg-slate-200 transition-colors flex items-center justify-center gap-2">
                            <i class="fas fa-eye"></i> Preview
                        </a>
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
                        {{-- Same form as Save Draft (nested <form> tags are invalid and get
                             dropped by browsers, which silently turned Publish into Save).
                             action=publish saves the current edits, then publishes them. --}}
                        <button type="submit" name="action" value="publish"
                                onclick="return confirm('Publish version {{ $document->version }}?@if($published) The currently live version {{ $published->version }} will be archived (kept in history).@endif')"
                                class="w-full py-2.5 px-4 bg-emerald-50 text-emerald-600 rounded-xl font-medium hover:bg-emerald-100 transition-colors flex items-center justify-center gap-2">
                            <i class="fas fa-globe"></i> {{ $published ? 'Publish (replaces live v' . $published->version . ')' : 'Publish Now' }}
                        </button>

                        <a href="{{ route('admin.legal-documents.versions', $document->id) }}"
                           class="w-full py-2.5 px-4 bg-purple-50 text-purple-600 rounded-xl font-medium hover:bg-purple-100 transition-colors flex items-center justify-center gap-2">
                            <i class="fas fa-clock-rotate-left"></i> Version History
                        </a>

                        {{-- Outside the main form — associated via the form="..." attribute
                             on the delete button below. --}}
                        <button type="submit" form="delete-draft-form"
                                onclick="return confirm('Delete this draft? This cannot be undone.')"
                                class="w-full py-2.5 px-4 bg-red-50 text-red-600 rounded-xl font-medium hover:bg-red-100 transition-colors flex items-center justify-center gap-2">
                            <i class="fas fa-trash"></i> Delete Draft
                        </button>
                    </div>
                </div>

                {{-- Tips --}}
                <div class="bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl shadow-lg p-5 text-white">
                    <div class="flex items-center gap-2 mb-3">
                        <i class="fas fa-lightbulb"></i>
                        <h3 class="font-semibold">Pro Tip</h3>
                    </div>
                    <p class="text-sm text-white/90 leading-relaxed mb-3">
                        Use the rich text editor to format your content. The exact formatting you create will be displayed in the app.
                    </p>
                    <p class="text-sm text-white/90 leading-relaxed">
                        <i class="fas fa-link mr-1"></i> Use the <strong>Document Link</strong> button in the toolbar to insert cross-links to other legal documents. These links work both on the website and in the app.
                    </p>
                </div>
            </div>
        </div>
    </form>

    {{-- Standalone target for the Delete button (form="delete-draft-form") --}}
    <form id="delete-draft-form" action="{{ route('admin.legal-documents.delete', $document->id) }}" method="POST" style="display:none">
        @csrf
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
    .doc-link-menu {
        display: none;
        position: absolute;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        z-index: 9999;
        min-width: 220px;
        padding: 6px;
    }
    .doc-link-menu.show { display: block; }
    .doc-link-menu button {
        display: block;
        width: 100%;
        text-align: left;
        padding: 10px 14px;
        border: none;
        background: none;
        font-size: 14px;
        color: #334155;
        cursor: pointer;
        border-radius: 6px;
        transition: background 0.15s;
    }
    .doc-link-menu button:hover {
        background: #f1f5f9;
        color: #4f46e5;
    }
    .doc-link-menu .menu-label {
        padding: 6px 14px 4px;
        font-size: 11px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
</style>
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
    const docTypes = @json($types);

    const quill = new Quill('#editor-container', {
        theme: 'snow',
        placeholder: 'Start writing your legal document content here...',
        modules: {
            toolbar: {
                container: [
                    [{ 'header': [2, 3, 4, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['blockquote'],
                    ['link'],
                    ['doc-link'],
                    ['clean']
                ],
                handlers: {
                    'doc-link': function() {
                        const btn = this.container.querySelector('.ql-doc-link');
                        let menu = document.getElementById('doc-link-menu');
                        if (!menu) {
                            menu = document.createElement('div');
                            menu.id = 'doc-link-menu';
                            menu.className = 'doc-link-menu';
                            document.body.appendChild(menu);
                        }
                        const label = document.createElement('div');
                        label.className = 'menu-label';
                        label.textContent = 'Link to document';
                        menu.innerHTML = '';
                        menu.appendChild(label);
                        for (const [key, name] of Object.entries(docTypes)) {
                            const b = document.createElement('button');
                            b.type = 'button';
                            b.textContent = name;
                            b.addEventListener('click', function(e) {
                                e.preventDefault();
                                const range = quill.getSelection();
                                const text = range && range.length > 0
                                    ? quill.getText(range.index, range.length)
                                    : name;
                                const index = range ? range.index : quill.getLength() - 1;
                                if (range && range.length > 0) {
                                    quill.removeText(range.index, range.length, Quill.sources.USER);
                                }
                                quill.insertText(index, text, { link: '/legal/' + key }, Quill.sources.USER);
                                quill.setSelection(index + text.length, Quill.sources.SILENT);
                                menu.classList.remove('show');
                            });
                            menu.appendChild(b);
                        }
                        const rect = btn.getBoundingClientRect();
                        menu.style.top = (rect.bottom + window.scrollY + 4) + 'px';
                        menu.style.left = (rect.left + window.scrollX) + 'px';
                        menu.classList.toggle('show');
                    }
                }
            }
        }
    });

    document.addEventListener('click', function(e) {
        const menu = document.getElementById('doc-link-menu');
        if (menu && !menu.contains(e.target) && !e.target.closest('.ql-doc-link')) {
            menu.classList.remove('show');
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
