@extends('web.dark-layout')

@section('title', $place->name)

@push('head')
<style>
    .k-detail-hero { position:relative; height:400px; overflow:hidden; }
    .k-detail-hero img { width:100%; height:100%; object-fit:cover; }
    .k-detail-hero .k-hero-overlay { position:absolute; inset:0; background:linear-gradient(180deg, transparent 30%, rgba(2,14,14,0.95) 100%); }
    .k-detail-hero .k-hero-placeholder { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,rgba(245,158,11,0.1),rgba(16,185,129,0.05)); }
    .k-detail-hero .k-hero-placeholder i { font-size:5rem; color:rgba(255,255,255,0.08); }
    .k-detail-hero .k-hero-content { position:absolute; bottom:0; left:0; right:0; padding:2.5rem 3rem; z-index:2; max-width:1400px; margin:0 auto; }

    .k-breadcrumb { display:flex; align-items:center; gap:0.5rem; margin-bottom:1rem; font-size:0.8rem; }
    .k-breadcrumb a { color:rgba(255,255,255,0.45); text-decoration:none; transition:color 0.3s; }
    .k-breadcrumb a:hover { color:#f59e0b; }
    .k-breadcrumb span { color:rgba(255,255,255,0.3); }
    .k-breadcrumb .k-current { color:rgba(255,255,255,0.7); }

    .k-detail-tags { display:flex; flex-wrap:wrap; gap:0.5rem; margin-bottom:0.75rem; }
    .k-detail-tag { display:inline-flex; align-items:center; gap:0.35rem; background:rgba(245,158,11,0.12); border:1px solid rgba(245,158,11,0.2); border-radius:100px; padding:0.3rem 0.8rem; font-size:0.7rem; font-weight:600; color:#fbbf24; backdrop-filter:blur(10px); }
    .k-detail-tag.featured { background:rgba(16,185,129,0.12); border-color:rgba(16,185,129,0.2); color:#5eead4; }
    .k-detail-tag.rating { background:rgba(245,158,11,0.12); border-color:rgba(245,158,11,0.2); color:#fbbf24; }

    .k-detail-title { font-family:'Outfit',sans-serif; font-size:clamp(1.8rem,3vw,2.5rem); font-weight:800; margin:0 0 0.35rem; }
    .k-detail-loc { font-size:0.9rem; color:rgba(255,255,255,0.45); display:flex; align-items:center; gap:0.4rem; }

    .k-detail-body { max-width:1400px; margin:0 auto; padding:2.5rem 3rem; position:relative; z-index:2; display:grid; grid-template-columns:2fr 1fr; gap:2rem; }

    .k-detail-main { display:flex; flex-direction:column; gap:1.5rem; }

    .k-detail-card { background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06); border-radius:20px; padding:1.75rem; backdrop-filter:blur(20px); }
    .k-detail-card h2 { font-family:'Outfit',sans-serif; font-size:1.15rem; font-weight:700; margin:0 0 1rem; display:flex; align-items:center; gap:0.5rem; }
    .k-detail-card h2 i { color:#f59e0b; font-size:0.95rem; }
    .k-detail-card p { font-size:0.9rem; color:rgba(255,255,255,0.55); line-height:1.8; margin:0; white-space:pre-line; }

    .k-info-grid { display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; }
    .k-info-item { display:flex; align-items:center; gap:0.75rem; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.05); border-radius:12px; padding:0.875rem 1rem; transition:all 0.3s ease; text-decoration:none; color:inherit; }
    .k-info-item:hover { background:rgba(245,158,11,0.05); border-color:rgba(245,158,11,0.15); }
    .k-info-item i { color:#f59e0b; font-size:1rem; width:20px; text-align:center; }
    .k-info-item span { font-size:0.85rem; color:rgba(255,255,255,0.6); }

    .k-review-card { background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.05); border-radius:14px; padding:1.25rem; }
    .k-review-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:0.5rem; }
    .k-review-author { font-size:0.85rem; font-weight:600; color:#fff; }
    .k-review-stars { color:#f59e0b; font-size:0.75rem; }
    .k-review-title { font-size:0.85rem; font-weight:600; color:rgba(255,255,255,0.8); margin-bottom:0.25rem; }
    .k-review-text { font-size:0.85rem; color:rgba(255,255,255,0.45); line-height:1.6; }
    .k-review-date { font-size:0.7rem; color:rgba(255,255,255,0.25); margin-top:0.5rem; }
    .k-review-empty { text-align:center; padding:2rem; color:rgba(255,255,255,0.3); font-size:0.85rem; }

    .k-detail-sidebar { display:flex; flex-direction:column; gap:1.5rem; }

    .k-sidebar-cta { background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.06); border-radius:20px; padding:1.75rem; text-align:center; backdrop-filter:blur(20px); }
    .k-sidebar-cta-icon { width:56px; height:56px; border-radius:16px; background:linear-gradient(135deg,#f59e0b,#ea580c); display:flex; align-items:center; justify-content:center; margin:0 auto 1rem; color:#fff; font-size:1.25rem; box-shadow:0 8px 25px rgba(245,158,11,0.3); }
    .k-sidebar-cta h3 { font-family:'Outfit',sans-serif; font-size:1.1rem; font-weight:700; margin:0 0 0.35rem; }
    .k-sidebar-cta p { font-size:0.8rem; color:rgba(255,255,255,0.4); margin:0 0 1.25rem; line-height:1.6; }
    .k-store-link { display:block; width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:0.75rem 1rem; color:#fff; text-decoration:none; font-size:0.85rem; font-weight:600; display:flex; align-items:center; justify-content:center; gap:0.5rem; transition:all 0.3s ease; margin-bottom:0.5rem; }
    .k-store-link:hover { background:rgba(255,255,255,0.08); border-color:rgba(255,255,255,0.15); transform:translateY(-2px); }

    .k-similar-card { display:flex; align-items:center; gap:0.75rem; padding:0.75rem; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.05); border-radius:12px; text-decoration:none; color:inherit; transition:all 0.3s ease; }
    .k-similar-card:hover { background:rgba(245,158,11,0.05); border-color:rgba(245,158,11,0.15); }
    .k-similar-icon { width:40px; height:40px; border-radius:10px; background:rgba(245,158,11,0.1); display:flex; align-items:center; justify-content:center; color:#f59e0b; flex-shrink:0; }
    .k-similar-name { font-size:0.85rem; font-weight:600; color:rgba(255,255,255,0.8); }
    .k-similar-loc { font-size:0.7rem; color:rgba(255,255,255,0.35); }

    .k-coords { font-size:0.75rem; color:rgba(255,255,255,0.25); margin-top:0.75rem; text-align:center; font-family:monospace; }

    @media (max-width:1024px) {
        .k-detail-hero { height:300px; }
        .k-detail-hero .k-hero-content { padding:1.5rem; }
        .k-detail-body { grid-template-columns:1fr; padding:1.5rem; }
    }
    @media (max-width:640px) {
        .k-detail-hero { height:250px; }
        .k-info-grid { grid-template-columns:1fr; }
    }
</style>
@endpush

@section('content')
<div class="k-detail-hero">
    @if($place->images->isNotEmpty())
        <img src="{{ $place->images->first()->image_url }}" alt="{{ $place->name }}">
    @else
        <div class="k-hero-placeholder"><i class="fas fa-map-marker-alt"></i></div>
    @endif
    <div class="k-hero-overlay"></div>
    <div class="k-hero-content">
        <div class="k-breadcrumb">
            <a href="{{ route('web.home') }}"><i class="fas fa-home"></i> Home</a>
            <span>/</span>
            <a href="{{ route('web.places') }}">Places</a>
            <span>/</span>
            <span class="k-current">{{ $place->name }}</span>
        </div>
        <div class="k-detail-tags">
            @if($place->category?->name)
                <span class="k-detail-tag"><i class="fas fa-tag"></i> {{ $place->category->name }}</span>
            @endif
            @if($place->is_featured)
                <span class="k-detail-tag featured"><i class="fas fa-star"></i> Featured</span>
            @endif
            @if($place->average_rating > 0)
                <span class="k-detail-tag rating"><i class="fas fa-star"></i> {{ number_format($place->average_rating, 1) }} ({{ $place->total_reviews }} reviews)</span>
            @endif
        </div>
        <h1 class="k-detail-title">{{ $place->name }}</h1>
        <div class="k-detail-loc">
            <i class="fas fa-location-dot"></i>
            {{ $place->address ? $place->address . ', ' : '' }}{{ $place->district ?? 'Nepal' }}
        </div>
    </div>
</div>

<div class="k-detail-body">
    <div class="k-detail-main">
        @if($place->description)
            <div class="k-detail-card">
                <h2><i class="fas fa-info-circle"></i> About</h2>
                <p>{{ $place->description }}</p>
            </div>
        @endif

        <div class="k-detail-card">
            <h2><i class="fas fa-address-card"></i> Contact & Details</h2>
            <div class="k-info-grid">
                @if($place->phone)
                    <a href="tel:{{ $place->phone }}" class="k-info-item">
                        <i class="fas fa-phone"></i>
                        <span>{{ $place->phone }}</span>
                    </a>
                @endif
                @if($place->website)
                    <a href="{{ str_starts_with($place->website, 'http') ? $place->website : 'https://' . $place->website }}" target="_blank" rel="noopener" class="k-info-item">
                        <i class="fas fa-globe"></i>
                        <span>Website</span>
                    </a>
                @endif
                @if($place->district)
                    <div class="k-info-item">
                        <i class="fas fa-location-dot"></i>
                        <span>{{ $place->district }}</span>
                    </div>
                @endif
                @if($place->address)
                    <div class="k-info-item">
                        <i class="fas fa-map"></i>
                        <span>{{ $place->address }}</span>
                    </div>
                @endif
            </div>
            @if($place->latitude && $place->longitude)
                <div class="k-coords">
                    <i class="fas fa-crosshairs"></i> {{ number_format($place->latitude, 5) }}, {{ number_format($place->longitude, 5) }}
                </div>
            @endif
        </div>

        <div class="k-detail-card">
            <h2><i class="fas fa-comments"></i> Reviews & Community Reports</h2>
            @forelse($reviews as $review)
                <div class="k-review-card" @if(!$loop->last) style="margin-bottom:0.75rem" @endif>
                    <div class="k-review-header">
                        <div class="k-review-author">{{ $review->user?->name ?? 'Traveler' }}</div>
                        <div class="k-review-stars">
                            @for($i = 1; $i <= 5; $i++)
                                <i class="fas fa-star{{ $i <= $review->rating ? '' : '-o' }}" style="{{ $i > $review->rating ? 'opacity:0.3' : '' }}"></i>
                            @endfor
                        </div>
                    </div>
                    @if($review->title)
                        <div class="k-review-title">{{ $review->title }}</div>
                    @endif
                    <div class="k-review-text">{{ $review->description }}</div>
                    <div class="k-review-date">{{ $review->created_at?->format('M j, Y') }}</div>
                </div>
            @empty
                <div class="k-review-empty">
                    <i class="fas fa-comment-dots" style="font-size:1.5rem;margin-bottom:0.5rem;display:block;opacity:0.3"></i>
                    No reviews yet. Be the first to share your experience!
                </div>
            @endforelse
        </div>
    </div>

    <div class="k-detail-sidebar">
        <div class="k-sidebar-cta">
            <div class="k-sidebar-cta-icon"><i class="fas fa-mobile-screen"></i></div>
            <h3>Experience it live</h3>
            <p>Real-time road conditions, live reports, and exclusive rewards — in the app.</p>
            <a href="{{ env('PLAY_STORE_URL', '#') }}" class="k-store-link">
                <i class="fab fa-google-play"></i> Google Play
            </a>
            <a href="{{ env('APP_STORE_URL', '#') }}" class="k-store-link">
                <i class="fab fa-apple"></i> App Store
            </a>
        </div>

        @if($similar->isNotEmpty())
            <div class="k-detail-card">
                <h2><i class="fas fa-compass"></i> Similar Places</h2>
                <div style="display:flex;flex-direction:column;gap:0.5rem;">
                    @foreach($similar as $s)
                        <a href="{{ route('web.place', $s->id) }}" class="k-similar-card">
                            <div class="k-similar-icon"><i class="fas fa-map-marker-alt"></i></div>
                            <div>
                                <div class="k-similar-name">{{ $s->name }}</div>
                                <div class="k-similar-loc">{{ $s->district }}</div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
