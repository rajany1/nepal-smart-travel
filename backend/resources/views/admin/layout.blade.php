@php
    // TRUE when this request must return ONLY the main content + page scripts
    // (used by the SPA-style sidebar navigation), skipping the full HTML shell.
    $fragment = (request()->query('fragment') === '1');
@endphp
@if($fragment)
    <div id="appPane" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden min-h-[60vh]">
        @yield('content')
    </div>
    @yield('scripts')
@else
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard') - {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            '50': '#E0F2F1', '100': '#B2DFDB', '200': '#80CBC4',
                            '300': '#4DB6AC', '400': '#26A69A', '500': '#009688',
                            '600': '#00897B', '700': '#00796B', '800': '#00695C',
                            '900': '#004D40',
                        },
                        accent: {
                            '50': '#FFF3E0', '100': '#FFE0B2', '200': '#FFCC80',
                            '300': '#FFB74D', '400': '#FFA726', '500': '#F39C12',
                            '600': '#D68910', '700': '#B9770E', '800': '#9A6A0C',
                            '900': '#7B550A',
                        },
                    },
                },
            },
        };
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            background: #FDFBF7;
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
<body data-since="{{ json_encode(\App\Support\LiveFeed::fingerprint()) }}" class="min-h-screen text-slate-900 antialiased">
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
        <aside class="hidden xl:flex flex-col w-72 bg-primary-900 text-teal-100 shadow-xl shrink-0 border-r border-primary-950 xl:sticky xl:top-0 xl:h-screen">
            <div class="px-6 py-5 border-b border-primary-800">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-accent-500 grid place-items-center text-white text-xl shadow-lg">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div>
                        <h1 class="text-xl font-semibold">Nepal Admin</h1>
                        <p class="text-xs text-teal-300">Smart Travel Dashboard</p>
                    </div>
                </div>
            </div>
            @php
                $menuGroups = [
                    'main' => ['label' => null, 'admin_only' => false],
                    'monetization' => ['label' => 'Monetization', 'admin_only' => false],
                    'store' => ['label' => 'Sponsors & Store', 'admin_only' => false],
                    'access' => ['label' => 'Access Control', 'admin_only' => true],
                ];
            @endphp
            <nav class="flex-1 overflow-y-auto p-4 space-y-2 scrollbar-thin">
                @foreach($menuGroups as $group => $cfg)
                    @php $groupPerms = $menuPerms->where('menu_group', $group); @endphp
                    @if($groupPerms->isNotEmpty() && (!$cfg['admin_only'] || !$isModerator))
                        @if($cfg['label'])
                        <div class="pt-3 border-t border-primary-800">
                            <p class="px-4 text-xs font-semibold text-teal-400 uppercase tracking-wider mb-2">{{ $cfg['label'] }}</p>
                        </div>
                        @endif
                        @foreach($groupPerms as $mp)
                            @can($mp->name)
                            <a href="{{ route($mp->route_name) }}" class="group flex items-center gap-3 rounded-3xl px-4 py-3 transition {{ request()->routeIs($mp->route_name . '*') ? 'bg-accent-500 text-white shadow-lg' : 'text-teal-200 hover:bg-primary-800 hover:text-white' }}">
                                <i class="fas fa-{{ $mp->menu_icon }} w-5 text-center"></i>
                                <span class="font-medium">{{ $mp->menu_label }}</span>
                                @if($mp->name === 'approve_reports' && $pendingCount > 0)
                                    <span class="ml-auto rounded-full bg-red-500 px-2.5 py-0.5 text-[11px] font-semibold text-white">{{ $pendingCount }}</span>
                                @endif
                            </a>
                            @endcan
                        @endforeach
                    @endif
                @endforeach

                <!-- AI Agents - hardcoded link -->
                <div class="pt-3 border-t border-primary-800">
                    <p class="px-4 text-xs font-semibold text-teal-400 uppercase tracking-wider mb-2">AI</p>
                </div>
                <a href="{{ route('admin.ai.agents') }}" class="group flex items-center gap-3 rounded-3xl px-4 py-3 transition {{ request()->routeIs('admin.ai.agents*') ? 'bg-accent-500 text-white shadow-lg' : 'text-teal-200 hover:bg-primary-800 hover:text-white' }}">
                    <i class="fas fa-robot w-5 text-center"></i>
                    <span class="font-medium">AI Employees</span>
                </a>
                <a href="{{ route('admin.ai.tasks') }}" class="group flex items-center gap-3 rounded-3xl px-4 py-3 transition {{ request()->routeIs('admin.ai.tasks*') ? 'bg-accent-500 text-white shadow-lg' : 'text-teal-200 hover:bg-primary-800 hover:text-white' }}">
                    <i class="fas fa-tasks w-5 text-center"></i>
                    <span class="font-medium">AI Tasks</span>
                </a>
                <a href="{{ route('admin.translator') }}" class="group flex items-center gap-3 rounded-3xl px-4 py-3 transition {{ request()->routeIs('admin.translator*') ? 'bg-accent-500 text-white shadow-lg' : 'text-teal-200 hover:bg-primary-800 hover:text-white' }}">
                    <i class="fas fa-language w-5 text-center"></i>
                    <span class="font-medium">Translator</span>
                </a>
            </nav>
            <div class="border-t border-primary-800 px-6 py-4">
                <a href="/" class="flex items-center gap-3 rounded-3xl px-4 py-3 text-teal-200 hover:bg-primary-800 hover:text-white transition">
                    <i class="fas fa-arrow-left w-5 text-center"></i>
                    Back to site
                </a>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="flex items-center gap-3 w-full rounded-3xl px-4 py-3 bg-primary-800 text-teal-200 hover:bg-primary-700 transition">
                        <i class="fas fa-sign-out-alt w-5 text-center"></i>
                        Logout
                    </button>
                </form>
            </div>
        </aside>

        <!-- Mobile header -->
        <div class="md:hidden fixed top-0 left-0 right-0 z-50 bg-primary-800 text-white p-3 flex items-center justify-between">
            <h1 class="font-bold text-sm">Admin Panel</h1>
            <button onclick="document.getElementById('mobileMenu').classList.toggle('hidden')" class="text-white">
                <i class="fas fa-bars text-xl"></i>
            </button>
        </div>
        <div id="mobileMenu" class="md:hidden fixed top-12 left-0 right-0 z-50 bg-primary-800 text-white hidden">
            <nav class="p-3 space-y-1">
                @foreach($menuGroups as $group => $cfg)
                    @php $groupPerms = $menuPerms->where('menu_group', $group); @endphp
                    @if($groupPerms->isNotEmpty() && (!$cfg['admin_only'] || !$isModerator))
                        @if($cfg['label'])
                        <div class="border-t border-primary-700 my-2 pt-2">
                            <p class="px-3 text-xs font-semibold text-accent-300 uppercase tracking-wider mb-1">{{ $cfg['label'] }}</p>
                        </div>
                        @endif
                        @foreach($groupPerms as $mp)
                            @can($mp->name)
                            <a href="{{ route($mp->route_name) }}" class="block px-3 py-2 rounded {{ request()->routeIs($mp->route_name . '*') ? 'bg-primary-700' : '' }}"><i class="fas fa-{{ $mp->menu_icon }} w-5"></i> {{ $mp->menu_label }}</a>
                            @endcan
                        @endforeach
                    @endif
                @endforeach
                <div class="border-t border-primary-700 my-2 pt-2">
                    <p class="px-3 text-xs font-semibold text-accent-300 uppercase tracking-wider mb-1">AI</p>
                </div>
                <a href="{{ route('admin.ai.agents') }}" class="block px-3 py-2 rounded {{ request()->routeIs('admin.ai.agents*') ? 'bg-primary-700' : '' }}"><i class="fas fa-robot w-5"></i> AI Employees</a>
                <a href="{{ route('admin.ai.tasks') }}" class="block px-3 py-2 rounded {{ request()->routeIs('admin.ai.tasks*') ? 'bg-primary-700' : '' }}"><i class="fas fa-tasks w-5"></i> AI Tasks</a>
                <a href="{{ route('admin.translator') }}" class="block px-3 py-2 rounded {{ request()->routeIs('admin.translator*') ? 'bg-primary-700' : '' }}"><i class="fas fa-language w-5"></i> Translator</a>
                <hr class="border-primary-700 my-2">
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
                            <span class="h-10 w-10 rounded-full bg-accent-500 grid place-items-center text-white">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                            <div class="text-left">
                                <p class="text-sm font-semibold text-slate-900">{{ $user->name }}</p>
                                <p class="text-xs {{ $isAdmin ? 'text-accent-500' : 'text-amber-600' }} font-semibold">{{ $roleLabel }}</p>
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
                <div id="appPane" class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden min-h-[60vh]">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
    @yield('scripts')
<script>
(function () {
    'use strict';
    if (window.__liveFeedStarted) return;
    window.__liveFeedStarted = true;

    var POLL_MS = 10000;
    var TTL = { places: 1, place_reviews: 1, reports: 1, report_comments: 1, alerts: 1, users: 1, reward_offers: 1, offer_redemptions: 1, ad_campaigns: 1, bookings: 1, payouts: 1, audit_logs: 1, place_corrections: 1, travel_partners: 1, user_subscriptions: 1, subscription_plans: 1, roles: 1, permissions: 1, achievements: 1, curated_routes: 1, ai_agents: 1, ai_agent_tasks: 1, translation_glossary: 1 };
    var dirty = false;
    var applying = false;
    var chip = null;

    // Dirty-form guard: never touch the page while the admin is typing.
    document.addEventListener('input', function () { dirty = true; }, true);
    document.addEventListener('change', function () { dirty = true; }, true);

    function baseSince() {
        try { return JSON.parse(document.body.getAttribute('data-since') || '{}'); } catch (e) { return {}; }
    }
    function storedDels() {
        try { return JSON.parse(sessionStorage.getItem('lf_dels') || '{}'); } catch (e) { return {}; }
    }
    function storeDels(d) {
        try {
            var cap = {};
            Object.keys(d).forEach(function (k) { cap[k] = d[k].slice(-100); });
            sessionStorage.setItem('lf_dels', JSON.stringify(cap));
        } catch (e) {}
    }

    function buildUrl(extra) {
        var href = location.href.split('#')[0];
        return href + (href.indexOf('?') >= 0 ? '&' : '?') + extra;
    }

    function showChip(count) {
        if (!chip) {
            chip = document.createElement('div');
            chip.id = 'liveFeedChip';
            chip.style.cssText = 'position:fixed;right:20px;bottom:20px;z-index:9999;display:flex;align-items:center;gap:8px;background:#00695C;color:#fff;padding:10px 16px;border-radius:9999px;box-shadow:0 6px 20px rgba(0,0,0,.25);font-size:13px;cursor:pointer;';
            chip.addEventListener('click', function () { location.reload(); });
            document.body.appendChild(chip);
        }
        chip.innerHTML = '<i class="fas fa-sync-alt"></i> ' + count + ' update' + (count === 1 ? '' : 's') + ' ΓÇö Apply';
    }
    function hideChip() { if (chip) chip.remove(); chip = null; }

    // ---- Surgical list interactions (AJAX, table-only replace) ----
    function applyParsed(html, url) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var fresh = doc.getElementById('liveTable');
        var cur = document.getElementById('liveTable');
        var ok = !!(fresh && cur);
        if (ok) cur.innerHTML = fresh.innerHTML;
        var fs = doc.body ? doc.body.getAttribute('data-since') : null;
        if (fs) { try { document.body.setAttribute('data-since', fs); } catch (e) {} }
        dirty = false;
        if (url) url = url.replace(/[?&]fragment=1$/, '');
        if (url && url !== location.href) { try { history.replaceState(null, '', url); } catch (e) {} }
        return ok;
    }

    function ajaxGet(url) {
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { if (!r.ok) throw new Error('bad'); return r.text(); })
            .then(function (html) { if (!applyParsed(html, url)) location.href = url; })
            .catch(function () { location.href = url; });
    }

    function ajaxPost(form) {
        var action = form.getAttribute('action') || location.href;
        var fd = new FormData(form);
        fetch(action, {
            method: form.getAttribute('method') || 'POST',
            body: fd, redirect: 'follow',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { if (!r.ok) throw new Error('bad'); return r.text().then(function (html) { return { html: html, url: r.url }; }); })
            .then(function (res) {
                var clean = res.url.split('#')[0];
                if (!applyParsed(res.html, clean)) location.href = clean;
            })
            .catch(function () { location.href = location.href; });
    }

    // Same-page links inside the live table (pagination, sort, status tabs) ΓåÆ AJAX, no page render.
    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (!a || !document.getElementById('liveTable') || !a.closest('#liveTable')) return;
        var href = a.getAttribute('href') || '';
        if (href.charAt(0) === '#') return;
        try {
            var u = new URL(a.href, location.origin);
            if (u.pathname !== location.pathname) return;
        } catch (err) { return; }
        e.preventDefault();
        e.stopPropagation();
        ajaxGet(a.href + (a.href.indexOf('?') >= 0 ? '&' : '?') + 'fragment=1');
    }, true);

    // Forms on list pages (actions, search, bulk, categories) ΓåÆ AJAX, table-only replace.
    // (Bubble phase so inline onsubmit confirm() runs first.)
    document.addEventListener('submit', function (e) {
        if (e.defaultPrevented) return;
        var f = e.target;
        if (!f || f.tagName !== 'FORM') return;
        if (!document.getElementById('liveTable')) return;
        if (f.closest('[id$="Modal"]')) return;
        e.preventDefault();
        e.stopPropagation();
        var m = (f.getAttribute('method') || 'POST').toUpperCase();
        if (m === 'GET') {
            var q = new URLSearchParams(new FormData(f)).toString();
            var base = f.getAttribute('action') || location.pathname;
            var sep = base.indexOf('?') >= 0 ? '&' : '?';
            ajaxGet(base + sep + q + (q ? '&' : '') + 'fragment=1');
        } else {
            ajaxPost(f);
        }
    });

    function applyTableFragment() {
        if (applying) return;
        applying = true;
        fetch(buildUrl('fragment=1'), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { if (!r.ok) throw new Error('bad'); return r.text(); })
            .then(function (html) {
                if (!applyParsed(html)) location.reload();
            })
            .catch(function () {})
            .finally(function () { applying = false; });
    }

    function applyDashboardStats() {
        if (applying) return;
        applying = true;
        fetch('/admin/live-feed/stats', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) return;
                var s = res.stats;
                document.querySelectorAll('[data-count]').forEach(function (el) {
                    var k = el.getAttribute('data-count');
                    if (s[k] === undefined || s[k] === null) return;
                    if (k === 'system_health_status') {
                        el.textContent = s[k];
                        return;
                    }
                    var prefix = k === 'ads_income' ? '$' : '';
                    var suffix = (k === 'operations_efficiency' || k === 'system_health_score') ? '%'
                        : (k === 'analytics_score') ? ' / 100'
                        : (k.indexOf('xp_') === 0) ? ' XP'
                        : '';
                    el.textContent = prefix + Number(s[k]).toLocaleString() + suffix;
                });
                dirty = false;
            })
            .catch(function () {})
            .finally(function () { applying = false; });
    }

    function applyMapDelta(changes) {
        var evt = new CustomEvent('livefeed:change', { detail: { changes: changes } });
        window.dispatchEvent(evt);
    }

    function isTyping() {
        var el = document.activeElement;
        if (el && (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.tagName === 'SELECT')) return true;
        return dirty;
    }

    function poll() {
        if (document.visibilityState === 'hidden') return;
        var since = baseSince();
        var dels = storedDels();
        var q = 'since=' + encodeURIComponent(JSON.stringify(since)) + '&dels_seen=' + encodeURIComponent(JSON.stringify(dels));
        fetch('/admin/live-feed/changes?' + q, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) return;
                var changes = res.changes || {};
                var keys = Object.keys(changes);
                if (keys.length === 0) { hideChip(); return; }

                // Track deleted ids the client has applied.
                var appliedDels = Object.assign({}, dels);
                keys.forEach(function (t) {
                    if (changes[t].deleted.length) {
                        appliedDels[t] = (appliedDels[t] || []).concat(changes[t].deleted);
                    }
                });
                storeDels(appliedDels);

                // Advance the fingerprint for the next poll.
                try { document.body.setAttribute('data-since', JSON.stringify(res.fp)); } catch (e) {}

                if (isTyping()) return; // wait for next poll ΓÇö never disturb a typing admin

                var hasTable = !!document.getElementById('liveTable');
                var hasMap = !!document.getElementById('liveMap');
                var hasStats = !!document.querySelector('[data-count]');

                if (hasMap) {
                    applyMapDelta(changes);
                } else if (hasTable) {
                    applyTableFragment();
                } else if (hasStats) {
                    applyDashboardStats();
                } else {
                    showChip(keys.length);
                }
            })
            .catch(function () {});
    }

    setInterval(poll, POLL_MS);
    setTimeout(poll, 3000);
})();
</script>

<script>
(function () {
    'use strict';
    // SPA-style sidebar navigation: navbar switch updates ONLY #appPane.
    // EXEMPT: /admin/live-map does a normal full-page load.
    if (window.__spaNavStarted) return;
    window.__spaNavStarted = true;

    function ensurePane() {
        return document.getElementById('appPane') || document.querySelector('main');
    }
    function isNavLink(a) {
        var aside = document.querySelector('aside nav');
        var mob = document.getElementById('mobileMenu');
        if ((aside && aside.contains(a)) || (mob && mob.contains(a))) return true;
        return a.hasAttribute('data-spa');
    }
    function isExempt(url) {
        return /\/admin\/live-map/.test(url);
    }
    function runScripts(root) {
        if (!root) return;
        root.querySelectorAll('script').forEach(function (old) {
            var n = document.createElement('script');
            if (old.src) { n.src = old.src; } else { n.textContent = old.textContent; }
            old.parentNode.replaceChild(n, old);
        });
    }
    function paneAndScripts(doc, pane) {
        var out = [];
        if (pane) out.push(pane.innerHTML);
        Array.prototype.forEach.call(doc.querySelectorAll('head link[rel="stylesheet"], head style'), function (s) {
            out.push(s.outerHTML);
        });
        Array.prototype.forEach.call(doc.querySelectorAll('body > script'), function (s) {
            if (s.src) { out.push('<script src="' + s.src + '"></' + 'script>'); }
            else { out.push(s.outerHTML); }
        });
        return out.join('\n');
    }
    function setActive(href) {
        var path = (href || '').split('?')[0];
        document.querySelectorAll('aside nav a').forEach(function (a) {
            var ap = (a.getAttribute('href') || '').split('?')[0];
            var active = ap.length > 1 && path.indexOf(ap) === 0;
            if (active) { a.classList.add('bg-accent-500', 'text-white', 'shadow-lg'); a.classList.remove('text-teal-200', 'hover:bg-primary-800'); }
            else { a.classList.remove('bg-accent-500', 'text-white', 'shadow-lg'); a.classList.add('text-teal-200'); }
        });
    }
    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (!a || !isNavLink(a)) return;
        var href = a.getAttribute('href') || '';
        if (!href || href.charAt(0) === '#' || href === '/') return;
        if (href.indexOf('logout') >= 0) return;
        if (isExempt(a.href)) return;
        var tgt = ensurePane();
        if (!tgt) return;
        e.preventDefault();
        var url = a.href;
        tgt.style.opacity = '.4';
        history.pushState({ url: url }, '', url);
        fetch(url + (url.indexOf('?') >= 0 ? '&' : '?') + 'fragment=1', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { if (!r.ok) throw new Error('bad'); return r.text(); })
          .then(function (html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var fresh = doc.getElementById('appPane') || doc.querySelector('#appPane') || doc.querySelector('main');
            if (!fresh) { window.location.href = url; return; }
            tgt.innerHTML = paneAndScripts(doc, fresh);
            runScripts(tgt);
            var title = doc.querySelector('title');
            if (title) document.title = title.textContent;
            var since = doc.body ? doc.body.getAttribute('data-since') : null;
            if (since) document.body.setAttribute('data-since', since);
            setActive(url);
            tgt.style.opacity = '1';
            tgt.scrollTop = 0;
            window.scrollTo(0, 0);
          })
          .catch(function () { tgt.style.opacity = '1'; window.location.href = url; });
    }, true);
    window.addEventListener('popstate', function () {
        var url = location.href;
        if (isExempt(url)) { location.reload(); return; }
        var tgt = ensurePane();
        if (!tgt) return;
        fetch(url + (url.indexOf('?') >= 0 ? '&' : '?') + 'fragment=1', {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { return r.text(); })
          .then(function (html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            var fresh = doc.getElementById('appPane') || doc.querySelector('main');
            if (!fresh) return;
            tgt.innerHTML = paneAndScripts(doc, fresh);
            runScripts(tgt);
            var title = doc.querySelector('title');
            if (title) document.title = title.textContent;
            setActive(url);
          })
          .catch(function () { location.reload(); });
    });
})();
</script>
</body>
</html>
@endif
