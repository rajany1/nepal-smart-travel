@extends('admin.layout')
@section('title', 'Report Categories')

@section('content')
<div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="px-6 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-4">
        <h3 class="font-semibold text-gray-800">Report Categories</h3>
        <button onclick="openCreateModal()" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition">
            <i class="fas fa-plus mr-1"></i> Add Category
        </button>
    </div>

    <div class="overflow-x-auto">
        <div id="liveTable">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Name</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Slug</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Group</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Icon</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Featured</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Emergency</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Active</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Options</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Fields</th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($categories as $category)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <p class="text-sm font-medium text-gray-900">{{ $category->name }}</p>
                            @if($category->name_ne)
                            <p class="text-xs text-primary-600">{{ $category->name_ne }}</p>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600 font-mono">{{ $category->slug }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $category->group?->name ?? '—' }}</td>
                        <td class="px-6 py-4">
                            @if($category->icon)
                            <i class="material-icons text-primary-600 text-2xl">{{ $category->icon }}</i>
                            @else
                            <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $category->is_featured ? 'bg-primary-100 text-primary-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $category->is_featured ? 'Yes' : 'No' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $category->is_emergency ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800' }}">
                                {{ $category->is_emergency ? 'Yes' : 'No' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $category->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $category->is_active ? 'Yes' : 'No' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $category->options_count ?? $category->options->count() }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $category->fields_count ?? $category->fields->count() }}</td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button onclick="openEditModal({{ $category->id }}, '{{ addslashes($category->name) }}', '{{ $category->slug }}', '{{ addslashes($category->name_ne ?? '') }}', '{{ addslashes($category->description ?? '') }}', '{{ addslashes($category->description_ne ?? '') }}', '{{ $category->icon }}', '{{ $category->category_group_id ?? '' }}', {{ $category->sort_order }}, {{ $category->is_active ? 'true' : 'false' }}, {{ $category->is_featured ? 'true' : 'false' }}, {{ $category->is_emergency ? 'true' : 'false' }})" class="px-3 py-1.5 text-xs font-medium bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" action="{{ route('admin.report-categories.delete', $category->id) }}" class="inline" onsubmit="return confirm('Delete this category?');">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <button onclick="manageOptions({{ $category->id }}, '{{ addslashes($category->name) }}')" class="px-3 py-1.5 text-xs font-medium bg-purple-50 text-purple-600 rounded-lg hover:bg-purple-100 transition">
                                    <i class="fas fa-list"></i>
                                </button>
                                <button onclick="manageFields({{ $category->id }}, '{{ addslashes($category->name) }}')" class="px-3 py-1.5 text-xs font-medium bg-amber-50 text-amber-600 rounded-lg hover:bg-amber-100 transition">
                                    <i class="fas fa-cogs"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-folder-open text-3xl text-gray-300 mb-3 block"></i>
                            No categories found
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($categories->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">{{ $categories->links() }}</div>
        @endif
    </div>
</div>

<!-- Create/Edit Category Modal -->
<div id="categoryModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeCategoryModal()"></div>
        <div class="relative w-full max-w-2xl bg-white rounded-xl shadow-xl">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 id="categoryModalTitle" class="font-semibold text-gray-800">Add Category</h3>
                <button onclick="closeCategoryModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
            </div>
            <form method="POST" id="categoryForm" class="p-6 space-y-4">
                @csrf
                <input type="hidden" name="id" id="categoryId">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name (English) <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="cat_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Slug <span class="text-red-500">*</span></label>
                        <input type="text" name="slug" id="cat_slug" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name (Nepali)</label>
                        <input type="text" name="name_ne" id="cat_name_ne" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Icon</label>
                        <input type="text" name="icon" id="cat_icon" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500" placeholder="material icon name">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description (English)</label>
                        <textarea name="description" id="cat_description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description (Nepali)</label>
                        <textarea name="description_ne" id="cat_description_ne" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Group</label>
                        <select name="category_group_id" id="cat_group_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="">— None —</option>
                            @foreach($groups as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                        <input type="number" name="sort_order" id="cat_sort_order" value="0" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_active" id="cat_is_active" value="1" checked class="rounded border-gray-300 text-primary-600">
                            <span class="text-sm font-medium text-gray-700">Active</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_featured" id="cat_is_featured" value="1" class="rounded border-gray-300 text-primary-600">
                            <span class="text-sm font-medium text-gray-700">Featured</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" name="is_emergency" id="cat_is_emergency" value="1" class="rounded border-gray-300 text-primary-600">
                            <span class="text-sm font-medium text-gray-700">Emergency</span>
                        </label>
                    </div>
                </div>
                <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
                    <button type="button" onclick="closeCategoryModal()" class="px-4 py-2 text-sm font-medium bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition" id="categorySubmitBtn">
                        <i class="fas fa-save mr-1"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Options Modal -->
<div id="optionsModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeOptionsModal()"></div>
        <div class="relative w-full max-w-4xl bg-white rounded-xl shadow-xl max-h-[80vh] flex flex-col">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 id="optionsModalTitle" class="font-semibold text-gray-800">Category Options</h3>
                <button onclick="closeOptionsModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
            </div>
            <div class="p-4 border-b border-gray-100">
                <button onclick="openOptionCreateModal()" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition">
                    <i class="fas fa-plus mr-1"></i> Add Option
                </button>
            </div>
            <div class="flex-1 overflow-y-auto p-4">
                <table class="w-full" id="optionsTable">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase">Slug</th>
                            <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase">Icon</th>
                            <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase">Severity</th>
                            <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase">Requires Photo</th>
                            <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase">Requires Location</th>
                            <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase">Active</th>
                            <th class="text-right px-4 py-2 text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100" id="optionsTbody">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Fields Modal -->
<div id="fieldsModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeFieldsModal()"></div>
        <div class="relative w-full max-w-4xl bg-white rounded-xl shadow-xl max-h-[80vh] flex flex-col">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 id="fieldsModalTitle" class="font-semibold text-gray-800">Category Fields</h3>
                <button onclick="closeFieldsModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
            </div>
            <div class="p-4 border-b border-gray-100">
                <button onclick="openFieldCreateModal()" class="px-4 py-2 bg-primary-600 text-white rounded-lg text-sm font-medium hover:bg-primary-700 transition">
                    <i class="fas fa-plus mr-1"></i> Add Field
                </button>
            </div>
            <div class="flex-1 overflow-y-auto p-4">
                <table class="w-full" id="fieldsTable">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase">Label</th>
                            <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase">Type</th>
                            <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase">Required</th>
                            <th class="text-left px-4 py-2 text-xs font-medium text-gray-500 uppercase">Active</th>
                            <th class="text-right px-4 py-2 text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100" id="fieldsTbody">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Option Form Modal (create / edit) -->
<div id="optionFormModal" class="fixed inset-0 z-[60] hidden overflow-y-auto">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeOptionForm()"></div>
        <div class="relative w-full max-w-2xl bg-white rounded-xl shadow-xl">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 id="optionFormTitle" class="font-semibold text-gray-800">Add Option</h3>
                <button onclick="closeOptionForm()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
            </div>
            <form method="POST" id="optionForm" class="p-6 space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name (English) <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="opt_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Slug <span class="text-red-500">*</span></label>
                        <input type="text" name="slug" id="opt_slug" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name (Nepali)</label>
                        <input type="text" name="name_ne" id="opt_name_ne" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Icon</label>
                        <input type="text" name="icon" id="opt_icon" placeholder="material icon name" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Severity (card outline)</label>
                        <select name="severity" id="opt_severity" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="low">Low (green)</option>
                            <option value="medium" selected>Medium (blue)</option>
                            <option value="high">High (red)</option>
                            <option value="critical">Critical (red)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                        <input type="number" name="sort_order" id="opt_sort_order" value="0" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description (English)</label>
                        <textarea name="description" id="opt_description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description (Nepali)</label>
                        <textarea name="description_ne" id="opt_description_ne" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"></textarea>
                    </div>
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" id="opt_is_active" value="1" class="rounded border-gray-300 text-primary-600">
                            <span class="text-sm font-medium text-gray-700">Active</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="requires_photo" value="0">
                            <input type="checkbox" name="requires_photo" id="opt_requires_photo" value="1" class="rounded border-gray-300 text-primary-600">
                            <span class="text-sm font-medium text-gray-700">Requires Photo</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="requires_location" value="0">
                            <input type="checkbox" name="requires_location" id="opt_requires_location" value="1" class="rounded border-gray-300 text-primary-600">
                            <span class="text-sm font-medium text-gray-700">Requires Location</span>
                        </label>
                    </div>
                </div>
                <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
                    <button type="button" onclick="closeOptionForm()" class="px-4 py-2 text-sm font-medium bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition">
                        <i class="fas fa-save mr-1"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Field Form Modal (create / edit) -->
<div id="fieldFormModal" class="fixed inset-0 z-[60] hidden overflow-y-auto">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeFieldForm()"></div>
        <div class="relative w-full max-w-2xl bg-white rounded-xl shadow-xl">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 id="fieldFormTitle" class="font-semibold text-gray-800">Add Field</h3>
                <button onclick="closeFieldForm()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-xl"></i></button>
            </div>
            <form method="POST" id="fieldForm" class="p-6 space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name (key) <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="fld_name" required placeholder="e.g. road_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Label (English) <span class="text-red-500">*</span></label>
                        <input type="text" name="label" id="fld_label" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Label (Nepali)</label>
                        <input type="text" name="label_ne" id="fld_label_ne" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type <span class="text-red-500">*</span></label>
                        <select name="type" id="fld_type" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option value="text">Text</option>
                            <option value="textarea">Textarea</option>
                            <option value="number">Number</option>
                            <option value="single_select">Single Select</option>
                            <option value="multi_select">Multi Select</option>
                            <option value="yes_no">Yes / No</option>
                            <option value="photo">Photo</option>
                            <option value="location">Location</option>
                            <option value="severity">Severity</option>
                            <option value="date">Date</option>
                            <option value="time">Time</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Placeholder (English)</label>
                        <input type="text" name="placeholder" id="fld_placeholder" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Placeholder (Nepali)</label>
                        <input type="text" name="placeholder_ne" id="fld_placeholder_ne" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Select Options (one per line, for select fields)</label>
                        <textarea name="options_text" id="fld_options_text" rows="3" placeholder="Asphalt&#10;Gravel&#10;Dirt" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500"></textarea>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Help Text (English)</label>
                        <input type="text" name="help_text" id="fld_help_text" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Help Text (Nepali)</label>
                        <input type="text" name="help_text_ne" id="fld_help_text_ne" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sort Order</label>
                        <input type="number" name="sort_order" id="fld_sort_order" value="0" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                    </div>
                    <div class="flex items-center gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="required" value="0">
                            <input type="checkbox" name="required" id="fld_required" value="1" class="rounded border-gray-300 text-primary-600">
                            <span class="text-sm font-medium text-gray-700">Required</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" id="fld_is_active" value="1" class="rounded border-gray-300 text-primary-600">
                            <span class="text-sm font-medium text-gray-700">Active</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="show_in_preview" value="0">
                            <input type="checkbox" name="show_in_preview" id="fld_show_in_preview" value="1" class="rounded border-gray-300 text-primary-600">
                            <span class="text-sm font-medium text-gray-700">In Preview</span>
                        </label>
                    </div>
                </div>
                <div class="pt-4 border-t border-gray-100 flex justify-end gap-3">
                    <button type="button" onclick="closeFieldForm()" class="px-4 py-2 text-sm font-medium bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition">
                        <i class="fas fa-save mr-1"></i> Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentCategoryId = null;
let optionsById = {};
let fieldsById = {};

const ROUTES = {
    optionsList: @json(route('admin.report-categories.options.list', '__ID__')),
    optionCreate: @json(route('admin.report-categories.options.create', '__ID__')),
    optionUpdate: @json(route('admin.report-categories.options.update', '__ID__')),
    optionDelete: @json(route('admin.report-categories.options.delete', '__ID__')),
    fieldsList: @json(route('admin.report-categories.fields.list', '__ID__')),
    fieldCreate: @json(route('admin.report-categories.fields.create', '__ID__')),
    fieldUpdate: @json(route('admin.report-categories.fields.update', '__ID__')),
    fieldDelete: @json(route('admin.report-categories.fields.delete', '__ID__')),
};

function url(tpl, id) { return tpl.replace('__ID__', id); }

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
}

function severityBadge(sev) {
    const s = sev || 'medium';
    const map = {
        low: 'bg-green-100 text-green-800',
        medium: 'bg-blue-100 text-blue-800',
        high: 'bg-red-100 text-red-700',
        critical: 'bg-red-600 text-white',
    };
    return `<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${map[s] || map.medium}">${esc(s)}</span>`;
}

function yesNo(v) {
    return v
        ? '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Yes</span>'
        : '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">No</span>';
}

function postDelete(routeUrl, label) {
    if (!confirm('Delete ' + label + '?')) return;
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = routeUrl;
    const token = document.createElement('input');
    token.type = 'hidden';
    token.name = '_token';
    token.value = document.querySelector('meta[name="csrf-token"]').content;
    form.appendChild(token);
    document.body.appendChild(form);
    form.submit();
}

function slugify(s) {
    return String(s).toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
}

// ===== Categories =====

function openCreateModal() {
    document.getElementById('categoryModalTitle').textContent = 'Add Category';
    document.getElementById('categoryForm').reset();
    document.getElementById('categoryId').value = '';
    document.getElementById('cat_is_active').checked = true;
    document.getElementById('cat_is_featured').checked = false;
    document.getElementById('cat_is_emergency').checked = false;
    document.getElementById('categoryForm').action = '{{ route("admin.report-categories.create") }}';
    document.getElementById('categorySubmitBtn').innerHTML = '<i class="fas fa-save mr-1"></i> Save';
    document.getElementById('categoryModal').classList.remove('hidden');
}

function openEditModal(id, name, slug, name_ne, description, description_ne, icon, group_id, sort_order, is_active, is_featured, is_emergency) {
    document.getElementById('categoryModalTitle').textContent = 'Edit Category';
    document.getElementById('categoryId').value = id;
    document.getElementById('cat_name').value = name;
    document.getElementById('cat_slug').value = slug;
    document.getElementById('cat_name_ne').value = name_ne;
    document.getElementById('cat_description').value = description;
    document.getElementById('cat_description_ne').value = description_ne;
    document.getElementById('cat_icon').value = icon;
    document.getElementById('cat_group_id').value = group_id;
    document.getElementById('cat_sort_order').value = sort_order;
    document.getElementById('cat_is_active').checked = is_active;
    document.getElementById('cat_is_featured').checked = is_featured;
    document.getElementById('cat_is_emergency').checked = is_emergency;
    document.getElementById('categoryForm').action = '{{ route("admin.report-categories.update", ":id") }}'.replace(':id', id);
    document.getElementById('categorySubmitBtn').innerHTML = '<i class="fas fa-save mr-1"></i> Update';
    document.getElementById('categoryModal').classList.remove('hidden');
}

function closeCategoryModal() {
    document.getElementById('categoryModal').classList.add('hidden');
}

// ===== Options =====

function manageOptions(categoryId, categoryName) {
    currentCategoryId = categoryId;
    document.getElementById('optionsModalTitle').textContent = 'Options for ' + categoryName;
    document.getElementById('optionsTbody').innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">Loading...</td></tr>';
    document.getElementById('optionsModal').classList.remove('hidden');
    fetchOptions();
}

function fetchOptions() {
    const tbody = document.getElementById('optionsTbody');
    fetch(url(ROUTES.optionsList, currentCategoryId), { headers: { 'Accept': 'application/json' } })
        .then(r => { if (!r.ok) throw new Error('load failed'); return r.json(); })
        .then(res => {
            const rows = res.data || [];
            optionsById = {};
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">No options yet</td></tr>';
                return;
            }
            rows.forEach(o => { optionsById[o.id] = o; });
            tbody.innerHTML = rows.map(o => `
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <p class="text-sm font-medium text-gray-900">${esc(o.name)}</p>
                        ${o.name_ne ? `<p class="text-xs text-primary-600">${esc(o.name_ne)}</p>` : ''}
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600 font-mono">${esc(o.slug)}</td>
                    <td class="px-4 py-3">${o.icon ? `<i class="material-icons text-primary-600">${esc(o.icon)}</i>` : '<span class="text-gray-400">&mdash;</span>'}</td>
                    <td class="px-4 py-3">${severityBadge(o.severity)}</td>
                    <td class="px-4 py-3">${yesNo(o.requires_photo)}</td>
                    <td class="px-4 py-3">${yesNo(o.requires_location)}</td>
                    <td class="px-4 py-3">${yesNo(o.is_active)}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button onclick="openOptionEditModal(${o.id})" class="px-3 py-1.5 text-xs font-medium bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition"><i class="fas fa-edit"></i></button>
                            <button onclick="deleteOption(${o.id})" class="px-3 py-1.5 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>`).join('');
        })
        .catch(() => {
            tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-8 text-center text-red-500">Failed to load options</td></tr>';
        });
}

function deleteOption(id) {
    const o = optionsById[id];
    postDelete(url(ROUTES.optionDelete, id), 'option "' + (o ? o.name : id) + '"');
}

function closeOptionsModal() {
    document.getElementById('optionsModal').classList.add('hidden');
}

function openOptionCreateModal() {
    if (!currentCategoryId) return;
    const form = document.getElementById('optionForm');
    form.reset();
    document.getElementById('optionFormTitle').textContent = 'Add Option';
    document.getElementById('opt_severity').value = 'medium';
    document.getElementById('opt_sort_order').value = 0;
    document.getElementById('opt_is_active').checked = true;
    form.action = url(ROUTES.optionCreate, currentCategoryId);
    document.getElementById('optionFormModal').classList.remove('hidden');
}

function openOptionEditModal(id) {
    const o = optionsById[id];
    if (!o) return;
    const form = document.getElementById('optionForm');
    form.reset();
    document.getElementById('optionFormTitle').textContent = 'Edit Option';
    document.getElementById('opt_name').value = o.name || '';
    document.getElementById('opt_slug').value = o.slug || '';
    document.getElementById('opt_name_ne').value = o.name_ne || '';
    document.getElementById('opt_icon').value = o.icon || '';
    document.getElementById('opt_severity').value = o.severity || 'medium';
    document.getElementById('opt_sort_order').value = o.sort_order || 0;
    document.getElementById('opt_description').value = o.description || '';
    document.getElementById('opt_description_ne').value = o.description_ne || '';
    document.getElementById('opt_is_active').checked = !!o.is_active;
    document.getElementById('opt_requires_photo').checked = !!o.requires_photo;
    document.getElementById('opt_requires_location').checked = !!o.requires_location;
    form.action = url(ROUTES.optionUpdate, id);
    document.getElementById('optionFormModal').classList.remove('hidden');
}

function closeOptionForm() {
    document.getElementById('optionFormModal').classList.add('hidden');
}

// ===== Fields =====

function manageFields(categoryId, categoryName) {
    currentCategoryId = categoryId;
    document.getElementById('fieldsModalTitle').textContent = 'Fields for ' + categoryName;
    document.getElementById('fieldsTbody').innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Loading...</td></tr>';
    document.getElementById('fieldsModal').classList.remove('hidden');
    fetchFields();
}

function fetchFields() {
    const tbody = document.getElementById('fieldsTbody');
    fetch(url(ROUTES.fieldsList, currentCategoryId), { headers: { 'Accept': 'application/json' } })
        .then(r => { if (!r.ok) throw new Error('load failed'); return r.json(); })
        .then(res => {
            const rows = res.data || [];
            fieldsById = {};
            if (!rows.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No fields yet</td></tr>';
                return;
            }
            rows.forEach(f => { fieldsById[f.id] = f; });
            tbody.innerHTML = rows.map(f => `
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <p class="text-sm font-medium text-gray-900 font-mono">${esc(f.name)}</p>
                        ${f.label_ne ? `<p class="text-xs text-primary-600">${esc(f.label_ne)}</p>` : ''}
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">${esc(f.label)}</td>
                    <td class="px-4 py-3"><span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">${esc(f.type)}</span></td>
                    <td class="px-4 py-3">${yesNo(f.required)}</td>
                    <td class="px-4 py-3">${yesNo(f.is_active)}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <button onclick="openFieldEditModal(${f.id})" class="px-3 py-1.5 text-xs font-medium bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100 transition"><i class="fas fa-edit"></i></button>
                            <button onclick="deleteField(${f.id})" class="px-3 py-1.5 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition"><i class="fas fa-trash"></i></button>
                        </div>
                    </td>
                </tr>`).join('');
        })
        .catch(() => {
            tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-red-500">Failed to load fields</td></tr>';
        });
}

function deleteField(id) {
    const f = fieldsById[id];
    postDelete(url(ROUTES.fieldDelete, id), 'field "' + (f ? f.name : id) + '"');
}

function closeFieldsModal() {
    document.getElementById('fieldsModal').classList.add('hidden');
}

function openFieldCreateModal() {
    if (!currentCategoryId) return;
    const form = document.getElementById('fieldForm');
    form.reset();
    document.getElementById('fieldFormTitle').textContent = 'Add Field';
    document.getElementById('fld_type').value = 'text';
    document.getElementById('fld_sort_order').value = 0;
    document.getElementById('fld_is_active').checked = true;
    document.getElementById('fld_show_in_preview').checked = true;
    form.action = url(ROUTES.fieldCreate, currentCategoryId);
    document.getElementById('fieldFormModal').classList.remove('hidden');
}

function openFieldEditModal(id) {
    const f = fieldsById[id];
    if (!f) return;
    const form = document.getElementById('fieldForm');
    form.reset();
    document.getElementById('fieldFormTitle').textContent = 'Edit Field';
    document.getElementById('fld_name').value = f.name || '';
    document.getElementById('fld_label').value = f.label || '';
    document.getElementById('fld_label_ne').value = f.label_ne || '';
    document.getElementById('fld_type').value = f.type || 'text';
    document.getElementById('fld_placeholder').value = f.placeholder || '';
    document.getElementById('fld_placeholder_ne').value = f.placeholder_ne || '';
    document.getElementById('fld_help_text').value = f.help_text || '';
    document.getElementById('fld_help_text_ne').value = f.help_text_ne || '';
    document.getElementById('fld_sort_order').value = f.sort_order || 0;
    document.getElementById('fld_required').checked = !!f.required;
    document.getElementById('fld_is_active').checked = !!f.is_active;
    document.getElementById('fld_show_in_preview').checked = !!f.show_in_preview;
    const opts = Array.isArray(f.options) ? f.options : [];
    document.getElementById('fld_options_text').value = opts
        .map(o => o.label || o.value || '')
        .filter(Boolean)
        .join('\n');
    form.action = url(ROUTES.fieldUpdate, id);
    document.getElementById('fieldFormModal').classList.remove('hidden');
}

function closeFieldForm() {
    document.getElementById('fieldFormModal').classList.add('hidden');
}

// Convert the newline options list into options[i][value]/options[i][label]
// pairs on submit. Empty list sends options_clear so the backend can clear.
document.getElementById('fieldForm').addEventListener('submit', function () {
    this.querySelectorAll('input[name^="options["], input[name="options_clear"]').forEach(el => el.remove());
    const lines = document.getElementById('fld_options_text').value
        .split('\n').map(s => s.trim()).filter(Boolean);
    if (!lines.length) {
        const clear = document.createElement('input');
        clear.type = 'hidden';
        clear.name = 'options_clear';
        clear.value = '1';
        this.appendChild(clear);
        return;
    }
    lines.forEach((line, i) => {
        const v = document.createElement('input');
        v.type = 'hidden';
        v.name = 'options[' + i + '][value]';
        v.value = slugify(line) || ('opt-' + i);
        const l = document.createElement('input');
        l.type = 'hidden';
        l.name = 'options[' + i + '][label]';
        l.value = line;
        this.appendChild(v);
        this.appendChild(l);
    });
});
</script>
@endsection