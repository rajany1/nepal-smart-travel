<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Nepal Smart Travel & Local Intelligence Platform — discover places, live road conditions, routes, and exclusive local offers.">
    <title>@yield('title', 'Nepal Smart Travel & Local Intelligence')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { 50: '#f0fdfa', 100: '#ccfbf1', 500: '#0d9488', 600: '#0f766e', 700: '#115e59', 800: '#134e4a', 900: '#042f2e' },
                        accent: { 400: '#fbbf24', 500: '#f59e0b', 600: '#d97706' },
                    }
                }
            }
        }
    </script>
    @stack('head')
</head>
<body class="bg-slate-50 min-h-screen flex flex-col font-sans">
    <header class="bg-primary-900 text-white shadow-lg sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="flex items-center justify-between h-16">
                <a href="{{ route('web.home') }}" class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-accent-500 grid place-items-center text-xl"><i class="fas fa-mountain"></i></div>
                    <div class="leading-tight">
                        <div class="font-bold">Nepal Smart Travel</div>
                        <div class="text-[11px] text-teal-300">Local Intelligence Platform</div>
                    </div>
                </a>
                <nav class="hidden md:flex items-center gap-6 text-sm text-teal-100">
                    <a href="{{ route('web.places') }}" class="hover:text-white">Places</a>
                    <a href="{{ route('web.category', 'hotels') }}" class="hover:text-white">Hotels</a>
                    <a href="{{ route('web.category', 'restaurants') }}" class="hover:text-white">Restaurants</a>
                    <a href="{{ route('web.category', 'attractions') }}" class="hover:text-white">Attractions</a>
                    <a href="{{ route('web.routes') }}" class="hover:text-white">Routes</a>
                    <a href="{{ route('web.offers') }}" class="hover:text-white">Offers</a>
                    <a href="{{ route('partner.register') }}" class="hover:text-white">Partner</a>
                </nav>
                <a href="{{ env('PLAY_STORE_URL', '#') }}" class="inline-flex items-center gap-2 bg-accent-500 hover:bg-accent-600 text-white text-sm font-semibold rounded-xl px-4 py-2 transition">
                    <i class="fas fa-download"></i> Get the App
                </a>
            </div>
        </div>
        <nav class="md:hidden border-t border-primary-800">
            <div class="max-w-7xl mx-auto px-4 flex items-center gap-5 overflow-x-auto text-sm text-teal-100 py-2.5">
                <a href="{{ route('web.places') }}" class="whitespace-nowrap hover:text-white">Places</a>
                <a href="{{ route('web.category', 'hotels') }}" class="whitespace-nowrap hover:text-white">Hotels</a>
                <a href="{{ route('web.category', 'restaurants') }}" class="whitespace-nowrap hover:text-white">Restaurants</a>
                <a href="{{ route('web.category', 'attractions') }}" class="whitespace-nowrap hover:text-white">Attractions</a>
                <a href="{{ route('web.routes') }}" class="whitespace-nowrap hover:text-white">Routes</a>
                <a href="{{ route('web.offers') }}" class="whitespace-nowrap hover:text-white">Offers</a>
                <a href="{{ route('partner.register') }}" class="whitespace-nowrap hover:text-white">Partner</a>
            </div>
        </nav>
    </header>

    <main class="flex-1">
        @yield('content')
    </main>

    <footer class="bg-primary-900 text-teal-300">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-10 grid grid-cols-1 md:grid-cols-4 gap-8 text-sm">
            <div class="md:col-span-2">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-9 h-9 rounded-lg bg-accent-500 grid place-items-center text-white"><i class="fas fa-mountain"></i></div>
                    <div class="font-bold text-white">Nepal Smart Travel</div>
                </div>
                <p class="text-teal-200 leading-relaxed max-w-md">
                    Discover Nepal's hidden gems, real-time road conditions, community reports, and exclusive local offers — all in one place.
                </p>
            </div>
            <div>
                <h4 class="text-white font-semibold mb-3">Explore</h4>
                <ul class="space-y-2">
                    <li><a href="{{ route('web.places') }}" class="hover:text-white">All Places</a></li>
                    <li><a href="{{ route('web.category', 'hotels') }}" class="hover:text-white">Hotels</a></li>
                    <li><a href="{{ route('web.category', 'restaurants') }}" class="hover:text-white">Restaurants</a></li>
                    <li><a href="{{ route('web.category', 'attractions') }}" class="hover:text-white">Attractions</a></li>
                    <li><a href="{{ route('web.routes') }}" class="hover:text-white">Curated Routes</a></li>
                    <li><a href="{{ route('web.offers') }}" class="hover:text-white">Offers & Rewards</a></li>
                    <li class="pt-1"><a href="{{ route('partner.register') }}" class="text-accent-400 font-semibold hover:text-white">For Business — Partner Portal</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-white font-semibold mb-3">Get the App</h4>
                <p class="text-teal-200 mb-3">Live maps, offline reports, rewards — best in the app.</p>
                <a href="{{ env('PLAY_STORE_URL', '#') }}" class="inline-flex items-center gap-2 bg-black/30 hover:bg-black/50 border border-teal-700 rounded-xl px-4 py-2.5 transition">
                    <i class="fab fa-google-play text-xl"></i>
                    <span class="text-xs leading-tight"><span class="block opacity-70">Get it on</span><span class="font-semibold text-white">Google Play</span></span>
                </a>
                <a href="{{ env('APP_STORE_URL', '#') }}" class="mt-2 inline-flex items-center gap-2 bg-black/30 hover:bg-black/50 border border-teal-700 rounded-xl px-4 py-2.5 transition">
                    <i class="fab fa-apple text-xl"></i>
                    <span class="text-xs leading-tight"><span class="block opacity-70">Download on the</span><span class="font-semibold text-white">App Store</span></span>
                </a>
            </div>
        </div>
        <div class="border-t border-primary-800 text-center text-xs py-4 text-teal-400">
            © {{ date('Y') }} Nepal Smart Travel & Local Intelligence Platform
        </div>
    </footer>

    <script>
        // App detection: visiting via the app's webview sets this cookie -> premium features unlock
        (function () {
            const cookie = document.cookie.split(';').find(c => c.trim().startsWith('nst_app='));
            if (new URLSearchParams(window.location.search).has('app')) {
                document.cookie = 'nst_app=1; path=/; max-age=31536000';
            }
        })();
    </script>
    @stack('scripts')
</body>
</html>
