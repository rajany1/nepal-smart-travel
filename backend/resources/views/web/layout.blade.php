<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Nepal Smart Travel & Local Intelligence Platform — discover places, live road conditions, routes, and exclusive local offers.">
    <title>@yield('title', 'Nepal Smart Travel & Local Intelligence')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
    <style>
        /* Shared landing navbar/footer design system — identical to web/home.blade.php */
        @include('web.partials.site_chrome_css')
        body { padding-top: var(--header-height); }
    </style>
    @stack('head')
</head>
<body class="bg-slate-50 min-h-screen flex flex-col font-sans">
    {{-- Shared landing navbar — identical to web/home.blade.php --}}
    @include('web.partials.site_header')

    <main class="flex-1">
        @yield('content')
    </main>

    {{-- Shared landing footer — identical to web/home.blade.php --}}
    @include('web.partials.site_footer')

    <script>
        // App detection: visiting via the app's webview sets this cookie -> premium features unlock
        (function () {
            const cookie = document.cookie.split(';').find(c => c.trim().startsWith('nst_app='));
            if (new URLSearchParams(window.location.search).has('app')) {
                document.cookie = 'nst_app=1; path=/; max-age=31536000';
            }
        })();
    </script>
    @include('web.partials.site_nav_js')
    @stack('scripts')
</body>
</html>
