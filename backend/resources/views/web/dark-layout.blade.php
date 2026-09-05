<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Nepal Smart Travel & Local Intelligence Platform — discover places, live road conditions, routes, and exclusive local offers.">
    <title>@yield('title', 'Nepal Smart Travel')</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #020e0e; color: #fff; overflow-x: hidden; position: relative;
            min-height: 100vh; display: flex; flex-direction: column;
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

        /* NAVBAR */
        .k-nav { display:flex; align-items:center; justify-content:space-between; padding:0.75rem 3rem; position:sticky; top:0; z-index:100; backdrop-filter:blur(24px); background:rgba(2,14,14,0.7); border-bottom:1px solid rgba(255,255,255,0.04); }
        .k-logo { display:flex; align-items:center; gap:0.75rem; }
        .k-logo-icon { width:42px; height:42px; background:linear-gradient(135deg,#f59e0b,#ea580c); border-radius:12px; display:flex; align-items:center; justify-content:center; box-shadow:0 8px 32px rgba(245,158,11,0.3); position:relative; overflow:hidden; }
        .k-logo-icon::after { content:''; position:absolute; inset:-50%; background:linear-gradient(45deg,transparent 30%,rgba(255,255,255,0.25) 50%,transparent 70%); animation:shine 4s infinite; }
        @keyframes shine { 0%{transform:translateX(-100%) rotate(45deg)} 100%{transform:translateX(100%) rotate(45deg)} }
        .k-logo-text h1 { font-family:'Outfit',sans-serif; font-size:1.2rem; font-weight:800; background:linear-gradient(135deg,#fff,#e2e8f0); -webkit-background-clip:text; -webkit-text-fill-color:transparent; margin:0; line-height:1.1; }
        .k-logo-text span { font-size:0.65rem; color:#5eead4; font-weight:600; letter-spacing:0.1em; text-transform:uppercase; }
        .k-nav-links { display:flex; gap:2rem; list-style:none; margin:0; padding:0; }
        .k-nav-links a { color:rgba(255,255,255,0.55); text-decoration:none; font-size:0.85rem; font-weight:500; transition:all 0.3s ease; position:relative; padding:0.25rem 0; }
        .k-nav-links a:hover { color:#fff; }
        .k-nav-links a.active { color:#f59e0b; }
        .k-nav-links a::after { content:''; position:absolute; bottom:-2px; left:50%; width:0; height:2px; background:linear-gradient(90deg,#f59e0b,#ea580c); transition:all 0.3s ease; transform:translateX(-50%); border-radius:2px; }
        .k-nav-links a:hover::after, .k-nav-links a.active::after { width:100%; }
        .k-nav-cta { background:linear-gradient(135deg,#f59e0b,#ea580c); color:#fff; border:none; padding:0.6rem 1.4rem; border-radius:10px; font-weight:600; font-size:0.85rem; cursor:pointer; display:inline-flex; align-items:center; gap:0.5rem; box-shadow:0 4px 20px rgba(245,158,11,0.25); transition:all 0.3s ease; text-decoration:none; }
        .k-nav-cta:hover { transform:translateY(-2px); box-shadow:0 8px 30px rgba(245,158,11,0.4); }

        /* BUTTONS */
        .k-btn-primary { background:linear-gradient(135deg,#f59e0b,#ea580c); color:#fff; border:none; padding:0.9rem 2rem; border-radius:14px; font-weight:600; font-size:1rem; cursor:pointer; display:inline-flex; align-items:center; gap:0.625rem; box-shadow:0 10px 40px rgba(245,158,11,0.3); transition:all 0.3s cubic-bezier(0.4,0,0.2,1); text-decoration:none; }
        .k-btn-primary:hover { transform:translateY(-3px) scale(1.02); box-shadow:0 14px 50px rgba(245,158,11,0.4); }
        .k-btn-secondary { background:rgba(255,255,255,0.04); color:#fff; border:1px solid rgba(255,255,255,0.1); padding:0.9rem 2rem; border-radius:14px; font-weight:600; font-size:1rem; cursor:pointer; display:inline-flex; align-items:center; gap:0.625rem; backdrop-filter:blur(20px); transition:all 0.3s ease; text-decoration:none; }
        .k-btn-secondary:hover { background:rgba(255,255,255,0.08); border-color:rgba(255,255,255,0.2); transform:translateY(-3px); }

        /* FOOTER */
        .k-footer { border-top:1px solid rgba(255,255,255,0.04); padding:4rem 3rem 2rem; position:relative; z-index:2; background:rgba(0,0,0,0.2); margin-top:auto; }
        .k-footer-inner { max-width:1400px; margin:0 auto; display:grid; grid-template-columns:2fr 1fr 1fr 1fr; gap:3rem; margin-bottom:3rem; }
        .k-footer-brand h4 { font-family:'Outfit',sans-serif; font-size:1.3rem; font-weight:800; margin:0 0 1rem; background:linear-gradient(135deg,#fff,#e2e8f0); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .k-footer-brand p { font-size:0.9rem; color:rgba(255,255,255,0.4); line-height:1.7; margin:0 0 1.5rem; max-width:300px; }
        .k-footer-col h5 { font-family:'Outfit',sans-serif; font-size:0.9rem; font-weight:700; margin:0 0 1.25rem; color:#fff; }
        .k-footer-col ul { list-style:none; margin:0; padding:0; }
        .k-footer-col li { margin-bottom:0.75rem; }
        .k-footer-col a { color:rgba(255,255,255,0.4); text-decoration:none; font-size:0.85rem; transition:all 0.3s ease; }
        .k-footer-col a:hover { color:#f59e0b; }
        .k-footer-bottom { text-align:center; padding-top:2rem; border-top:1px solid rgba(255,255,255,0.04); font-size:0.8rem; color:rgba(255,255,255,0.3); }

        /* MOBILE */
        @media (max-width: 1024px) {
            .k-nav-links { display:none; }
            .k-nav { padding:1rem 1.5rem; }
            .k-footer-inner { grid-template-columns:1fr; gap:2rem; }
        }
        @stack('head')
    </style>
</head>
<body>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>

    <nav class="k-nav">
        <a href="{{ route('web.home') }}" class="k-logo" style="text-decoration:none">
            <div style="background:rgba(255,255,255,0.9);border-radius:16px;padding:8px 12px;display:flex;align-items:center;box-shadow:0 4px 20px rgba(0,0,0,0.3)">
                <img src="{{ asset('images/oripori_logo.png') }}" alt="Oripori" style="height:80px;width:auto;object-fit:contain">
            </div>
        </a>
        <ul class="k-nav-links">
            <li><a href="{{ route('web.home') }}">Home</a></li>
            <li><a href="{{ route('web.places') }}" class="{{ request()->routeIs('web.places') || request()->routeIs('web.place') || request()->routeIs('web.category') ? 'active' : '' }}">Places</a></li>
            <li><a href="{{ route('web.routes') }}" class="{{ request()->routeIs('web.routes') || request()->routeIs('web.route') ? 'active' : '' }}">Routes</a></li>
            <li><a href="{{ route('web.offers') }}" class="{{ request()->routeIs('web.offers') ? 'active' : '' }}">Offers</a></li>
            <li><a href="{{ route('partner.login') }}">Partner</a></li>
        </ul>
        <a href="{{ env('PLAY_STORE_URL', '#') }}" class="k-nav-cta">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Get the App
        </a>
    </nav>

    <main style="flex:1;position:relative;z-index:2;">
        @yield('content')
    </main>

    <footer class="k-footer">
        <div class="k-footer-inner">
            <div class="k-footer-brand">
                <h4>Nepal Smart Travel</h4>
                <p>Your local intelligence platform for exploring Nepal. Real-time data, community-driven, and built for the mountains.</p>
            </div>
            <div class="k-footer-col">
                <h5>Services</h5>
                <ul>
                    <li><a href="{{ route('web.places') }}">Places & Trails</a></li>
                    <li><a href="{{ route('web.category', 'hotels') }}">Hotels & Stays</a></li>
                    <li><a href="{{ route('web.category', 'restaurants') }}">Restaurants</a></li>
                    <li><a href="{{ route('web.routes') }}">Curated Routes</a></li>
                </ul>
            </div>
            <div class="k-footer-col">
                <h5>Company</h5>
                <ul>
                    <li><a href="#">About Us</a></li>
                    <li><a href="{{ route('partner.register') }}">Partner With Us</a></li>
                    <li><a href="#">Careers</a></li>
                    <li><a href="#">Blog</a></li>
                </ul>
            </div>
            <div class="k-footer-col">
                <h5>Support</h5>
                <ul>
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">Safety</a></li>
                    <li><a href="#">Terms of Service</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                </ul>
            </div>
        </div>
        <div class="k-footer-bottom">
            &copy; {{ date('Y') }} Nepal Smart Travel. All rights reserved. Made with <span style="color:#ea580c">&hearts;</span> in Nepal.
        </div>
    </footer>

    <script>
        (function () {
            if (new URLSearchParams(window.location.search).has('app')) {
                document.cookie = 'nst_app=1; path=/; max-age=31536000';
            }
        })();
    </script>
    @stack('scripts')
</body>
</html>
