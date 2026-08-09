<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Business Partner Portal') — Nepal Smart Travel</title>
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
</head>
<body class="bg-slate-50 min-h-screen">
    <div class="min-h-screen flex flex-col">
        <header class="bg-primary-900 text-white shadow-lg sticky top-0 z-40">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-accent-500 grid place-items-center text-lg">
                        <i class="fas fa-store"></i>
                    </div>
                    <div>
                        <h1 class="text-lg font-semibold leading-tight">Business Portal</h1>
                        <p class="text-xs text-teal-300">Nepal Smart Travel & Local Intelligence</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    @auth
                        @if(auth()->user()->isBusiness())
                            @php $partner = auth()->user()->business; @endphp
                            @if($partner && $partner->verification_status === 'verified')
                                <span class="hidden sm:inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-full bg-teal-800/60 text-teal-200">
                                    <i class="fas fa-check-circle text-emerald-400"></i> {{ $partner->name }}
                                </span>
                            @elseif($partner && $partner->verification_status === 'pending')
                                <span class="hidden sm:inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-full bg-amber-500/20 text-amber-300">
                                    <i class="fas fa-clock"></i> Awaiting verification
                                </span>
                            @endif
                            <a href="{{ route('partner.offers') }}" class="text-xs text-teal-200 hover:text-white"><i class="fas fa-gift"></i> Offers</a>
                            <a href="{{ route('partner.ads') }}" class="text-xs text-teal-200 hover:text-white"><i class="fas fa-bullhorn"></i> Ads</a>
                            <a href="{{ route('partner.payouts') }}" class="text-xs text-teal-200 hover:text-white"><i class="fas fa-hand-holding-usd"></i> Payouts</a>
                            <a href="{{ route('partner.dashboard') }}" class="text-xs text-teal-200 hover:text-white"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                            <form method="POST" action="{{ route('partner.logout') }}" class="inline">
                                @csrf
                                <button class="text-xs text-teal-200 hover:text-white"><i class="fas fa-sign-out-alt"></i> Logout</button>
                            </form>
                        @endif
                    @endauth
                </div>
            </div>
        </header>

        @if(session('success'))
            <div class="max-w-7xl mx-auto px-4 sm:px-6 mt-4">
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
                    <i class="fas fa-check-circle"></i> {{ session('success') }}
                </div>
            </div>
        @endif
        @if(session('error'))
            <div class="max-w-7xl mx-auto px-4 sm:px-6 mt-4">
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
                    <i class="fas fa-exclamation-circle"></i> {{ session('error') }}
                </div>
            </div>
        @endif
        @if(session('info'))
            <div class="max-w-7xl mx-auto px-4 sm:px-6 mt-4">
                <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
                    <i class="fas fa-info-circle"></i> {{ session('info') }}
                </div>
            </div>
        @endif

        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 py-6">
            @yield('content')
        </main>

        <footer class="bg-primary-900 text-teal-300 text-center text-xs py-4">
            © {{ date('Y') }} Nepal Smart Travel & Local Intelligence Platform — Business Partner Portal
        </footer>
    </div>
</body>
</html>
