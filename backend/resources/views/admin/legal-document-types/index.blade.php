@extends('admin.layout')

@section('title', 'Legal Document Types')

@section('content')
<div class="min-h-screen bg-slate-50 p-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-3">
                <div class="p-2.5 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl shadow-lg">
                    <i class="fas fa-tags text-white text-lg"></i>
                </div>
                Document Types
            </h1>
            <p class="text-slate-500 mt-1 ml-12">Manage categories for legal documents.</p>
        </div>
        <a href="{{ route('admin.legal-document-types.create') }}" 
           class="mt-4 sm:mt-0 inline-flex items-center gap-2 px-5 py-2.5 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-xl font-medium shadow-lg shadow-indigo-200 hover:shadow-xl hover:shadow-indigo-300 transition-all duration-200">
            <i class="fas fa-plus text-sm"></i> New Type
        </a>
    </div>

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

    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100">
                        <th class="text-left px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Sort</th>
                        <th class="text-left px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Slug</th>
                        <th class="text-left px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Label</th>
                        <th class="text-left px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="text-left px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Created</th>
                        <th class="text-right px-6 py-4 text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($types as $type)
                        <tr class="hover:bg-slate-50 transition-colors">
                            <td class="px-6 py-4 text-sm text-slate-500 font-mono">{{ $type->sort_order }}</td>
                            <td class="px-6 py-4">
                                <code class="px-2 py-1 bg-slate-100 text-indigo-600 rounded text-xs font-mono">{{ $type->slug }}</code>
                            </td>
                            <td class="px-6 py-4 text-sm font-semibold text-slate-900">{{ $type->label }}</td>
                            <td class="px-6 py-4">
                                @if($type->is_active)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-50 text-emerald-600 text-xs font-semibold rounded-full">
                                        <i class="fas fa-circle text-[6px]"></i> Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-slate-100 text-slate-500 text-xs font-semibold rounded-full">
                                        <i class="fas fa-circle text-[6px]"></i> Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-slate-500">{{ $type->created_at?->diffForHumans() ?? '-' }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('admin.legal-document-types.edit', $type->id) }}" 
                                       class="p-2 bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 transition-colors" title="Edit">
                                        <i class="fas fa-pen text-xs"></i>
                                    </a>
                                    <form action="{{ route('admin.legal-document-types.delete', $type->id) }}" method="POST" 
                                          onsubmit="return confirm('Delete this document type? Documents using this type will not be affected.')">
                                        @csrf
                                        <button type="submit" class="p-2 bg-red-50 text-red-500 rounded-lg hover:bg-red-100 transition-colors" title="Delete">
                                            <i class="fas fa-trash text-xs"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="inline-flex p-4 bg-slate-100 rounded-2xl mb-4">
                                    <i class="fas fa-tags text-4xl text-slate-300"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-slate-700 mb-2">No document types yet</h3>
                                <p class="text-slate-500 mb-6 max-w-md mx-auto">Add document types to organize your legal content.</p>
                                <a href="{{ route('admin.legal-document-types.create') }}" 
                                   class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-xl font-medium shadow-lg">
                                    <i class="fas fa-plus"></i> Add First Type
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
