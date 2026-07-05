<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            background: #eef2ff;
        }
        .scrollbar-thin::-webkit-scrollbar {
            width: 8px;
        }
        .scrollbar-thin::-webkit-scrollbar-thumb {
            background-color: rgba(148, 163, 184, 0.6);
            border-radius: 9999px;
        }
    </style>
</head>
<body class="min-h-screen text-slate-900 antialiased">
    @php
        $user = Auth::user();
        $isModerator = $user && $user->isModerator();
        $isAdmin = $user && $user->isAdmin();
        $roleLabel = $isAdmin ? 'Admin' : ($isModerator ? 'Moderator' : 'User');

        $pendingCount = \App\Models\Report::where('status', 'pending')->count();

        $menuPerms = \App\Models\Permission::whereNotNull('menu_label')
            ->whereNotNull('route_name')
            ->whereNotNull('menu_icon')
            ->orderBy('menu_order')
            ->get();
    @endphp
    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <aside class="hidden xl:flex flex-col w-72 bg-slate-900 text-slate-100 shadow-xl">
            <div class="px-6 py-5 border-b border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-600 grid place-items-center text-white text-xl shadow-lg">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold">Nepal Admin</h1>
                        <p class="text-xs text-slate-400">Smart Travel Dashboard</p>
                    </div>
                </div>
            </div>
            <nav class="flex-1 overflow-y-auto p-4 space-y-2 scrollbar-thin">
                @foreach($menuPerms as $mp)
                    @can($mp->name)
                    <a href="{{ route($mp->route_name) }}" class="group flex items-center gap-3 rounded-3xl px-4 py-3 transition {{ request()->routeIs($mp->route_name . '*') ? 'bg-indigo-700 text-white shadow-lg' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i class="fas fa-{{ $mp->menu_icon }} w-5 text-center"></i>
                        <span class="font-medium">{{ $mp->menu_label }}</span>
                        @if($mp->name === 'approve_reports' && $pendingCount > 0)
                            <span class="ml-auto rounded-full bg-red-500 px-2.5 py-0.5 text-[11px] font-semibold text-white">{{ $pendingCount }}</span>
                        @endif
                    </a>
                    @endcan
                @endforeach

                <div class="pt-3 border-t border-slate-800">
                    <p class="px-4 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Monetization</p>
                    <a href="{{ route('admin.travel-partners') }}" class="group flex items-center gap-3 rounded-3xl px-4 py-3 transition {{ request()->routeIs('admin.travel-partners*') ? 'bg-indigo-700 text-white shadow-lg' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i class="fas fa-handshake w-5 text-center"></i>
                        <span class="font-medium">Travel Partners</span>
                    </a>
                    <a href="{{ route('admin.bookings') }}" class="group flex items-center gap-3 rounded-3xl px-4 py-3 transition {{ request()->routeIs('admin.bookings*') ? 'bg-indigo-700 text-white shadow-lg' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i class="fas fa-calendar-check w-5 text-center"></i>
                        <span class="font-medium">Bookings & Commissions</span>
                    </a>
                    <a href="{{ route('admin.subscription.plans') }}" class="group flex items-center gap-3 rounded-3xl px-4 py-3 transition {{ request()->routeIs('admin.subscription.plans*') ? 'bg-indigo-700 text-white shadow-lg' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i class="fas fa-crown w-5 text-center"></i>
                        <span class="font-medium">Subscription Plans</span>
                    </a>
                    <a href="{{ route('admin.subscription.users') }}" class="group flex items-center gap-3 rounded-3xl px-4 py-3 transition {{ request()->routeIs('admin.subscription.users*') ? 'bg-indigo-700 text-white shadow-lg' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i class="fas fa-users-cog w-5 text-center"></i>
                        <span class="font-medium">Subscribers</span>
                    </a>
                    <a href="{{ route('admin.ad-campaigns') }}" class="group flex items-center gap-3 rounded-3xl px-4 py-3 transition {{ request()->routeIs('admin.ad-campaigns*') ? 'bg-indigo-700 text-white shadow-lg' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i class="fas fa-ad w-5 text-center"></i>
                        <span class="font-medium">Ad Campaigns</span>
                    </a>
                </div>

                <div class="pt-3 border-t border-slate-800">
                    <p class="px-4 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Sponsors & Store</p>
                    <a href="{{ route('admin.sponsors') }}" class="group flex items-center gap-3 rounded-3xl px-4 py-3 transition {{ request()->routeIs('admin.sponsors*') ? 'bg-indigo-700 text-white shadow-lg' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i class="fas fa-handshake w-5 text-center"></i>
                        <span class="font-medium">Sponsors</span>
                    </a>
                    <a href="{{ route('admin.store.items') }}" class="group flex items-center gap-3 rounded-3xl px-4 py-3 transition {{ request()->routeIs('admin.store.items*') ? 'bg-indigo-700 text-white shadow-lg' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i class="fas fa-store w-5 text-center"></i>
                        <span class="font-medium">Reward Items</span>
                    </a>
                    <a href="{{ route('admin.store.orders') }}" class="group flex items-center gap-3 rounded-3xl px-4 py-3 transition {{ request()->routeIs('admin.store.orders*') ? 'bg-indigo-700 text-white shadow-lg' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i class="fas fa-shopping-cart w-5 text-center"></i>
                        <span class="font-medium">Orders</span>
                    </a>
                </div>

                @if(!$isModerator)
                <a href="{{ route('admin.audit-logs') }}" class="group flex items-center gap-3 rounded-3xl px-4 py-3 transition {{ request()->routeIs('admin.audit-logs') ? 'bg-indigo-700 text-white shadow-lg' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                    <i class="fas fa-history w-5 text-center"></i>
                    <span class="font-medium">Activity Log</span>
                </a>

                <div class="pt-3 border-t border-slate-800">
                    <p class="px-4 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">Access Control</p>
                    <a href="{{ route('admin.roles') }}" class="group flex items-center gap-3 rounded-3xl px-4 py-3 transition {{ request()->routeIs('admin.roles*') ? 'bg-indigo-700 text-white shadow-lg' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i class="fas fa-user-tag w-5 text-center"></i>
                        <span class="font-medium">Roles</span>
                    </a>
                    <a href="{{ route('admin.permissions') }}" class="group flex items-center gap-3 rounded-3xl px-4 py-3 transition {{ request()->routeIs('admin.permissions*') ? 'bg-indigo-700 text-white shadow-lg' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
                        <i class="fas fa-key w-5 text-center"></i>
                        <span class="font-medium">Permissions</span>
                    </a>
                </div>
                @endif
            </nav>
            <div class="border-t border-slate-800 px-6 py-4">
                <a href="/" class="flex items-center gap-3 rounded-3xl px-4 py-3 text-slate-300 hover:bg-slate-800 hover:text-white transition">
                    <i class="fas fa-arrow-left w-5 text-center"></i>
                    Back to site
                </a>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="flex items-center gap-3 w-full rounded-3xl px-4 py-3 bg-slate-800 text-slate-300 hover:bg-slate-700 transition">
                        <i class="fas fa-sign-out-alt w-5 text-center"></i>
                        Logout
                    </button>
                </form>
            </div>
        </aside>

        <!-- Mobile header -->
        <div class="md:hidden fixed top-0 left-0 right-0 z-50 bg-indigo-800 text-white p-3 flex items-center justify-between">
            <h1 class="font-bold text-sm">Admin Panel</h1>
            <button onclick="document.getElementById('mobileMenu').classList.toggle('hidden')" class="text-white">
                <i class="fas fa-bars text-xl"></i>
            </button>
        </div>
        <div id="mobileMenu" class="md:hidden fixed top-12 left-0 right-0 z-50 bg-indigo-800 text-white hidden">
            <nav class="p-3 space-y-1">
                @foreach($menuPerms as $mp)
                    @can($mp->name)
                    <a href="{{ route($mp->route_name) }}" class="block px-3 py-2 rounded {{ request()->routeIs($mp->route_name . '*') ? 'bg-indigo-700' : '' }}"><i class="fas fa-{{ $mp->menu_icon }} w-5"></i> {{ $mp->menu_label }}</a>
                    @endcan
                @endforeach
                <div class="border-t border-indigo-700 my-2 pt-2">
                    <p class="px-3 text-xs font-semibold text-indigo-300 uppercase tracking-wider mb-1">Monetization</p>
                    <a href="{{ route('admin.travel-partners') }}" class="block px-3 py-2 rounded {{ request()->routeIs('admin.travel-partners*') ? 'bg-indigo-700' : '' }}"><i class="fas fa-handshake w-5"></i> Travel Partners</a>
                    <a href="{{ route('admin.bookings') }}" class="block px-3 py-2 rounded {{ request()->routeIs('admin.bookings*') ? 'bg-indigo-700' : '' }}"><i class="fas fa-calendar-check w-5"></i> Bookings</a>
                    <a href="{{ route('admin.subscription.plans') }}" class="block px-3 py-2 rounded {{ request()->routeIs('admin.subscription.plans*') ? 'bg-indigo-700' : '' }}"><i class="fas fa-crown w-5"></i> Plans</a>
                    <a href="{{ route('admin.subscription.users') }}" class="block px-3 py-2 rounded {{ request()->routeIs('admin.subscription.users*') ? 'bg-indigo-700' : '' }}"><i class="fas fa-users-cog w-5"></i> Subscribers</a>
                    <a href="{{ route('admin.ad-campaigns') }}" class="block px-3 py-2 rounded {{ request()->routeIs('admin.ad-campaigns*') ? 'bg-indigo-700' : '' }}"><i class="fas fa-ad w-5"></i> Ad Campaigns</a>
                </div>
                <div class="border-t border-indigo-700 my-2 pt-2">
                    <p class="px-3 text-xs font-semibold text-indigo-300 uppercase tracking-wider mb-1">Sponsors & Store</p>
                    <a href="{{ route('admin.sponsors') }}" class="block px-3 py-2 rounded {{ request()->routeIs('admin.sponsors*') ? 'bg-indigo-700' : '' }}"><i class="fas fa-handshake w-5"></i> Sponsors</a>
                    <a href="{{ route('admin.store.items') }}" class="block px-3 py-2 rounded {{ request()->routeIs('admin.store.items*') ? 'bg-indigo-700' : '' }}"><i class="fas fa-store w-5"></i> Reward Items</a>
                    <a href="{{ route('admin.store.orders') }}" class="block px-3 py-2 rounded {{ request()->routeIs('admin.store.orders*') ? 'bg-indigo-700' : '' }}"><i class="fas fa-shopping-cart w-5"></i> Orders</a>
                </div>
                @if(!$isModerator)
                <a href="{{ route('admin.audit-logs') }}" class="block px-3 py-2 rounded {{ request()->routeIs('admin.audit-logs') ? 'bg-indigo-700' : '' }}"><i class="fas fa-history w-5"></i> Activity Log</a>
                <div class="border-t border-indigo-700 my-2 pt-2">
                    <p class="px-3 text-xs font-semibold text-indigo-300 uppercase tracking-wider mb-1">Access Control</p>
                    <a href="{{ route('admin.roles') }}" class="block px-3 py-2 rounded {{ request()->routeIs('admin.roles*') ? 'bg-indigo-700' : '' }}"><i class="fas fa-user-tag w-5"></i> Roles</a>
                    <a href="{{ route('admin.permissions') }}" class="block px-3 py-2 rounded {{ request()->routeIs('admin.permissions*') ? 'bg-indigo-700' : '' }}"><i class="fas fa-key w-5"></i> Permissions</a>
                </div>
                @endif
                <hr class="border-indigo-700 my-2">
                <a href="/" class="block px-3 py-2"><i class="fas fa-arrow-left w-5"></i> Back to Site</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block px-3 py-2 w-full text-left"><i class="fas fa-sign-out-alt w-5"></i> Logout</button>
                </form>
            </nav>
        </div>

        <!-- Main content -->
        <div class="flex-1 flex flex-col overflow-hidden pt-12 md:pt-0">
            <!-- Top bar -->
            <header class="sticky top-0 z-30 bg-white border-b border-slate-200 px-5 py-4 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div class="flex items-center gap-3">
                        <button class="xl:hidden text-slate-700 p-2 rounded-2xl bg-slate-100" onclick="document.getElementById('mobileMenu').classList.toggle('hidden')">
                            <i class="fas fa-bars"></i>
                        </button>
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">@yield('title', 'Dashboard')</h2>
                            <p class="text-sm text-slate-500">Overview of your platform health and activity.</p>
                        </div>
                    </div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                        <div class="hidden sm:flex items-center gap-3 bg-slate-100 border border-slate-200 rounded-3xl px-4 py-2">
                            <i class="fas fa-search text-slate-400"></i>
                            <input type="search" placeholder="Search reports, users, places" class="bg-transparent outline-none text-sm text-slate-700 w-72" />
                        </div>
                        <button class="h-11 w-11 rounded-2xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition grid place-items-center">
                            <i class="fas fa-bell"></i>
                        </button>
                        <div class="flex items-center gap-3 rounded-3xl border border-slate-200 bg-white px-4 py-2 shadow-sm">
                            <span class="h-10 w-10 rounded-full bg-indigo-600 grid place-items-center text-white">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                            <div class="text-left">
                                <p class="text-sm font-semibold text-slate-900">{{ $user->name }}</p>
                                <p class="text-xs {{ $isAdmin ? 'text-indigo-600' : 'text-amber-600' }} font-semibold">{{ $roleLabel }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Flash messages -->
            @if(session('success'))
                <div class="mx-6 mt-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg flex items-center gap-2">
                    <i class="fas fa-check-circle"></i>
                    <span>{{ session('success') }}</span>
                    <button onclick="this.parentElement.remove()" class="ml-auto text-green-500 hover:text-green-700"><i class="fas fa-times"></i></button>
                </div>
            @endif
            @if(session('error'))
                <div class="mx-6 mt-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg flex items-center gap-2">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>{{ session('error') }}</span>
                    <button onclick="this.parentElement.remove()" class="ml-auto text-red-500 hover:text-red-700"><i class="fas fa-times"></i></button>
                </div>
            @endif

            <!-- Page content -->
            <main class="flex-1 overflow-y-auto p-6">
                @yield('content')
            </main>
        </div>
    </div>
    @yield('scripts')
</body>
</html>
