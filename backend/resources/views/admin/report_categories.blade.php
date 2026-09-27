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

<script>
let currentCategoryId = null;

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

function manageOptions(categoryId, categoryName) {
    currentCategoryId = categoryId;
    document.getElementById('optionsModalTitle').textContent = 'Options for ' + categoryName;
    document.getElementById('optionsTbody').innerHTML = '<tr><td colspan="7" class="px-4 py-8 text-center text-gray-500">Loading...</td></tr>';
    document.getElementById('optionsModal').classList.remove('hidden');
    fetchOptions();
}

function fetchOptions() {
    fetch('{{ route("admin.report-categories.show", ":id") }}'.replace(':id', currentCategoryId) + '/options')
        .then(r => r.json())
        .then(data => {
            // For now, we'll load from the page data
            // In production, add a proper API endpoint
            location.reload(); // Temporary - replace with proper AJAX
        });
}

function closeOptionsModal() {
    document.getElementById('optionsModal').classList.add('hidden');
}

function manageFields(categoryId, categoryName) {
    currentCategoryId = categoryId;
    document.getElementById('fieldsModalTitle').textContent = 'Fields for ' + categoryName;
    document.getElementById('fieldsTbody').innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Loading...</td></tr>';
    document.getElementById('fieldsModal').classList.remove('hidden');
    fetchFields();
}

function fetchFields() {
    location.reload(); // Temporary
}

function closeFieldsModal() {
    document.getElementById('fieldsModal').classList.add('hidden');
}

function openOptionCreateModal() {
    alert('Option creation - implement AJAX form');
}

function openFieldCreateModal() {
    alert('Field creation - implement AJAX form');
}
</script>
@endsection