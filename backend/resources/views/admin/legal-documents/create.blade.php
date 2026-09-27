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
            <p class="text-slate-500 mt-1 ml-12">New documents are saved as drafts. Publish after review.</p>
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
                                    <option value="{{ $key }}" {{ old('type') === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1.5 text-xs text-slate-500">One document per type — versions are added by editing, not re-creating. Types that already have a document are rejected on save.</p>
                        </div>

                        <div>
                            <label for="slug" class="block text-sm font-semibold text-slate-700 mb-2">Slug *</label>
                            <div class="flex items-center gap-2">
                                <span class="px-3 py-3 bg-slate-200 text-slate-500 text-sm font-medium rounded-l-xl border border-r-0 border-slate-200">/legal/</span>
                                <input type="text" name="slug" id="slug"
                                       class="flex-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-r-xl text-slate-900 font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                                       value="{{ old('slug') }}"
                                       placeholder="e.g., community-guidelines"
                                       pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                                       title="Lowercase letters, numbers and hyphens only"
                                       required>
                            </div>
                            <p class="mt-1.5 text-xs text-slate-500">Lowercase letters, numbers and hyphens. This is the public URL — it cannot be changed later.</p>
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
                            <label for="short_description" class="block text-sm font-semibold text-slate-700 mb-2">Short Description</label>
                            <textarea name="short_description" id="short_description" rows="2" maxlength="500"
                                      class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                                      placeholder="One-line summary shown on the Legal Center cards">{{ old('short_description') }}</textarea>
                            <p class="mt-1 text-xs text-slate-500">Shown on the Legal Center overview page (max 500 characters).</p>
                        </div>

                        <div>
                            <label for="reference_url" class="block text-sm font-semibold text-slate-700 mb-2">Reference URL (optional)</label>
                            <input type="text" name="reference_url" id="reference_url" maxlength="500"
                                   class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                                   value="{{ old('reference_url') }}"
                                   placeholder="https://... | mailto:... | /legal/privacy">
                            <p class="mt-1.5 text-xs text-slate-500">Optional external/reference link shown on the document page. Allowed: https://, http://, mailto:, or a site path starting with a single /. This is <strong>not</strong> this document's own public URL (shown above as the slug).</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="version" class="block text-sm font-semibold text-slate-700 mb-2">Version</label>
                                <input type="text" name="version" id="version"
                                       class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                                       value="{{ old('version', '1.0') }}"
                                       placeholder="e.g., 1.0">
                            </div>
                            <div>
                                <label for="effective_date" class="block text-sm font-semibold text-slate-700 mb-2">Effective Date</label>
                                <input type="date" name="effective_date" id="effective_date"
                                       class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                                       value="{{ old('effective_date') }}">
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
                {{-- Save --}}
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-5 border-b border-slate-100">
                        <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                            <i class="fas fa-gear text-indigo-500"></i>
                            Save
                        </h2>
                    </div>
                    <div class="p-5 space-y-4">
                        <div class="p-3 bg-amber-50 rounded-xl border border-amber-100">
                            <div class="flex items-start gap-2">
                                <i class="fas fa-circle-info text-amber-500 mt-0.5 text-sm"></i>
                                <div>
                                    <p class="font-medium text-amber-800 text-sm">Saved as draft</p>
                                    <p class="text-xs text-amber-700 mt-0.5">The document stays private until you publish it. Placeholder content cannot be published.</p>
                                </div>
                            </div>
                        </div>

                        <button type="submit"
                                class="w-full py-3 px-4 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-xl font-semibold shadow-lg shadow-indigo-200 hover:shadow-xl hover:shadow-indigo-300 transition-all duration-200 flex items-center justify-center gap-2">
                            <i class="fas fa-save"></i> Save Draft
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
                            <li class="flex items-start gap-2 pt-2 border-t border-slate-100 mt-2">
                                <i class="fas fa-link text-indigo-500 mt-0.5"></i>
                                <span><strong>Document Link</strong> button: insert links to other legal documents (works in app & web)</span>
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

    const typeSelect = document.getElementById('type');
    const slugInput = document.getElementById('slug');
    typeSelect.addEventListener('change', function() {
        if (!slugInput.value && this.value) {
            slugInput.value = this.value.replace(/_/g, '-');
        }
    });
</script>
@endsection
