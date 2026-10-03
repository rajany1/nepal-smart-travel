<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="@yield('description', 'Nepal Smart Travel & Local Intelligence Platform — discover places, live road conditions, routes, and exclusive local offers.')">
    <title>@yield('title', 'Nepal Smart Travel')</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #020e0e; color: #fff; overflow-x: hidden; position: relative;
            min-height: 100vh; display: flex; flex-direction: column;
            padding-top: var(--header-height); /* reserves fixed landing navbar height */
        }
        body::before {
            content: ''; position: fixed; inset: 0;
            background:
                radial-gradient(ellipse at 15% 20%, rgba(245,158,11,0.15) 0%, transparent 50%),
                radial-gradient(ellipse at 85% 80%, rgba(16,185,129,0.1) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 50%, rgba(245,158,11,0.06) 0%, transparent 60%);
            pointer-events: none; z-index: 0;
        }
        .orb { position: fixed; border-radius: 50%; filter: blur(100px); pointer-events: none; z-index: 0; }
        .orb-1 { width: 500px; height: 500px; background: rgba(245,158,11,0.12); top: -150px; right: -100px; animation: orbFloat 25s ease-in-out infinite; }
        .orb-2 { width: 400px; height: 400px; background: rgba(16,185,129,0.08); bottom: 5%; left: -150px; animation: orbFloat 30s ease-in-out infinite reverse; }
        .orb-3 { width: 300px; height: 300px; background: rgba(245,158,11,0.06); top: 50%; right: 10%; animation: orbFloat 20s ease-in-out infinite 3s; }
        @keyframes orbFloat { 0%,100%{transform:translate(0,0) scale(1)} 33%{transform:translate(40px,-40px) scale(1.1)} 66%{transform:translate(-30px,30px) scale(0.9)} }

        /* NAVBAR + FOOTER — shared landing design system (web/partials/site_chrome_css) */

        /* BUTTONS */
        .k-btn-primary { background:linear-gradient(135deg,#f59e0b,#ea580c); color:#fff; border:none; padding:0.9rem 2rem; border-radius:14px; font-weight:600; font-size:1rem; cursor:pointer; display:inline-flex; align-items:center; gap:0.625rem; box-shadow:0 10px 40px rgba(245,158,11,0.3); transition:all 0.3s cubic-bezier(0.4,0,0.2,1); text-decoration:none; }
        .k-btn-primary:hover { transform:translateY(-3px) scale(1.02); box-shadow:0 14px 50px rgba(245,158,11,0.4); }
        .k-btn-secondary { background:rgba(255,255,255,0.04); color:#fff; border:1px solid rgba(255,255,255,0.1); padding:0.9rem 2rem; border-radius:14px; font-weight:600; font-size:1rem; cursor:pointer; display:inline-flex; align-items:center; gap:0.625rem; backdrop-filter:blur(20px); transition:all 0.3s ease; text-decoration:none; }
        .k-btn-secondary:hover { background:rgba(255,255,255,0.08); border-color:rgba(255,255,255,0.2); transform:translateY(-3px); }

        /* FOOTER + RESPONSIVE NAV — shared landing design system */
        @include('web.partials.site_chrome_css')

        @stack('head')
    </style>
    @stack('meta')
</head>
<body>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    {{-- Shared landing navbar — identical to web/home.blade.php --}}
    @include('web.partials.site_header')

    <main style="flex:1;position:relative;z-index:2;">
        @yield('content')
    </main>

    {{-- Shared landing footer — identical to web/home.blade.php --}}
    @include('web.partials.site_footer')

    <script>
        (function () {
            if (new URLSearchParams(window.location.search).has('app')) {
                document.cookie = 'nst_app=1; path=/; max-age=31536000';
            }
        })();
    </script>
    @include('web.partials.site_nav_js')
    @stack('scripts')
</body>
</html>
