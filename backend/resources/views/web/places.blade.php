@extends('web.dark-layout')

@section('title', isset($title) ? $title . ' — Places' : 'All Places')

@push('head')
<style>
    .k-page-header { padding:3rem 3rem 0; max-width:1400px; margin:0 auto; position:relative; z-index:2; }
    .k-page-header h1 { font-family:'Outfit',sans-serif; font-size:clamp(1.8rem,3vw,2.5rem); font-weight:800; margin:0 0 0.5rem; }
    .k-page-header p { font-size:0.95rem; color:rgba(255,255,255,0.45); margin:0; }

    .k-filter-bar { max-width:1400px; margin:0 auto; padding:1.5rem 3rem; position:relative; z-index:2; }
    .k-filter-form { display:flex; flex-wrap:wrap; gap:0.75rem; align-items:end; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06); border-radius:16px; padding:1.25rem 1.5rem; backdrop-filter:blur(20px); }
    .k-filter-group { display:flex; flex-direction:column; gap:0.35rem; flex:1; min-width:180px; }
    .k-filter-group label { font-size:0.7rem; font-weight:600; color:rgba(255,255,255,0.45); text-transform:uppercase; letter-spacing:0.08em; }
    .k-filter-group input, .k-filter-group select {
        background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.08);
        border-radius:10px; padding:0.6rem 1rem; color:#fff; font-size:0.85rem;
        font-family:'Plus Jakarta Sans',sans-serif; outline:none; transition:all 0.3s ease;
    }
    .k-filter-group input::placeholder { color:rgba(255,255,255,0.3); }
    .k-filter-group input:focus, .k-filter-group select:focus { border-color:rgba(245,158,11,0.4); box-shadow:0 0 0 3px rgba(245,158,11,0.1); }
    .k-filter-group select option { background:#0a1f1f; color:#fff; }
    .k-filter-btn { background:linear-gradient(135deg,#f59e0b,#ea580c); color:#fff; border:none; padding:0.6rem 1.5rem; border-radius:10px; font-weight:600; font-size:0.85rem; cursor:pointer; transition:all 0.3s ease; white-space:nowrap; }
    .k-filter-btn:hover { transform:translateY(-2px); box-shadow:0 8px 25px rgba(245,158,11,0.3); }
    .k-filter-clear { color:rgba(255,255,255,0.4); text-decoration:none; font-size:0.85rem; padding:0.6rem 0; transition:color 0.3s; white-space:nowrap; }
    .k-filter-clear:hover { color:#f59e0b; }

    .k-places-section { max-width:1400px; margin:0 auto; padding:0 3rem 4rem; position:relative; z-index:2; }
    .k-places-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:1.5rem; }
    .k-place-card {
        background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06);
        border-radius:20px; overflow:hidden; transition:all 0.4s cubic-bezier(0.4,0,0.2,1);
        text-decoration:none; color:inherit; display:block;
    }
    .k-place-card:hover { transform:translateY(-8px); border-color:rgba(245,158,11,0.2); box-shadow:0 20px 60px rgba(0,0,0,0.4), 0 0 40px rgba(245,158,11,0.05); }
    .k-place-card-img { height:200px; position:relative; overflow:hidden; background:linear-gradient(135deg,rgba(245,158,11,0.1),rgba(16,185,129,0.05)); }
    .k-place-card-img img { width:100%; height:100%; object-fit:cover; transition:transform 0.6s cubic-bezier(0.4,0,0.2,1); }
    .k-place-card:hover .k-place-card-img img { transform:scale(1.08); }
    .k-place-card-img .k-placeholder-icon { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,0.15); font-size:3rem; }
    .k-place-card-rating { position:absolute; top:0.75rem; right:0.75rem; background:rgba(2,14,14,0.7); backdrop-filter:blur(12px); border:1px solid rgba(255,255,255,0.1); border-radius:100px; padding:0.25rem 0.65rem; font-size:0.75rem; font-weight:600; color:#fbbf24; display:flex; align-items:center; gap:0.3rem; }
    .k-place-card-body { padding:1.25rem; }
    .k-place-card-cat { font-size:0.7rem; font-weight:600; color:#f59e0b; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:0.35rem; }
    .k-place-card-name { font-family:'Outfit',sans-serif; font-size:1.1rem; font-weight:700; margin:0 0 0.35rem; color:#fff; }
    .k-place-card-loc { font-size:0.8rem; color:rgba(255,255,255,0.4); display:flex; align-items:center; gap:0.35rem; }

    .k-empty-state { text-align:center; padding:5rem 2rem; }
    .k-empty-state i { font-size:3rem; color:rgba(255,255,255,0.1); margin-bottom:1rem; display:block; }
    .k-empty-state p { color:rgba(255,255,255,0.35); font-size:0.95rem; }

    .k-pagination { display:flex; justify-content:center; gap:0.5rem; margin-top:3rem; flex-wrap:wrap; }
    .k-page-link { display:inline-flex; align-items:center; justify-content:center; min-width:40px; height:40px; padding:0 0.75rem; border-radius:10px; font-size:0.85rem; font-weight:500; text-decoration:none; transition:all 0.3s ease; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06); color:rgba(255,255,255,0.5); }
    .k-page-link:hover { background:rgba(255,255,255,0.06); color:#fff; border-color:rgba(255,255,255,0.1); }
    .k-page-link.active { background:linear-gradient(135deg,#f59e0b,#ea580c); color:#fff; border-color:transparent; box-shadow:0 4px 15px rgba(245,158,11,0.3); }

    @media (max-width:1024px) {
        .k-page-header, .k-filter-bar, .k-places-section { padding-left:1.5rem; padding-right:1.5rem; }
    }
    @media (max-width:640px) {
        .k-places-grid { grid-template-columns:1fr; }
        .k-filter-form { flex-direction:column; }
        .k-filter-group { min-width:100%; }
    }
</style>
@endpush

@section('content')
<div class="k-page-header">
    <h1>{{ isset($title) ? $title : 'All Places' }}</h1>
    <p>{{ $places->total() }} places found across Nepal</p>
</div>

@if(isset($categories))
<div class="k-filter-bar">
    <form method="GET" class="k-filter-form">
        <div class="k-filter-group">
            <label>Search</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search places, districts...">
        </div>
        <div class="k-filter-group">
            <label>Category</label>
            <select name="category">
                <option value="">All categories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" @selected((string) request('category') === (string) $cat->id)>{{ $cat->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="k-filter-group">
            <label>District</label>
            <select name="district">
                <option value="">All districts</option>
                @foreach($districts as $d)
                    <option value="{{ $d }}" @selected(request('district') === $d)>{{ $d }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="k-filter-btn"><i class="fas fa-search"></i> Filter</button>
        @if(request()->hasAny(['q', 'category', 'district']))
            <a href="{{ route('web.places') }}" class="k-filter-clear">Clear</a>
        @endif
    </form>
</div>
@endif

<div class="k-places-section">
    <div class="k-places-grid">
        @forelse($places as $place)
            <a href="{{ route('web.place', $place->id) }}" class="k-place-card">
                <div class="k-place-card-img">
                    @if($place->images->isNotEmpty())
                        <img src="{{ $place->images->first()->image_url }}" alt="{{ $place->name }}">
                    @else
                        <div class="k-placeholder-icon"><i class="fas fa-map-marker-alt"></i></div>
                    @endif
                    @if($place->average_rating > 0)
                        <div class="k-place-card-rating"><i class="fas fa-star"></i> {{ number_format($place->average_rating, 1) }}</div>
                    @endif
                </div>
                <div class="k-place-card-body">
                    <div class="k-place-card-cat">{{ $place->category?->name ?? 'Place' }}</div>
                    <div class="k-place-card-name">{{ $place->name }}</div>
                    <div class="k-place-card-loc">
                        <i class="fas fa-location-dot"></i>
                        {{ $place->district ?? 'Nepal' }}{{ $place->address ? ', ' . $place->address : '' }}
                    </div>
                </div>
            </a>
        @empty
            <div class="k-empty-state">
                <i class="fas fa-map-pin"></i>
                <p>No places found matching your filters.</p>
            </div>
        @endforelse
    </div>

    <div class="k-pagination">
        {{ $places->links() }}
    </div>
</div>
@endsection
