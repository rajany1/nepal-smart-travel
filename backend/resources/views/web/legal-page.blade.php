@extends('web.dark-layout')

@section('title', $document->title.' — Oripori Legal Center')

@section('description', $metaDescription)

@push('meta')
    <link rel="canonical" href="{{ url('/legal/'.($document->slug ?? $document->type)) }}">
    <meta name="robots" content="index, follow">
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="Oripori">
    <meta property="og:title" content="{{ $document->title }} — Oripori">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ url('/legal/'.($document->slug ?? $document->type)) }}">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $document->title }} — Oripori">
    <meta name="twitter:description" content="{{ $metaDescription }}">
@endpush

@push('head')
    /* Legal document body — keep the existing light legal background exactly as-is. */
    body { background: #f7faf9; color: #1f2937; }
    body::before { display: none; }
    .orb { display: none; }
    a { color: inherit; }
    html { scroll-behavior: smooth; }

    /* Navbar/footer sit on the light legal body — opaque landing dark only;
       fixed positioning + compact-on-scroll come from the shared layout. */
    .k-nav { background: #020e0e; backdrop-filter: none; }
    .k-footer { background: #020e0e; }

    /* Layout */
    .wrap { max-width: 1120px; margin: 0 auto; padding: 36px 20px 64px; line-height: 1.65; }
    .crumb { font-size: 13px; color: #6b7280; margin-bottom: 18px; }
    .crumb a { color: #0f766e; text-decoration: none; font-weight: 600; }
    .crumb a:hover { text-decoration: underline; }

    .layout {
        display: grid;
        grid-template-columns: 1fr;
        gap: 32px;
        align-items: start;
    }
    @media (min-width: 1024px) {
        .layout { grid-template-columns: 250px minmax(0, 1fr); }
    }

    /* TOC — sticky offsets account for the shared landing navbar height */
    .toc {
        background: #fff;
        border: 1px solid #e5e9e7;
        border-radius: 14px;
        padding: 18px 18px 14px;
    }
    .toc .toc-title {
        font-size: 11.5px;
        font-weight: 800;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: #0f766e;
        margin-bottom: 10px;
    }
    .toc ul { list-style: none; }
    .toc li { margin: 0; }
    .toc a {
        display: block;
        font-size: 13.5px;
        color: #4b5563;
        text-decoration: none;
        padding: 6px 8px;
        border-radius: 8px;
        border-left: 2px solid transparent;
        line-height: 1.4;
    }
    .toc a:hover { background: #f0fdfa; color: #0f766e; border-left-color: #0f766e; }
    .toc li.level-3 a { padding-left: 20px; font-size: 13px; }

    .toc-desktop { display: none; }
    @media (min-width: 1024px) {
        .toc-desktop { display: block; position: sticky; top: 90px; max-height: calc(100vh - 114px); overflow-y: auto; }
        .toc-mobile { display: none; }
    }
    .toc-mobile { margin-bottom: 20px; }
    .toc-mobile details {
        background: #fff;
        border: 1px solid #e5e9e7;
        border-radius: 12px;
        padding: 12px 16px;
    }
    .toc-mobile summary {
        cursor: pointer;
        font-weight: 700;
        font-size: 14px;
        color: #0f766e;
        list-style: none;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .toc-mobile summary::-webkit-details-marker { display: none; }
    .toc-mobile details[open] summary { margin-bottom: 8px; }

    /* Document card */
    .doc {
        background: #fff;
        border: 1px solid #e5e9e7;
        border-radius: 18px;
        padding: clamp(22px, 4vw, 44px);
        min-width: 0;
    }
    .doc h1 {
        font-family: 'Outfit', sans-serif;
        font-size: clamp(24px, 3.6vw, 34px);
        font-weight: 800;
        color: #0f2f2b;
        line-height: 1.25;
        margin-bottom: 10px;
    }
    .doc .lede { font-size: 15.5px; color: #5b6770; margin-bottom: 16px; }
    .meta-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding-bottom: 18px;
        margin-bottom: 8px;
        border-bottom: 1px solid #eef2f0;
    }
    .chip {
        font-size: 12.5px;
        font-weight: 600;
        color: #334155;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        padding: 5px 11px;
        border-radius: 999px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .chip i { color: #0f766e; font-size: 11px; }

    /* Content typography */
    .content {
        font-size: 15.5px;
        line-height: 1.8;
        color: #2d3748;
        overflow-wrap: anywhere;
    }
    .content h2 {
        font-family: 'Outfit', sans-serif;
        font-size: 21px;
        font-weight: 700;
        color: #0f2f2b;
        margin: 30px 0 12px;
        scroll-margin-top: 90px;
    }
    .content h3 {
        font-family: 'Outfit', sans-serif;
        font-size: 17.5px;
        font-weight: 700;
        color: #134e4a;
        margin: 24px 0 10px;
        scroll-margin-top: 90px;
    }
    .content h4 { font-size: 15.5px; font-weight: 700; margin: 20px 0 8px; color: #134e4a; }
    .content p { margin-bottom: 14px; }
    .content ul, .content ol { margin: 10px 0 16px; padding-left: 26px; }
    .content li { margin-bottom: 7px; }
    .content a { color: #0f766e; font-weight: 600; text-decoration: underline; text-underline-offset: 2px; }
    .content a:hover { color: #115e59; }
    .content strong { color: #0f2f2b; }
    .content blockquote {
        border-left: 4px solid #f59e0b;
        background: #fffbeb;
        padding: 12px 16px;
        border-radius: 0 10px 10px 0;
        margin: 16px 0;
        color: #78350f;
    }
    .content code {
        background: #f1f5f9;
        padding: 2px 6px;
        border-radius: 6px;
        font-size: 13.5px;
    }
    .content pre {
        background: #0f172a;
        color: #e2e8f0;
        padding: 14px 16px;
        border-radius: 10px;
        overflow-x: auto;
        margin: 16px 0;
        font-size: 13.5px;
    }
    .content pre code { background: none; padding: 0; color: inherit; }

    /* Quill output classes */
    .content .ql-align-center { text-align: center; }
    .content .ql-align-right { text-align: right; }
    .content .ql-align-justify { text-align: justify; }
    .content .ql-direction-rtl { direction: rtl; text-align: right; }
    .content .ql-indent-1 { margin-left: 3em; }
    .content .ql-indent-2 { margin-left: 6em; }
    .content .ql-indent-3 { margin-left: 9em; }
    .content .ql-indent-4 { margin-left: 12em; }
    .content .ql-indent-5 { margin-left: 15em; }
    .content .ql-indent-6 { margin-left: 18em; }
    .content .ql-indent-7 { margin-left: 21em; }
    .content .ql-indent-8 { margin-left: 24em; }
    .content .ql-indent-9 { margin-left: 27em; }
    .content .ql-indent-10 { margin-left: 30em; }
    .content .ql-indent-11 { margin-left: 33em; }
    .content .ql-indent-12 { margin-left: 36em; }

    .doc-footer {
        margin-top: 34px;
        padding-top: 18px;
        border-top: 1px solid #eef2f0;
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        justify-content: space-between;
        align-items: center;
        font-size: 13.5px;
        color: #6b7280;
    }
    .reference-link { margin: -2px 0 18px; font-size: 13.5px; }
    .reference-link a {
        color: #0f766e;
        font-weight: 600;
        text-decoration: underline;
        text-underline-offset: 2px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .reference-link a:hover { color: #115e59; }
    .doc-footer a {
        color: #0f766e;
        font-weight: 700;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 7px;
    }
    .doc-footer a:hover { text-decoration: underline; }

    @media (max-width: 640px) {
        .content { font-size: 15px; }
        .content .ql-indent-1, .content .ql-indent-2,
        .content .ql-indent-3, .content .ql-indent-4 { margin-left: 1.5em; }
    }
@endpush

@section('content')
    <div class="wrap">
        <nav class="crumb">
            <a href="{{ route('web.legal.index') }}">Legal Center</a> &nbsp;/&nbsp; {{ $document->title }}
        </nav>

        <div class="layout">
            {{-- Desktop: side table of contents --}}
            @if($toc)
                <aside class="toc toc-desktop" aria-label="Table of contents">
                    <div class="toc-title">On this page</div>
                    <ul>
                        @foreach($toc as $item)
                            <li class="level-{{ $item['level'] }}">
                                <a href="#{{ $item['id'] }}">{{ $item['text'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </aside>
            @endif

            <article class="doc">
                {{-- Mobile: compact table of contents --}}
                @if($toc)
                    <div class="toc toc-mobile">
                        <details>
                            <summary>
                                <span>Table of contents</span>
                                <i class="fas fa-chevron-down"></i>
                            </summary>
                            <ul>
                                @foreach($toc as $item)
                                    <li class="level-{{ $item['level'] }}">
                                        <a href="#{{ $item['id'] }}">{{ $item['text'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    </div>
                @endif

                <h1>{{ $document->title }}</h1>

                @if($document->short_description)
                    <p class="lede">{{ $document->short_description }}</p>
                @endif

                <div class="meta-row">
                    <span class="chip"><i class="fas fa-code-branch"></i> Version {{ $document->version ?? '1.0' }}</span>
                    @if($document->effective_date)
                        <span class="chip"><i class="fas fa-calendar-check"></i> Effective {{ $document->effective_date->format('F j, Y') }}</span>
                    @endif
                    @if($document->published_at)
                        <span class="chip"><i class="fas fa-clock"></i> Last updated {{ $document->published_at->format('F j, Y') }}</span>
                    @endif
                </div>

                @if($document->safe_reference_url)
                    <p class="reference-link">
                        <a href="{{ $document->safe_reference_url }}" target="_blank" rel="noopener noreferrer">
                            <i class="fas fa-arrow-up-right-from-square"></i> Reference
                        </a>
                    </p>
                @endif

                <div class="content">
                    {!! $content !!}
                </div>

                <div class="doc-footer">
                    <span>&copy; {{ date('Y') }} Oripori. All rights reserved.</span>
                    <a href="{{ route('web.legal.index') }}"><i class="fas fa-arrow-left"></i> Back to Legal Center</a>
                </div>
            </article>
        </div>
    </div>
@endsection
