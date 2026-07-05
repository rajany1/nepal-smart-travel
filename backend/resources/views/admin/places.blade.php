@extends('admin.layout')
@section('title', 'Places Management')

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div class="lg:col-span-2 bg-white rounded-xl shadow-sm border border-gray-100">
        <div class="px-6 py-3 border-b border-gray-100 flex items-center gap-1">
            <a href="{{ route('admin.places') }}" class="px-3 py-1.5 text-sm font-medium rounded-lg {{ request()->routeIs('admin.places') && !request()->routeIs('admin.places.osm') ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                <i class="fas fa-database mr-1"></i> Database
            </a>
            <a href="{{ route('admin.places.osm') }}" class="px-3 py-1.5 text-sm font-medium rounded-lg {{ request()->routeIs('admin.places.osm') ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                <i class="fas fa-globe-asia mr-1"></i> OSM Live (Nepal)
            </a>
        </div>
        <div class="px-6 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <h3 class="font-semibold text-gray-800">All Places</h3>
                <span class="text-xs text-gray-400">({{ $places->total() }})</span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <form method="GET" action="{{ route('admin.places') }}" class="flex items-center gap-2">
                    <input type="hidden" name="category_id" value="{{ $categoryId }}">
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="direction" value="{{ $direction }}">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Search name, address, district..." class="pl-8 pr-3 py-1.5 text-sm border border-gray-300 rounded-lg w-64 focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
                    </div>
                    <button type="submit" class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Search</button>
                    @if($search)
                    <a href="{{ route('admin.places', ['category_id' => $categoryId]) }}" class="px-3 py-1.5 text-sm bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200">Clear</a>
                    @endif
                </form>
                <div class="w-px h-6 bg-gray-200 mx-1"></div>
                <a href="{{ route('admin.places', array_merge(request()->query(), ['category_id' => 'all'])) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $categoryId === 'all' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">All</a>
                @foreach($categories as $cat)
                <a href="{{ route('admin.places', array_merge(request()->query(), ['category_id' => $cat->id])) }}" class="px-3 py-1.5 text-sm rounded-lg {{ $categoryId == $cat->id ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">{{ $cat->name }}</a>
                @endforeach
                <button onclick="openCategoryModal()" class="px-3 py-1.5 text-sm bg-gray-100 text-gray-600 rounded-lg hover:bg-gray-200" title="Manage Categories">
                    <i class="fas fa-cog"></i>
                </button>
            </div>
        </div>
        <div class="overflow-x-auto">
            @php
                $sortUrl = function($column) use ($sort, $direction, $categoryId, $search) {
                    $newDir = $sort === $column && $direction === 'asc' ? 'desc' : 'asc';
                    $params = array_filter(['category_id' => $categoryId, 'search' => $search, 'sort' => $column, 'direction' => $newDir]);
                    return route('admin.places', $params);
                };
                $sortIcon = function($column) use ($sort, $direction) {
                    if ($sort !== $column) return '<i class="fas fa-sort text-gray-300 ml-1 text-[10px]"></i>';
                    $icon = $direction === 'asc' ? 'fa-sort-up' : 'fa-sort-down';
                    return '<i class="fas ' . $icon . ' text-indigo-600 ml-1 text-[10px]"></i>';
                };
            @endphp
            <form id="bulkForm" method="POST" action="">
                @csrf
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase w-10">
                            <input type="checkbox" id="selectAll" onchange="toggleAll(this)" class="rounded border-gray-300">
                        </th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ $sortUrl('id') }}" class="flex items-center gap-1 hover:text-indigo-600">ID {!! $sortIcon('id') !!}</a>
                        </th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ $sortUrl('name') }}" class="flex items-center gap-1 hover:text-indigo-600">Name {!! $sortIcon('name') !!}</a>
                        </th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">Category</th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ $sortUrl('district') }}" class="flex items-center gap-1 hover:text-indigo-600">District {!! $sortIcon('district') !!}</a>
                        </th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ $sortUrl('average_rating') }}" class="flex items-center gap-1 hover:text-indigo-600">Rating {!! $sortIcon('average_rating') !!}</a>
                        </th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ $sortUrl('total_reviews') }}" class="flex items-center gap-1 hover:text-indigo-600">Reviews {!! $sortIcon('total_reviews') !!}</a>
                        </th>
                        <th class="text-left px-6 py-3 text-xs font-medium text-gray-500 uppercase">
                            <a href="{{ $sortUrl('is_featured') }}" class="flex items-center gap-1 hover:text-indigo-600">Featured {!! $sortIcon('is_featured') !!}</a>
                        </th>
                        <th class="text-right px-6 py-3 text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($places as $place)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-4">
                            <input type="checkbox" name="ids[]" value="{{ $place->id }}" class="place-checkbox rounded border-gray-300" onchange="updateBulkBar()">
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500">#{{ $place->id }}</td>
                        <td class="px-6 py-4">
                            <p class="text-sm font-medium text-gray-900 max-w-[200px] truncate">{{ $place->name }}</p>
                            @if($place->address)
                            <p class="text-xs text-gray-500 truncate">{{ $place->address }}</p>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            <span class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded">{{ $place->category?->name ?? 'N/A' }}</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $place->district ?? '-' }}</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-1">
                                <i class="fas fa-star text-yellow-400 text-xs"></i>
                                <span class="text-sm text-gray-700">{{ number_format($place->average_rating ?? 0, 1) }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ $place->total_reviews ?? 0 }}</td>
                        <td class="px-6 py-4">
                            @if($place->is_featured)
                                <span class="text-xs bg-yellow-100 text-yellow-700 px-2 py-1 rounded font-medium"><i class="fas fa-star text-yellow-500 mr-1"></i> Featured</span>
                            @else
                                <span class="text-xs bg-gray-100 text-gray-500 px-2 py-1 rounded">No</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right flex gap-2 justify-end">
                            <button type="button" onclick="openEditModal({{ $place->id }})" class="px-3 py-1.5 text-xs font-medium bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100 transition">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" action="{{ route('admin.places.feature', $place->id) }}" class="inline">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 text-xs font-medium {{ $place->is_featured ? 'bg-yellow-50 text-yellow-600 hover:bg-yellow-100' : 'bg-gray-50 text-gray-600 hover:bg-gray-100' }} rounded-lg transition">
                                    <i class="fas {{ $place->is_featured ? 'fa-star' : 'fa-star-half-alt' }}"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.places.delete', $place->id) }}" class="inline" onsubmit="return confirm('Delete this place?');">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 text-xs font-medium bg-red-50 text-red-600 rounded-lg hover:bg-red-100 transition">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                            <i class="fas fa-map-marker-alt text-3xl text-gray-300 mb-3 block"></i>
                            No places found
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
            </form>
        </div>
        <!-- Bulk Action Bar -->
        <div id="bulkBar" class="hidden px-6 py-3 bg-indigo-50 border-t border-indigo-100 flex items-center justify-between">
            <span class="text-sm text-indigo-700"><span id="selectedCount">0</span> selected</span>
            <div class="flex gap-2">
                <button onclick="openBulkEditModal()" class="px-3 py-1.5 text-xs font-medium bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition"><i class="fas fa-edit mr-1"></i>Bulk Edit</button>
                <button onclick="bulkDelete()" class="px-3 py-1.5 text-xs font-medium bg-red-600 text-white rounded-lg hover:bg-red-700 transition"><i class="fas fa-trash mr-1"></i>Delete Selected</button>
            </div>
        </div>
        @if($places->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">{{ $places->links() }}</div>
        @endif
    </div>

    <!-- Right Column: Create + Tools -->
    <div class="space-y-6">
        <!-- Create Place Form -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800">Add New Place</h3>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('admin.places.create') }}">
                    @csrf
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                            <input type="text" name="name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                            <textarea name="description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                            <select name="category_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <input type="text" name="address" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">District</label>
                            <input type="text" name="district" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Latitude</label>
                                <input type="number" step="0.000001" name="latitude" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Longitude</label>
                                <input type="number" step="0.000001" name="longitude" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            </div>
                        </div>
                        <button type="submit" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">
                            <i class="fas fa-plus mr-1"></i> Add Place
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- OSM Import -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800">Import from OpenStreetMap</h3>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('admin.places.import-osm') }}">
                    @csrf
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">City</label>
                            <select name="city" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                                <option value="">All major Nepali cities</option>
                                <option value="Kathmandu">Kathmandu</option>
                                <option value="Pokhara">Pokhara</option>
                                <option value="Bharatpur">Bharatpur</option>
                                <option value="Lalitpur">Lalitpur</option>
                                <option value="Bhaktapur">Bhaktapur</option>
                                <option value="Birgunj">Birgunj</option>
                                <option value="Janakpur">Janakpur</option>
                                <option value="Butwal">Butwal</option>
                                <option value="Biratnagar">Biratnagar</option>
                                <option value="Dharan">Dharan</option>
                                <option value="Nepalgunj">Nepalgunj</option>
                                <option value="Hetauda">Hetauda</option>
                                <option value="Dhangadhi">Dhangadhi</option>
                                <option value="Lumbini">Lumbini</option>
                                <option value="Jomsom">Jomsom</option>
                                <option value="Namche Bazaar">Namche Bazaar</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Radius (km)</label>
                            <input type="number" name="radius" value="10" min="1" max="50" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        </div>
                        <button type="submit" class="w-full bg-emerald-600 text-white py-2 px-4 rounded-lg text-sm font-medium hover:bg-emerald-700 transition">
                            <i class="fas fa-download mr-1"></i> Import from OSM
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Category Management -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-800">Categories</h3>
            </div>
            <div class="p-6">
                <form method="POST" action="{{ route('admin.places.categories') }}" class="mb-4">
                    @csrf
                    <div class="flex gap-2">
                        <input type="text" name="name" placeholder="New category name" required class="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                        <button type="submit" class="px-3 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700"><i class="fas fa-plus"></i></button>
                    </div>
                </form>
                <div class="space-y-2">
                    @foreach($categories as $cat)
                    <div class="flex items-center justify-between px-3 py-2 bg-gray-50 rounded-lg">
                        <span class="text-sm text-gray-700">{{ $cat->name }}</span>
                        <form method="POST" action="{{ route('admin.places.categories') }}" onsubmit="return confirm('Delete category {{ $cat->name }}?');">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="id" value="{{ $cat->id }}">
                            <button type="submit" class="text-red-500 hover:text-red-700 text-sm"><i class="fas fa-times"></i></button>
                        </form>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Inline Edit Modal -->
<div id="editPlaceModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeEditModal()"></div>
        <div class="relative inline-block bg-white rounded-xl shadow-2xl text-left overflow-hidden transform transition-all sm:max-w-xl w-full">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">Edit Place</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form id="editPlaceForm" method="POST" enctype="multipart/form-data" class="p-6">
                @csrf
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                        <input type="text" name="name" id="edit_name" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea name="description" id="edit_description" rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm"></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select name="category_id" id="edit_category_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">District</label>
                        <input type="text" name="district" id="edit_district" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <input type="text" name="address" id="edit_address" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Latitude</label>
                        <input type="number" step="0.000001" name="latitude" id="edit_latitude" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Longitude</label>
                        <input type="number" step="0.000001" name="longitude" id="edit_longitude" required class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input type="text" name="phone" id="edit_phone" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" id="edit_email" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Website</label>
                        <input type="url" name="website" id="edit_website" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div class="col-span-2">
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="is_verified" id="edit_is_verified" value="1" class="rounded border-gray-300 text-indigo-600">
                                <span class="text-sm text-gray-700">Verified</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="is_active" id="edit_is_active" value="1" class="rounded border-gray-300 text-indigo-600">
                                <span class="text-sm text-gray-700">Active</span>
                            </label>
                        </div>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Add Images</label>
                        <input type="file" name="images[]" multiple accept="image/*" class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    </div>
                    <div class="col-span-2" id="existingImages">
                        <!-- Existing images loaded via JS -->
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Store place data for inline editing
const placesData = <?php echo json_encode($places->map(function($p) {
    return [
        'id' => $p->id,
        'name' => $p->name,
        'description' => $p->description,
        'category_id' => $p->category_id,
        'address' => $p->address,
        'district' => $p->district,
        'latitude' => (float)$p->latitude,
        'longitude' => (float)$p->longitude,
        'phone' => $p->phone,
        'email' => $p->email,
        'website' => $p->website,
        'is_verified' => $p->is_verified,
        'is_active' => $p->is_active,
        'images' => $p->images->map(function($img) {
            return ['id' => $img->id, 'url' => $img->image_url];
        })->toArray(),
    ];
})->values()->toArray()); ?>;

const storageBase = '{{ asset('storage') }}';

function openEditModal(id) {
    const place = placesData.find(p => p.id === id);
    if (!place) return;

    document.getElementById('editPlaceForm').action = '{{ url('admin/places') }}/' + id + '/update';
    document.getElementById('edit_name').value = place.name || '';
    document.getElementById('edit_description').value = place.description || '';
    document.getElementById('edit_category_id').value = place.category_id;
    document.getElementById('edit_address').value = place.address || '';
    document.getElementById('edit_district').value = place.district || '';
    document.getElementById('edit_latitude').value = place.latitude;
    document.getElementById('edit_longitude').value = place.longitude;
    document.getElementById('edit_phone').value = place.phone || '';
    document.getElementById('edit_email').value = place.email || '';
    document.getElementById('edit_website').value = place.website || '';
    document.getElementById('edit_is_verified').checked = !!place.is_verified;
    document.getElementById('edit_is_active').checked = place.is_active !== false;

    // Load existing images
    const container = document.getElementById('existingImages');
    if (place.images && place.images.length > 0) {
        container.innerHTML = '<label class="block text-sm font-medium text-gray-700 mb-1">Existing Images</label><div class="flex flex-wrap gap-2">' +
            place.images.map(img =>
                '<div class="relative group">' +
                    '<img src="' + storageBase + '/' + img.url + '" class="w-20 h-20 object-cover rounded-lg border">' +
                    '<form method="POST" action="{{ url('admin/places') }}/' + id + '/images/delete" class="absolute top-1 right-1 hidden group-hover:block">' +
                        '@csrf' +
                        '<input type="hidden" name="id" value="' + img.id + '">' +
                        '<button type="submit" class="bg-red-500 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs hover:bg-red-600"><i class="fas fa-times"></i></button>' +
                    '</form>' +
                '</div>'
            ).join('') +
        '</div>';
    } else {
        container.innerHTML = '';
    }

    document.getElementById('editPlaceModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editPlaceModal').classList.add('hidden');
}

// ========== BULK ACTIONS ==========

function toggleAll(source) {
    document.querySelectorAll('.place-checkbox').forEach(cb => cb.checked = source.checked);
    updateBulkBar();
}

function updateBulkBar() {
    const checked = document.querySelectorAll('.place-checkbox:checked');
    const bar = document.getElementById('bulkBar');
    const count = document.getElementById('selectedCount');
    if (checked.length > 0) {
        bar.classList.remove('hidden');
        count.textContent = checked.length;
    } else {
        bar.classList.add('hidden');
    }
}

function getSelectedIds() {
    return Array.from(document.querySelectorAll('.place-checkbox:checked')).map(cb => cb.value);
}

function bulkDelete() {
    const ids = getSelectedIds();
    if (ids.length === 0) return;
    if (!confirm('Delete ' + ids.length + ' selected places?')) return;
    const form = document.getElementById('bulkForm');
    form.action = '{{ route('admin.places.bulk-delete') }}';
    form.submit();
}

function openBulkEditModal() {
    const ids = getSelectedIds();
    if (ids.length === 0) return;
    document.getElementById('bulkEditIds').value = ids.join(',');
    // Reset form
    document.getElementById('bulkEditForm').reset();
    document.getElementById('bulkEditModal').classList.remove('hidden');
}

function closeBulkEditModal() {
    document.getElementById('bulkEditModal').classList.add('hidden');
}

// Auto-open edit modal from place detail page
document.addEventListener('DOMContentLoaded', function() {
    const params = new URLSearchParams(window.location.search);
    const editId = params.get('edit_id');
    if (editId) {
        openEditModal(parseInt(editId));
        history.replaceState(null, '', window.location.pathname + window.location.search.replace(/edit_id=\d+&?/, '').replace(/[?&]$/, ''));
    }
});

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') { closeEditModal(); closeBulkEditModal(); }
});
</script>

<!-- Bulk Edit Modal -->
<div id="bulkEditModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeBulkEditModal()"></div>
        <div class="relative inline-block bg-white rounded-xl shadow-2xl text-left overflow-hidden transform transition-all sm:max-w-md w-full">
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800">Bulk Edit Places</h3>
                <button onclick="closeBulkEditModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form id="bulkEditForm" method="POST" action="{{ route('admin.places.bulk-update') }}" class="p-6">
                @csrf
                <input type="hidden" name="ids" id="bulkEditIds" value="">
                <p class="text-sm text-gray-500 mb-4">Changes will apply to all <span id="bulkEditCount">0</span> selected places.</p>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select name="category_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="">— No change —</option>
                            @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_verified" value="1" class="rounded border-gray-300 text-indigo-600">
                            <span class="text-sm text-gray-700">Mark Verified</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300 text-indigo-600">
                            <span class="text-sm text-gray-700">Set Active</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="is_featured" value="1" class="rounded border-gray-300 text-indigo-600">
                            <span class="text-sm text-gray-700">Set Featured</span>
                        </label>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="closeBulkEditModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">Apply to All Selected</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div id="categoryModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/30" onclick="closeCategoryModal()"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 bg-white rounded-xl shadow-xl border border-gray-200 w-full max-w-lg max-h-[80vh] overflow-y-auto">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Manage Categories</h3>
            <button onclick="closeCategoryModal()" class="text-gray-400 hover:text-gray-600">&times;</button>
        </div>
        <div class="px-6 py-4">
            <form method="POST" action="{{ route('admin.places.categories') }}" class="flex items-center gap-2 mb-6">
                @csrf
                <input type="text" name="name" placeholder="New category name" required class="flex-1 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
                <input type="text" name="icon" placeholder="Icon name (optional)" class="w-36 px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 outline-none">
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">Add</button>
            </form>
            <div class="space-y-2">
                @foreach($categories as $cat)
                <div class="flex items-center justify-between px-4 py-2.5 bg-gray-50 rounded-lg group">
                    <form method="POST" action="{{ route('admin.places.categories') }}" class="flex items-center gap-2 flex-1">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="id" value="{{ $cat->id }}">
                        <input type="text" name="name" value="{{ $cat->name }}" required class="flex-1 px-2 py-1 text-sm border border-transparent hover:border-gray-300 rounded focus:border-indigo-400 focus:ring-1 focus:ring-indigo-200 outline-none bg-transparent focus:bg-white">
                        <input type="text" name="icon" value="{{ $cat->icon }}" placeholder="icon" class="w-20 px-2 py-1 text-xs border border-transparent hover:border-gray-300 rounded focus:border-indigo-400 focus:ring-1 focus:ring-indigo-200 outline-none bg-transparent focus:bg-white">
                        <button type="submit" class="text-xs text-indigo-600 hover:text-indigo-800 opacity-0 group-hover:opacity-100 transition-opacity">
                            <i class="fas fa-check"></i>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.places.categories') }}" onsubmit="return confirm('Delete category \'{{ $cat->name }}\'?')">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="id" value="{{ $cat->id }}">
                        <button type="submit" class="text-xs text-red-500 hover:text-red-700 ml-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<script>
function openCategoryModal() { document.getElementById('categoryModal').classList.remove('hidden'); }
function closeCategoryModal() { document.getElementById('categoryModal').classList.add('hidden'); }

// Update count in bulk edit modal when opened
const origOpenBulkEdit = openBulkEditModal;
openBulkEditModal = function() {
    const ids = getSelectedIds();
    if (ids.length === 0) return;
    document.getElementById('bulkEditIds').value = ids.join(',');
    document.getElementById('bulkEditCount').textContent = ids.length;
    document.getElementById('bulkEditForm').reset();
    document.getElementById('bulkEditModal').classList.remove('hidden');
};
</script>
@endsection
