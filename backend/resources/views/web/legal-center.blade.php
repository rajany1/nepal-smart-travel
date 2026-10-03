@extends('web.dark-layout')

@section('title', 'Legal Center — Oripori')

@section('description', 'The Oripori Legal Center — terms, privacy, community guidelines, wallet, SOS, AI, and advertising policies in one place.')

@push('meta')
    <link rel="canonical" href="{{ url('/legal') }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Oripori">
    <meta property="og:title" content="Legal Center — Oripori">
    <meta property="og:description" content="Your privacy, safety and rights matter. Read all Oripori legal documents and policies.">
    <meta property="og:url" content="{{ url('/legal') }}">
    <meta name="twitter:card" content="summary">
@endpush

@push('head')
    /* Legal Center body — keep the existing light legal background exactly as-is. */
    body { background: #f7faf9; color: #1f2937; }
    body::before { display: none; }
    .orb { display: none; }
    a { color: inherit; }
    .hero, .docs { line-height: 1.6; }

    /* Navbar/footer come from the shared landing chrome (web/partials/site_header,
       site_footer) — identical to web/home.blade.php. */

    /* Hero */
    .hero {
        background: linear-gradient(160deg, #042f2e 0%, #115e59 70%, #0f766e 100%);
        color: #fff;
        text-align: center;
        padding: 64px 20px 56px;
    }
    .hero .eyebrow {
        font-size: 13px;
        font-weight: 700;
        letter-spacing: 0.22em;
        color: #fbbf24;
        text-transform: uppercase;
        margin-bottom: 14px;
    }
    .hero h1 {
        font-family: 'Outfit', sans-serif;
        font-size: clamp(30px, 5vw, 46px);
        font-weight: 800;
        letter-spacing: 0.04em;
        margin-bottom: 14px;
    }
    .hero p {
        max-width: 560px;
        margin: 0 auto;
        color: #ccfbf1;
        font-size: 17px;
    }

    /* Cards */
    .docs {
        max-width: 1080px;
        margin: 0 auto;
        padding: 40px 20px 64px;
    }
    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }
    .card {
        background: #fff;
        border: 1px solid #e5e9e7;
        border-radius: 16px;
        padding: 24px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        text-decoration: none;
        color: inherit;
        transition: box-shadow .18s ease, transform .18s ease, border-color .18s ease;
    }
    .card:hover {
        border-color: #0f766e;
        box-shadow: 0 12px 30px rgba(4, 47, 46, 0.10);
        transform: translateY(-2px);
    }
    .card .card-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .card .icon {
        width: 42px; height: 42px;
        border-radius: 12px;
        background: #f0fdfa;
        color: #0f766e;
        display: grid; place-items: center;
        font-size: 17px;
    }
    .card .version {
        font-size: 12px;
        font-weight: 700;
        color: #0f766e;
        background: #f0fdfa;
        border: 1px solid #ccfbf1;
        padding: 3px 9px;
        border-radius: 999px;
    }
    .card h2 { font-size: 17px; font-weight: 700; color: #0f2f2b; line-height: 1.35; }
    .card .desc { font-size: 14px; color: #5b6770; flex: 1; }
    .card .meta { font-size: 12.5px; color: #8a949b; }
    .card .read {
        margin-top: 6px;
        align-self: flex-start;
        font-size: 14px;
        font-weight: 700;
        color: #0f766e;
        display: inline-flex;
        align-items: center;
        gap: 7px;
    }
    .card:hover .read i { transform: translateX(3px); }
    .card .read i { transition: transform .15s ease; }

    .empty {
        grid-column: 1 / -1;
        text-align: center;
        background: #fff;
        border: 1px dashed #cbd5d1;
        border-radius: 16px;
        padding: 56px 24px;
        color: #6b7280;
    }
    .empty i { font-size: 34px; color: #cbd5d1; margin-bottom: 12px; display: block; }

    @media (max-width: 640px) {
        .hero { padding: 44px 18px 40px; }
        .docs { padding: 28px 16px 48px; }
    }
@endpush

@section('content')
    <section class="hero">
        <div class="eyebrow">Legal &amp; Policies</div>
        <h1>LEGAL CENTER</h1>
        <p>Your privacy, safety and rights matter.</p>
    </section>

    <div class="docs">
        @if($documents->isEmpty())
            <div class="grid">
                <div class="empty">
                    <i class="fas fa-file-contract"></i>
                    <strong>No legal documents have been published yet.</strong>
                    <p>Published documents will appear here.</p>
                </div>
            </div>
        @else
            <div class="grid">
                @foreach($documents as $doc)
                    <a class="card" href="{{ route('web.legal.show', $doc->slug ?? $doc->type) }}">
                        <div class="card-top">
                            <div class="icon"><i class="fas fa-file-lines"></i></div>
                            <span class="version">v{{ $doc->version ?? '1.0' }}</span>
                        </div>
                        <h2>{{ $doc->title }}</h2>
                        @if($doc->short_description)
                            <p class="desc">{{ $doc->short_description }}</p>
                        @endif
                        <p class="meta">
                            @if($doc->effective_date)
                                Effective {{ $doc->effective_date->format('M j, Y') }}
                            @elseif($doc->published_at)
                                Updated {{ $doc->published_at->format('M j, Y') }}
                            @endif
                        </p>
                        <span class="read">Read <i class="fas fa-arrow-right"></i></span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
@endsection
