@extends('admin.layout')

@section('title', 'Edit Document Type')

@section('content')
<div class="min-h-screen bg-slate-50 p-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-3">
                <div class="p-2.5 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl shadow-lg">
                    <i class="fas fa-pen text-white text-lg"></i>
                </div>
                Edit Document Type
            </h1>
            <p class="text-slate-500 mt-1 ml-12">Update <strong>{{ $type->label }}</strong> document type.</p>
        </div>
        <a href="{{ route('admin.legal-document-types.index') }}" 
           class="mt-4 sm:mt-0 inline-flex items-center gap-2 px-5 py-2.5 bg-white text-slate-700 rounded-xl font-medium shadow-sm hover:shadow-md border border-slate-200 transition-all">
            <i class="fas fa-arrow-left text-sm"></i> Back to Types
        </a>
    </div>

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

    <form action="{{ route('admin.legal-document-types.update', $type->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="max-w-2xl">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-5 border-b border-slate-100">
                    <h2 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <i class="fas fa-info-circle text-indigo-500"></i>
                        Type Details
                    </h2>
                </div>
                <div class="p-5 space-y-5">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Slug</label>
                        <div class="px-4 py-3 bg-slate-100 border border-slate-200 rounded-xl text-slate-500 font-mono">
                            {{ $type->slug }}
                        </div>
                        <p class="mt-1.5 text-xs text-slate-500">Slug cannot be changed after creation.</p>
                    </div>

                    <div>
                        <label for="label" class="block text-sm font-semibold text-slate-700 mb-2">Label *</label>
                        <input type="text" name="label" id="label" 
                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                               value="{{ old('label', $type->label) }}" 
                               required>
                    </div>

                    <div>
                        <label for="sort_order" class="block text-sm font-semibold text-slate-700 mb-2">Sort Order</label>
                        <input type="number" name="sort_order" id="sort_order" 
                               class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 font-medium focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition-all"
                               value="{{ old('sort_order', $type->sort_order) }}" 
                               min="0">
                    </div>

                    <div class="flex items-center justify-between p-3 bg-slate-50 rounded-xl">
                        <div>
                            <p class="font-medium text-slate-900">Active</p>
                            <p class="text-xs text-slate-500">Allow documents to be created with this type</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" id="is_active" 
                                   class="sr-only peer" 
                                   value="1" {{ old('is_active', $type->is_active) ? 'checked' : '' }}>
                            <div class="w-11 h-6 bg-slate-200 peer-focus:ring-2 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                        </label>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-4">
                <button type="submit" 
                        class="px-6 py-3 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-xl font-semibold shadow-lg shadow-indigo-200 hover:shadow-xl hover:shadow-indigo-300 transition-all duration-200 flex items-center gap-2">
                    <i class="fas fa-save"></i> Update Type
                </button>
                <a href="{{ route('admin.legal-document-types.index') }}" 
                   class="px-6 py-3 bg-white text-slate-700 rounded-xl font-medium border border-slate-200 hover:bg-slate-50 transition-colors">
                    Cancel
                </a>
            </div>
        </div>
    </form>
</div>
@endsection
