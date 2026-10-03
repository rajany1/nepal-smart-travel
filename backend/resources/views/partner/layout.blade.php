@php
    // Guest-facing pages get the shared landing navbar instead of the portal header.
    $landingNav = request()->routeIs('partner.login', 'partner.register');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Business Partner Portal') — Nepal Smart Travel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { 50: '#f0fdfa', 100: '#ccfbf1', 200: '#99f6e4', 300: '#5eead4', 400: '#2dd4bf', 500: '#14b8a6', 600: '#0d9488', 700: '#0f766e', 800: '#115e59', 900: '#134e4a', 950: '#042f2e' },
                        accent: { 400: '#fbbf24', 500: '#f59e0b', 600: '#d97706' },
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        .drawer-overlay { transition: opacity 0.3s ease; }
        .drawer-panel { transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .drawer-panel.closed { transform: translateX(-100%); }
        .drawer-panel.open { transform: translateX(0); }
        .bottom-nav-item.active { color: #0d9488; }
        .bottom-nav-item.active i { transform: scale(1.15); }
        .bottom-nav-item i { transition: transform 0.2s ease; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .animate-slide-up { animation: slideUp 0.3s ease-out; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
    @if($landingNav)
    {{-- Shared landing header/footer design system — identical to web/home.blade.php --}}
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #49C5B6;
            --primary-dark: #015048;
            --primary-deep: #002824;
            --primary-light: #EBF7F5;
            --primary-border: rgba(73, 197, 182, 0.25);
            --white: #ffffff;
            --gray-50: #F7FAF9;
            --gray-100: #EDF3F1;
            --gray-200: #D8E4E1;
            --gray-600: #516360;
            --gray-800: #1C2B29;
            --font-main: 'Manrope', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            --header-height: 80px;
            --shadow-md: 0 10px 30px rgba(1, 80, 72, 0.08);
            --radius-sm: 10px;
            --radius-full: 9999px;
        }

        body {
            font-family: var(--font-main);
            padding-top: var(--header-height);
        }

        /* ===== HEADER & NAVIGATION ===== */
        .site-header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: var(--header-height);
            background-color: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--gray-200);
            z-index: 300;
            display: flex;
            align-items: center;
        }

        .site-header .container {
            width: 100%;
            max-width: 1240px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .header-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }

        .brand-logo img {
            height: 38px;
            width: auto;
            object-fit: contain;
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 32px;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .nav-link {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--primary-dark);
            position: relative;
            padding: 6px 0;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0%;
            height: 2px;
            background-color: var(--primary);
            transition: width 0.3s ease;
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .btn-cta-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            background-color: var(--primary-dark);
            color: var(--white);
            font-weight: 700;
            font-size: 0.95rem;
            border-radius: var(--radius-full);
            box-shadow: 0 4px 14px rgba(1, 80, 72, 0.2);
            border: 2px solid var(--primary-dark);
            transition: all 0.3s ease;
            white-space: nowrap;
            text-decoration: none;
        }

        .btn-cta-placeholder:hover {
            background-color: var(--primary);
            border-color: var(--primary);
            color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-glow);
        }

        .nav-mobile-toggle {
            display: none;
            font-size: 1.4rem;
            color: var(--primary-dark);
            cursor: pointer;
            background: none;
            border: none;
            line-height: 1;
            padding: 6px;
        }

        /* ===== FOOTER ===== */
        .site-footer {
            background-color: var(--primary-deep);
            color: var(--white);
            padding: 80px 0 40px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .site-footer .container {
            width: 100%;
            max-width: 1240px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .footer-top {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 50px;
            margin-bottom: 60px;
        }

        .footer-brand {
            max-width: 360px;
        }

        .footer-logo {
            margin-bottom: 20px;
        }

        .footer-logo img {
            height: 36px;
            width: auto;
            object-fit: contain;
        }

        .footer-brand p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.95rem;
            line-height: 1.7;
            margin-bottom: 24px;
        }

        .footer-heading {
            color: var(--white);
            font-size: 1.1rem;
            font-weight: 800;
            margin-bottom: 20px;
            position: relative;
        }

        .footer-links {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin: 0;
            padding: 0;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.75);
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.25s ease;
        }

        .footer-links a:hover {
            color: var(--primary);
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.6);
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 1024px) {
            .site-footer { padding: 60px 0 32px 0; }
            .footer-top { grid-template-columns: 1fr 1fr; gap: 40px; }
            .footer-bottom { justify-content: center; text-align: center; }
        }

        @media (max-width: 992px) {
            .nav-menu {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                flex-direction: column;
                align-items: stretch;
                gap: 0;
                background: var(--white);
                border-bottom: 1px solid var(--gray-200);
                box-shadow: var(--shadow-md);
                padding: 8px 24px 16px;
            }
            .nav-menu.is-open { display: flex; }
            .nav-menu li { width: 100%; }
            .nav-menu .nav-link {
                display: block;
                padding: 13px 0;
                border-bottom: 1px solid var(--gray-100);
            }
            .nav-menu li:last-child .nav-link { border-bottom: none; }
            .nav-link::after { display: none; }
            .nav-mobile-toggle { display: block; }
        }

        @media (max-width: 576px) {
            .footer-top { grid-template-columns: 1fr; }
        }

        @media (max-width: 480px) {
            .brand-logo img { height: 26px; }
            .btn-cta-placeholder { padding: 9px 15px; font-size: 0.85rem; }
        }
    </style>
    @endif
</head>
<body class="bg-slate-50 min-h-screen">
    <div class="min-h-screen flex flex-col">

        {{-- ========== TOP HEADER ========== --}}
        @if($landingNav)
            {{-- Shared landing header — identical to web/home.blade.php --}}
            <header class="site-header">
                <div class="container header-container">
                    <a href="{{ route('web.home') }}" class="brand-logo" aria-label="Oripori Home">
                        <img src="{{ asset('images/oripori_logo.svg') }}" alt="Oripori Logo">
                    </a>

                    <nav>
                        <ul class="nav-menu" id="nav-menu">
                            <li><a href="{{ route('web.home') }}#explore" class="nav-link">Explore</a></li>
                            <li><a href="{{ route('web.home') }}#safety" class="nav-link">Safety &amp; SOS</a></li>
                            <li><a href="{{ route('web.home') }}#community" class="nav-link">Community Insights</a></li>
                            <li><a href="{{ route('web.home') }}#rewards" class="nav-link">Oripori Coins</a></li>
                            <li><a href="{{ route('web.home') }}#values" class="nav-link">Core Values</a></li>
                        </ul>
                    </nav>

                    <a href="{{ route('partner.login') }}" class="btn-cta-placeholder">Partner Login</a>

                    <button class="nav-mobile-toggle" id="nav-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false" aria-controls="nav-menu">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                </div>
            </header>
        @else
        <header class="bg-primary-900 text-white shadow-lg sticky top-0 z-40">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 h-14 flex items-center justify-between">
                {{-- Logo + Title --}}
                <div class="flex items-center gap-3">
                    @auth
                        @if(auth()->user()->isBusiness())
                            <button onclick="toggleDrawer()" class="lg:hidden w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 grid place-items-center transition">
                                <i class="fas fa-bars text-sm"></i>
                            </button>
                        @endif
                    @endauth
                    <a href="{{ route('partner.dashboard') }}" class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-accent-500 grid place-items-center text-sm shadow-md">
                            <i class="fas fa-store"></i>
                        </div>
                        <div class="hidden sm:block">
                            <h1 class="text-sm font-bold leading-tight">Business Portal</h1>
                            <p class="text-[10px] text-teal-300 leading-tight">Nepal Smart Travel</p>
                        </div>
                    </a>
                </div>

                {{-- Desktop Nav --}}
                <div class="hidden lg:flex items-center gap-1">
                    @auth
                        @if(auth()->user()->isBusiness())
                            @php $partner = auth()->user()->business; @endphp
                            @if($partner && $partner->verification_status === 'verified')
                                <span class="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-full bg-teal-800/60 text-teal-200 mr-2">
                                    <i class="fas fa-check-circle text-emerald-400"></i> {{ $partner->name }}
                                </span>
                            @elseif($partner && $partner->verification_status === 'pending')
                                <span class="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-full bg-amber-500/20 text-amber-300 mr-2">
                                    <i class="fas fa-clock"></i> Pending
                                </span>
                            @endif
                            <a href="{{ route('partner.dashboard') }}" class="px-3 py-2 text-xs font-medium rounded-lg text-teal-200 hover:text-white hover:bg-white/10 transition"><i class="fas fa-tachometer-alt mr-1.5"></i>Dashboard</a>
                            <a href="{{ route('partner.offers') }}" class="px-3 py-2 text-xs font-medium rounded-lg text-teal-200 hover:text-white hover:bg-white/10 transition"><i class="fas fa-gift mr-1.5"></i>Offers</a>
                            <a href="{{ route('partner.ads') }}" class="px-3 py-2 text-xs font-medium rounded-lg text-teal-200 hover:text-white hover:bg-white/10 transition"><i class="fas fa-bullhorn mr-1.5"></i>Ads</a>
                            <a href="{{ route('partner.wallet') }}" class="px-3 py-2 text-xs font-medium rounded-lg text-teal-200 hover:text-white hover:bg-white/10 transition"><i class="fas fa-wallet mr-1.5"></i>Wallet</a>
                            <div class="w-px h-5 bg-white/20 mx-1"></div>
                            <form method="POST" action="{{ route('partner.logout') }}" class="inline">
                                @csrf
                                <button class="px-3 py-2 text-xs font-medium rounded-lg text-teal-200 hover:text-white hover:bg-white/10 transition"><i class="fas fa-sign-out-alt mr-1.5"></i>Logout</button>
                            </form>
                        @endif
                    @endauth
                </div>

                {{-- Mobile: Hamburger for non-auth or wallet icon --}}
                <div class="flex items-center gap-2 lg:hidden">
                    @auth
                        @if(auth()->user()->isBusiness())
                            <a href="{{ route('partner.wallet') }}" class="w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 grid place-items-center transition relative">
                                <i class="fas fa-wallet text-sm"></i>
                            </a>
                        @endif
                    @endauth
                </div>
            </div>
        </header>
        @endif

        {{-- ========== MOBILE DRAWER ========== --}}
        @auth
            @if(auth()->user()->isBusiness())
                @php $partner = auth()->user()->business; @endphp
                {{-- Overlay --}}
                <div id="drawer-overlay" onclick="toggleDrawer()" class="drawer-overlay fixed inset-0 bg-black/50 z-50 hidden opacity-0"></div>
                {{-- Panel --}}
                <div id="drawer-panel" class="drawer-panel closed fixed top-0 left-0 h-full w-72 bg-white z-50 shadow-2xl flex flex-col">
                    {{-- Drawer Header --}}
                    <div class="bg-gradient-to-br from-primary-700 to-primary-900 p-5 text-white">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-11 h-11 rounded-xl bg-accent-500 grid place-items-center text-lg shadow-md">
                                    <i class="fas fa-store"></i>
                                </div>
                                <div>
                                    <p class="font-bold text-sm">Business Portal</p>
                                    <p class="text-[10px] text-teal-200">Nepal Smart Travel</p>
                                </div>
                            </div>
                            <button onclick="toggleDrawer()" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 grid place-items-center transition">
                                <i class="fas fa-times text-sm"></i>
                            </button>
                        </div>
                        @if($partner)
                            <div class="bg-white/10 rounded-xl p-3 backdrop-blur">
                                <div class="flex items-center gap-2">
                                    <div class="w-9 h-9 rounded-lg bg-white/20 grid place-items-center text-sm font-bold">
                                        {{ strtoupper(substr($partner->name, 0, 2)) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-semibold text-sm truncate">{{ $partner->name }}</p>
                                        <p class="text-[10px] text-teal-200">{{ $partner->typeDisplay() }}</p>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    @if($partner->verification_status === 'verified')
                                        <span class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full bg-emerald-500/30 text-emerald-200"><i class="fas fa-check-circle"></i> Verified</span>
                                    @elseif($partner->verification_status === 'pending')
                                        <span class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full bg-amber-500/30 text-amber-200"><i class="fas fa-clock"></i> Pending Verification</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full bg-red-500/30 text-red-200"><i class="fas fa-times-circle"></i> {{ ucfirst($partner->verification_status) }}</span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Drawer Nav --}}
                    <nav class="flex-1 overflow-y-auto py-3 px-3">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 px-3 mb-2">Main Menu</p>
                        <a href="{{ route('partner.dashboard') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-700 hover:bg-primary-50 hover:text-primary-700 transition mb-1">
                            <span class="w-8 h-8 rounded-lg bg-primary-50 grid place-items-center text-primary-600"><i class="fas fa-tachometer-alt text-xs"></i></span> Dashboard
                        </a>
                        <a href="{{ route('partner.offers') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-700 hover:bg-primary-50 hover:text-primary-700 transition mb-1">
                            <span class="w-8 h-8 rounded-lg bg-amber-50 grid place-items-center text-amber-600"><i class="fas fa-gift text-xs"></i></span> Offers
                        </a>
                        <a href="{{ route('partner.ads') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-700 hover:bg-primary-50 hover:text-primary-700 transition mb-1">
                            <span class="w-8 h-8 rounded-lg bg-blue-50 grid place-items-center text-blue-600"><i class="fas fa-bullhorn text-xs"></i></span> Ad Campaigns
                        </a>

                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 px-3 mb-2 mt-5">Finance</p>
                        <a href="{{ route('partner.wallet') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-700 hover:bg-primary-50 hover:text-primary-700 transition mb-1">
                            <span class="w-8 h-8 rounded-lg bg-emerald-50 grid place-items-center text-emerald-600"><i class="fas fa-wallet text-xs"></i></span> My Wallet
                        </a>
                        <a href="{{ route('partner.payments.history') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-700 hover:bg-primary-50 hover:text-primary-700 transition mb-1">
                            <span class="w-8 h-8 rounded-lg bg-slate-100 grid place-items-center text-slate-600"><i class="fas fa-history text-xs"></i></span> Payment History
                        </a>

                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 px-3 mb-2 mt-5">Account</p>
                        <a href="{{ route('partner.business-form') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-slate-700 hover:bg-primary-50 hover:text-primary-700 transition mb-1">
                            <span class="w-8 h-8 rounded-lg bg-slate-100 grid place-items-center text-slate-600"><i class="fas fa-store text-xs"></i></span> Business Profile
                        </a>
                    </nav>

                    {{-- Drawer Footer --}}
                    <div class="border-t border-slate-100 p-3">
                        <form method="POST" action="{{ route('partner.logout') }}">
                            @csrf
                            <button class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-red-600 hover:bg-red-50 transition">
                                <span class="w-8 h-8 rounded-lg bg-red-50 grid place-items-center"><i class="fas fa-sign-out-alt text-xs"></i></span> Logout
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        @endauth

        {{-- ========== FLASH MESSAGES ========== --}}
        @if(session('success'))
            <div class="max-w-7xl mx-auto px-4 sm:px-6 mt-4 animate-slide-up">
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-sm flex items-center gap-2 shadow-sm">
                    <i class="fas fa-check-circle text-emerald-500"></i> {{ session('success') }}
                </div>
            </div>
        @endif
        @if(session('error'))
            <div class="max-w-7xl mx-auto px-4 sm:px-6 mt-4 animate-slide-up">
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm flex items-center gap-2 shadow-sm">
                    <i class="fas fa-exclamation-circle text-red-500"></i> {{ session('error') }}
                </div>
            </div>
        @endif
        @if(session('info'))
            <div class="max-w-7xl mx-auto px-4 sm:px-6 mt-4 animate-slide-up">
                <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-xl text-sm flex items-center gap-2 shadow-sm">
                    <i class="fas fa-info-circle text-blue-500"></i> {{ session('info') }}
                </div>
            </div>
        @endif

        {{-- ========== MAIN CONTENT ========== --}}
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 py-4 sm:py-6 pb-24 lg:pb-6">
            @yield('content')
        </main>

        {{-- ========== MOBILE BOTTOM NAV ========== --}}
        @auth
            @if(auth()->user()->isBusiness())
                <nav class="lg:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-slate-200 z-30 safe-bottom">
                    <div class="grid grid-cols-5 h-16 max-w-lg mx-auto">
                        <a href="{{ route('partner.dashboard') }}" class="bottom-nav-item {{ request()->routeIs('partner.dashboard') ? 'active' : 'text-slate-400' }} flex flex-col items-center justify-center gap-0.5 text-[10px] font-medium transition">
                            <i class="fas fa-home text-lg"></i>
                            <span>Home</span>
                        </a>
                        <a href="{{ route('partner.offers') }}" class="bottom-nav-item {{ request()->routeIs('partner.offers*') ? 'active' : 'text-slate-400' }} flex flex-col items-center justify-center gap-0.5 text-[10px] font-medium transition">
                            <i class="fas fa-gift text-lg"></i>
                            <span>Offers</span>
                        </a>
                        <a href="{{ route('partner.payments.scan') }}" class="bottom-nav-item {{ request()->routeIs('partner.payments.scan') ? 'active' : 'text-slate-400' }} flex flex-col items-center justify-center gap-0.5 text-[10px] font-medium transition relative">
                            <div class="w-12 h-12 -mt-5 rounded-full bg-primary-600 text-white grid place-items-center shadow-lg shadow-primary-600/30">
                                <i class="fas fa-qrcode text-lg"></i>
                            </div>
                            <span class="mt-0.5">Scan</span>
                        </a>
                        <a href="{{ route('partner.ads') }}" class="bottom-nav-item {{ request()->routeIs('partner.ads*') ? 'active' : 'text-slate-400' }} flex flex-col items-center justify-center gap-0.5 text-[10px] font-medium transition">
                            <i class="fas fa-bullhorn text-lg"></i>
                            <span>Ads</span>
                        </a>
                        <a href="{{ route('partner.wallet') }}" class="bottom-nav-item {{ request()->routeIs('partner.wallet') ? 'active' : 'text-slate-400' }} flex flex-col items-center justify-center gap-0.5 text-[10px] font-medium transition">
                            <i class="fas fa-wallet text-lg"></i>
                            <span>Wallet</span>
                        </a>
                    </div>
                </nav>
            @endif
        @endauth

        {{-- ========== FOOTER ========== --}}
        @if($landingNav)
            {{-- Shared landing footer — identical to web/home.blade.php --}}
            <footer class="site-footer">
                <div class="container">
                    <div class="footer-top">
                        <div class="footer-brand">
                            <div class="footer-logo">
                                <img src="{{ asset('images/oripori_logo_wordmark.png') }}" alt="Oripori White Logo">
                            </div>
                            <p>
                                A Nepal-first platform connecting people with what&rsquo;s around them&mdash;local information, community insights, safety, places, and businesses.
                            </p>
                        </div>

                        <div>
                            <h4 class="footer-heading">Services</h4>
                            <ul class="footer-links">
                                <li><a href="{{ route('web.places') }}">Places &amp; Trails</a></li>
                                <li><a href="{{ route('web.category', 'hotels') }}">Hotels &amp; Stays</a></li>
                                <li><a href="{{ route('web.category', 'restaurants') }}">Restaurants</a></li>
                                <li><a href="{{ route('web.routes') }}">Curated Routes</a></li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="footer-heading">Company</h4>
                            <ul class="footer-links">
                                <li><a href="#">About Us</a></li>
                                <li><a href="{{ route('partner.register') }}">Partner With Us</a></li>
                                <li><a href="#">Careers</a></li>
                                <li><a href="#">Blog</a></li>
                            </ul>
                        </div>

                        <div>
                            <h4 class="footer-heading">Support</h4>
                            <ul class="footer-links">
                                <li><a href="#">Help Center</a></li>
                                <li><a href="#">Safety</a></li>
                                <li><a href="{{ route('web.legal.show', 'terms') }}">Terms of Service</a></li>
                                <li><a href="{{ route('web.legal.show', 'privacy') }}">Privacy Policy</a></li>
                            </ul>
                        </div>
                    </div>

                    <div class="footer-bottom">
                        <div>&copy; Oripori. All rights reserved.</div>
                    </div>
                </div>
            </footer>
        @else
        <footer class="hidden lg:block bg-primary-900 text-teal-300 text-center text-xs py-4">
            &copy; {{ date('Y') }} Nepal Smart Travel & Local Intelligence Platform — Business Partner Portal
        </footer>
        @endif
    </div>

    {{-- ========== DRAWER JS ========== --}}
    <script>
        function toggleDrawer() {
            const overlay = document.getElementById('drawer-overlay');
            const panel = document.getElementById('drawer-panel');
            const isOpen = panel.classList.contains('open');
            if (isOpen) {
                panel.classList.remove('open');
                panel.classList.add('closed');
                overlay.classList.add('hidden');
                overlay.classList.remove('opacity-100');
                overlay.classList.add('opacity-0');
                document.body.classList.remove('overflow-hidden');
            } else {
                panel.classList.remove('closed');
                panel.classList.add('open');
                overlay.classList.remove('hidden');
                setTimeout(() => { overlay.classList.remove('opacity-0'); overlay.classList.add('opacity-100'); }, 10);
                document.body.classList.add('overflow-hidden');
            }
        }
        // Close drawer on escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const panel = document.getElementById('drawer-panel');
                if (panel && panel.classList.contains('open')) toggleDrawer();
            }
        });
    </script>
    @if($landingNav)
    <script>
        // Mobile navigation toggle — identical to web/home.blade.php
        (function () {
            var toggle = document.getElementById('nav-toggle');
            var menu = document.getElementById('nav-menu');
            if (toggle && menu) {
                toggle.addEventListener('click', function () {
                    var open = menu.classList.toggle('is-open');
                    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                    var icon = toggle.querySelector('i');
                    if (icon) icon.className = open ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
                });
                menu.querySelectorAll('a').forEach(function (link) {
                    link.addEventListener('click', function () {
                        menu.classList.remove('is-open');
                        toggle.setAttribute('aria-expanded', 'false');
                        var icon = toggle.querySelector('i');
                        if (icon) icon.className = 'fa-solid fa-bars';
                    });
                });
            }
        })();
    </script>
    @endif
    @yield('scripts')
</body>
</html>
