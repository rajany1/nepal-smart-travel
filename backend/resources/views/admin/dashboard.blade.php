@extends('admin.layout')
@section('title', 'Dashboard')

@section('content')
    <div class="mb-6 grid gap-6 lg:grid-cols-[2fr_1fr]">
        <div class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-medium text-slate-500">Dashboard overview</p>
                    <h1 class="text-2xl font-semibold text-slate-900">Welcome back, {{ Auth::user()->name }}.</h1>
                </div>
                <div class="inline-flex items-center gap-2 rounded-3xl bg-slate-100 px-4 py-3 text-sm text-slate-600">
                    <span class="h-8 w-8 rounded-2xl bg-indigo-600 text-white grid place-items-center"><i class="fas fa-rocket"></i></span>
                    <span>System in good health</span>
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="inline-flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-3xl bg-indigo-600 text-white"><i class="fas fa-users"></i></span>
                            <div class="min-w-0 w-full sm:w-auto">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Users</p>
                                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ number_format($stats['total_users']) }}</p>
                                <p class="mt-2 text-sm text-slate-500">Active users registered</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $stats['users_change_pct'] >= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                            <i class="fas fa-arrow-{{ $stats['users_change_pct'] >= 0 ? 'up' : 'down' }} mr-1"></i>
                            {{ abs($stats['users_change_pct']) }}%
                        </span>
                    </div>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                        <div class="flex min-w-0 w-full items-center gap-3">
                            <span class="inline-flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-3xl bg-sky-600 text-white"><i class="fas fa-flag"></i></span>
                            <div class="min-w-0 w-full sm:w-auto">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Reports</p>
                                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ number_format($stats['total_reports']) }}</p>
                                <p class="mt-2 text-sm text-slate-500">Total reports submitted</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $stats['reports_change_pct'] >= 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                            <i class="fas fa-arrow-{{ $stats['reports_change_pct'] >= 0 ? 'up' : 'down' }} mr-1"></i>
                            {{ abs($stats['reports_change_pct']) }}%
                        </span>
                    </div>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                        <div class="flex min-w-0 w-full items-center gap-3">
                            <span class="inline-flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-3xl bg-amber-600 text-white"><i class="fas fa-hourglass-half"></i></span>
                            <div class="min-w-0 w-full sm:w-auto">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Pending</p>
                                <p class="mt-3 text-3xl font-semibold text-amber-600">{{ number_format($stats['pending_reports']) }}</p>
                                <p class="mt-2 text-sm text-slate-500">Reports waiting review</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $stats['pending_change_pct'] >= 0 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                            <i class="fas fa-arrow-{{ $stats['pending_change_pct'] >= 0 ? 'up' : 'down' }} mr-1"></i>
                            {{ abs($stats['pending_change_pct']) }}%
                        </span>
                    </div>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                        <div class="flex min-w-0 w-full items-center gap-3">
                            <span class="inline-flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-3xl bg-rose-600 text-white"><i class="fas fa-bell"></i></span>
                            <div class="min-w-0 w-full sm:w-auto">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Alerts</p>
                                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ number_format($stats['total_alerts']) }}</p>
                                <p class="mt-2 text-sm text-slate-500">Active alerts today</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $stats['alerts_change_pct'] >= 0 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                            <i class="fas fa-arrow-{{ $stats['alerts_change_pct'] >= 0 ? 'up' : 'down' }} mr-1"></i>
                            {{ abs($stats['alerts_change_pct']) }}%
                        </span>
                    </div>
                </div>
            </div>
            <div class="mt-6 grid gap-4 grid-cols-1 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5 shadow-sm">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex w-full items-center gap-3">
                            <span class="inline-flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-indigo-600 text-white"><i class="fas fa-cogs"></i></span>
                            <div class="min-w-0 w-full sm:w-auto">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Operations</p>
                                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $stats['operations_efficiency'] }}%</p>
                                <p class="mt-2 text-sm text-slate-500">Efficiency across approvals and reviews</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5 shadow-sm">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex w-full items-center gap-3">
                            <span class="inline-flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-sky-600 text-white"><i class="fas fa-chart-bar"></i></span>
                            <div class="min-w-0 w-full sm:w-auto">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Analytics</p>
                                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $stats['analytics_score'] }} / 100</p>
                                <p class="mt-2 text-sm text-slate-500">Data readiness and activity score</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5 shadow-sm">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex w-full items-center gap-3">
                            <span class="inline-flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-white"><i class="fas fa-dollar-sign"></i></span>
                            <div class="min-w-0 w-full sm:w-auto">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Ads income</p>
                                <p class="mt-3 text-3xl font-semibold text-slate-900">${{ number_format($stats['ads_income']) }}</p>
                                <p class="mt-2 text-sm text-slate-500">Projected monthly revenue</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5 shadow-sm">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex w-full items-center gap-3">
                            <span class="inline-flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-rose-600 text-white"><i class="fas fa-heartbeat"></i></span>
                            <div class="min-w-0 w-full sm:w-auto">
                                <p class="text-xs uppercase tracking-[0.2em] text-slate-400">System Health</p>
                                <p class="mt-3 text-3xl font-semibold text-slate-900">{{ $stats['system_health_score'] }}%</p>
                                <p class="mt-2 text-sm text-slate-500">{{ $stats['system_health_status'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-6 rounded-3xl border border-slate-200 bg-slate-50 p-5 shadow-sm">
                <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Report activity</p>
                        <h3 class="text-lg font-semibold text-slate-900">Weekly submissions</h3>
                    </div>
                    <span class="rounded-full bg-indigo-100 px-3 py-1 text-sm font-semibold text-indigo-700">7-day trend</span>
                </div>
                <div class="mt-5">
                    <canvas id="reportsTrendChart" class="min-h-[260px] w-full"></canvas>
                </div>
                <div class="mt-5 grid gap-3 sm:grid-cols-3">
                    <div class="rounded-3xl bg-white p-4 shadow-sm">
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Today</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($stats['chart_values'][6] ?? 0) }}</p>
                    </div>
                    <div class="rounded-3xl bg-white p-4 shadow-sm">
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Weekly peak</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format(max($stats['chart_values'])) }}</p>
                    </div>
                    <div class="rounded-3xl bg-white p-4 shadow-sm">
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Average</p>
                        <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format(round(collect($stats['chart_values'])->avg())) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-500">XP metrics</p>
                        <h2 class="text-lg font-semibold text-slate-900">Game progress</h2>
                    </div>
                    <span class="rounded-3xl bg-indigo-100 px-3 py-2 text-sm font-semibold text-indigo-700">Live</span>
                </div>
                <div class="mt-6 space-y-4">
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm text-slate-500">Report approval XP</p>
                                <p class="mt-1 text-xl font-semibold text-slate-900">{{ $stats['xp_rates']['report_approval_xp'] }} XP</p>
                            </div>
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-600 text-white"><i class="fas fa-check"></i></span>
                        </div>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm text-slate-500">Alert post XP</p>
                                <p class="mt-1 text-xl font-semibold text-slate-900">{{ $stats['xp_rates']['alert_post_xp'] }} XP</p>
                            </div>
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-amber-600 text-white"><i class="fas fa-bell"></i></span>
                        </div>
                    </div>
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-sm text-slate-500">Review XP</p>
                                <p class="mt-1 text-xl font-semibold text-slate-900">{{ $stats['xp_rates']['review_xp'] }} XP</p>
                            </div>
                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-600 text-white"><i class="fas fa-star"></i></span>
                        </div>
                    </div>
                </div>
            </div>

            @if($stats['is_moderator'])
            <div class="rounded-3xl border border-amber-200 bg-amber-50 p-6 shadow-sm">
                <div class="flex items-center gap-3 border-b border-amber-200 pb-4">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-600 text-white"><i class="fas fa-user-shield"></i></span>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Moderator Access</h2>
                        <p class="text-sm text-amber-700">Your role: <strong>Moderator</strong></p>
                    </div>
                </div>
                <div class="mt-4">
                    <p class="text-sm font-medium text-slate-700 mb-2">Your permissions:</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($stats['moderator_permissions'] as $perm)
                            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
                                {{ str_replace('_', ' ', ucfirst($perm)) }}
                            </span>
                        @endforeach
                    </div>
                    @if(count($stats['moderator_permissions']) === 0)
                        <p class="text-sm text-slate-500 italic">No specific permissions assigned. Contact an admin.</p>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[1.4fr_1fr]">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-4 border-b border-slate-200 pb-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Recent Reports</h2>
                    <p class="text-sm text-slate-500">Most recent submissions waiting review.</p>
                </div>
                <a href="{{ route('admin.reports') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800">View all</a>
            </div>
            <div class="mt-5 space-y-4">
                @forelse($stats['recent_reports'] as $report)
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-900 truncate">{{ $report->title }}</p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $report->user?->name ?? 'Anonymous' }} • {{ $report->category?->name ?? 'Unknown' }}
                                </p>
                            </div>
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold 
                                {{ $report->status === 'approved' ? 'bg-emerald-100 text-emerald-700' : '' }}
                                {{ $report->status === 'pending' ? 'bg-amber-100 text-amber-700' : '' }}
                                {{ $report->status === 'rejected' ? 'bg-rose-100 text-rose-700' : '' }}">
                                {{ ucfirst($report->status) }}
                            </span>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-slate-500 text-center py-10">No reports available.</p>
                @endforelse
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between gap-4 border-b border-slate-200 pb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Approval Summary</h2>
                        <p class="text-sm text-slate-500">Status counts for the current system.</p>
                    </div>
                </div>
                <div class="mt-5 space-y-4">
                    <div class="rounded-3xl bg-slate-50 p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm text-slate-500">Approved</p>
                                <p class="mt-1 text-xl font-semibold text-slate-900">{{ number_format($stats['approved_reports']) }}</p>
                            </div>
                            <span class="rounded-2xl bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-700">Good</span>
                        </div>
                    </div>
                    <div class="rounded-3xl bg-slate-50 p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm text-slate-500">Rejected</p>
                                <p class="mt-1 text-xl font-semibold text-slate-900">{{ number_format($stats['rejected_reports']) }}</p>
                            </div>
                            <span class="rounded-2xl bg-rose-100 px-3 py-1 text-sm font-semibold text-rose-700">Needs review</span>
                        </div>
                    </div>
                    <div class="rounded-3xl bg-slate-50 p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm text-slate-500">Moderation Queue</p>
                                <p class="mt-1 text-xl font-semibold text-amber-600">{{ number_format($stats['pending_queue']) }}</p>
                            </div>
                            <span class="rounded-2xl bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-700">Pending</span>
                        </div>
                    </div>
                    <div class="rounded-3xl bg-slate-50 p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm text-slate-500">Places</p>
                                <p class="mt-1 text-xl font-semibold text-slate-900">{{ number_format($stats['total_places']) }}</p>
                            </div>
                            <span class="rounded-2xl bg-sky-100 px-3 py-1 text-sm font-semibold text-sky-700">Catalogued</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="border-b border-slate-200 pb-4">
                    <h2 class="text-lg font-semibold text-slate-900">Latest Users</h2>
                    <p class="text-sm text-slate-500">Newest members joined the platform.</p>
                </div>
                <div class="mt-5 space-y-4">
                    @forelse($stats['recent_users'] as $user)
                        <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                            <div class="flex items-center justify-between gap-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-indigo-600 text-white">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                                    <div>
                                        <p class="font-semibold text-slate-900">{{ $user->name }}</p>
                                        <p class="text-xs text-slate-500">{{ $user->email }}</p>
                                    </div>
                                </div>
                                <span class="rounded-full bg-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700">{{ ucfirst($user->roleName ?? 'user') }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500 text-center py-10">No recent users.</p>
                    @endforelse
                </div>
            </div>

            @if(!$stats['is_moderator'])
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="border-b border-slate-200 pb-4">
                    <h2 class="text-lg font-semibold text-slate-900">Recent Activity</h2>
                    <p class="text-sm text-slate-500">Audit log of recent admin/moderator actions.</p>
                </div>
                <div class="mt-5 space-y-3">
                    @forelse($stats['recent_audit_logs'] as $log)
                        <div class="rounded-2xl bg-slate-50 p-3">
                            <div class="flex items-start gap-3">
                                <span class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl bg-slate-200 text-xs font-bold text-slate-600">
                                    {{ strtoupper(substr($log->user->name ?? '?', 0, 1)) }}
                                </span>
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold text-slate-900">{{ $log->user->name ?? 'System' }}</p>
                                    <p class="text-xs text-slate-500">{{ $log->description ?? $log->action }}</p>
                                    <p class="text-xs text-slate-400 mt-1">{{ $log->created_at->diffForHumans() }}</p>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500 text-center py-6">No recent activity.</p>
                    @endforelse
                </div>
            </div>
            @endif
        </div>
    </div>
@endsection

@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var ctx = document.getElementById('reportsTrendChart');
            if (!ctx) return;

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: {!! json_encode($stats['chart_labels']) !!},
                    datasets: [{
                        label: 'Reports submitted',
                        data: {!! json_encode($stats['chart_values']) !!},
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.16)',
                        fill: true,
                        tension: 0.38,
                        pointRadius: 4,
                        pointBackgroundColor: '#4338ca',
                        pointBorderColor: '#ffffff',
                        borderWidth: 3,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#111827',
                            titleColor: '#ffffff',
                            bodyColor: '#d1d5db',
                            borderColor: '#4f46e5',
                            borderWidth: 1,
                        },
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { color: '#64748b' },
                        },
                        y: {
                            grid: { color: 'rgba(148, 163, 184, 0.16)', borderDash: [4, 4] },
                            ticks: { color: '#64748b', beginAtZero: true, precision: 0 },
                        },
                    },
                },
            });
        });
    </script>
@endsection
