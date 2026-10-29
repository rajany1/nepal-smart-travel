@extends('admin.layout')
@section('title', 'Automation & Queues')

@section('content')
    @php
        $scheduler = $data['scheduler'];
        $worker = $data['worker'];
        $queue = $data['queue'];
        $summary = $data['summary'];
        $toneBadge = [
            'emerald' => 'bg-emerald-50 text-emerald-700',
            'amber' => 'bg-amber-50 text-amber-700',
            'rose' => 'bg-rose-50 text-rose-700',
            'sky' => 'bg-sky-50 text-sky-700',
            'slate' => 'bg-slate-100 text-slate-500',
        ];
        $toneDot = [
            'emerald' => 'bg-emerald-500',
            'amber' => 'bg-amber-500',
            'rose' => 'bg-rose-500',
            'sky' => 'bg-sky-500',
            'slate' => 'bg-slate-400',
        ];
    @endphp

    <div id="automationPage" class="space-y-5">
        {{-- Header --}}
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-slate-500">System control</p>
                    <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Automation &amp; queues</h1>
                    <p class="mt-1 max-w-2xl text-sm leading-relaxed text-slate-500">
                        Every scheduled command, what it does, and whether its worker is alive.
                        Turn a task off if it is causing trouble, or run it once by hand.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-slate-100 px-3 py-1.5 text-[11px] font-semibold text-slate-500">
                        Updated <span id="automationUpdated">just now</span>
                    </span>
                    <button type="button" data-action="refresh" class="inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-600 transition hover:border-primary-200 hover:text-primary-700">
                        <i class="fas fa-rotate-right" id="automationRefreshIcon"></i> Refresh
                    </button>
                    <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-2 rounded-2xl bg-primary-600 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-primary-700">
                        <i class="fas fa-chart-line"></i> Dashboard
                    </a>
                </div>
            </div>

            <div id="schedulerAlert" class="mt-4 flex flex-wrap items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 {{ $scheduler['status'] !== 'online' ? '' : 'hidden' }}">
                <i class="fas fa-triangle-exclamation text-amber-500"></i>
                <div class="min-w-0 flex-1">
                    <p class="font-semibold">The scheduler is not running.</p>
                    <p class="text-xs text-amber-700">Scheduled tasks will not fire until <code class="rounded bg-white/70 px-1.5 py-0.5">{{ $data['commands']['scheduler'] }}</code> is running. Queue jobs already dispatched will still be processed if the worker is alive.</p>
                </div>
                <button type="button" data-action="copy" data-copy="{{ $data['commands']['scheduler'] }}" class="rounded-xl border border-amber-300 bg-white px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100">Copy command</button>
            </div>
        </section>

        {{-- Status cards --}}
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-primary-50 text-primary-600"><i class="fas fa-clock"></i></span>
                    <span id="schedulerState" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $toneBadge[$scheduler['tone']] ?? $toneBadge['slate'] }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $toneDot[$scheduler['tone']] ?? $toneDot['slate'] }}"></span>{{ $scheduler['label'] }}
                    </span>
                </div>
                <p class="mt-3 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Scheduler</p>
                <p id="schedulerHeartbeat" class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{{ $scheduler['human'] }}</p>
                <p class="mt-1 text-[11px] text-slate-500">Heartbeat expected every minute</p>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-violet-50 text-violet-600"><i class="fas fa-microchip"></i></span>
                    <span id="workerState" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $toneBadge[$worker['tone']] ?? $toneBadge['slate'] }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $toneDot[$worker['tone']] ?? $toneDot['slate'] }}"></span>{{ $worker['label'] }}
                    </span>
                </div>
                <p class="mt-3 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Queue worker</p>
                <p id="workerHeartbeat" class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{{ $worker['human'] }}</p>
                <p id="workerDetail" class="mt-1 truncate text-[11px] text-slate-500">
                    @if(!empty($worker['meta']['job'])) Last job: {{ \Illuminate\Support\Str::limit($worker['meta']['job'], 38) }} @else No job processed yet @endif
                </p>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-50 text-amber-600"><i class="fas fa-layer-group"></i></span>
                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-700">Live</span>
                </div>
                <p class="mt-3 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Pending jobs</p>
                <p id="pendingJobs" class="mt-1 text-2xl font-semibold tracking-tight text-slate-900">{{ number_format($queue['pending_jobs']) }}</p>
                <p id="pendingDetail" class="mt-1 text-[11px] text-slate-500">{{ number_format($queue['processing_jobs']) }} processing &middot; oldest {{ $queue['oldest_human'] }}</p>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-rose-50 text-rose-600"><i class="fas fa-triangle-exclamation"></i></span>
                    <span id="failedBadge" class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $queue['failed_jobs'] > 0 ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' }}">{{ $queue['failed_jobs'] > 0 ? 'Needs a look' : 'Clean' }}</span>
                </div>
                <p class="mt-3 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Failed jobs</p>
                <p id="failedJobs" class="mt-1 text-2xl font-semibold tracking-tight {{ $queue['failed_jobs'] > 0 ? 'text-rose-600' : 'text-slate-900' }}">{{ number_format($queue['failed_jobs']) }}</p>
                <p class="mt-1 text-[11px] text-slate-500">Retry or remove them below</p>
            </div>
        </section>

        <div class="grid gap-5 xl:grid-cols-[1.65fr_1fr]">
            {{-- Scheduled tasks --}}
            <section class="min-w-0 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Scheduled commands</p>
                        <h2 class="text-lg font-semibold text-slate-900">What runs, when, and why</h2>
                    </div>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold text-slate-600">
                        <span id="enabledTasks">{{ $summary['tasks_enabled'] }}</span> of {{ $summary['tasks_total'] }} enabled
                    </span>
                </div>

                <div class="mt-4 space-y-3">
                    @foreach($data['tasks'] as $task)
                        <article data-task-card="{{ $task['key'] }}" class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4 transition hover:border-primary-200 hover:bg-white">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="flex min-w-0 items-start gap-3">
                                    <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-{{ $task['color'] }}-100 text-{{ $task['color'] }}-600"><i class="fas fa-{{ $task['icon'] }} text-sm"></i></span>
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <h3 class="text-sm font-semibold text-slate-900">{{ $task['name'] }}</h3>
                                            <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-400 ring-1 ring-slate-200">{{ $task['category'] }}</span>
                                            @if($task['is_job'])
                                                <span class="rounded-full bg-orange-50 px-2 py-0.5 text-[10px] font-semibold text-orange-700">queued job</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-xs leading-relaxed text-slate-600">{{ $task['description'] }}</p>
                                        <p class="mt-1 text-[11px] leading-relaxed text-slate-400"><span class="font-semibold text-slate-500">If paused:</span> {{ $task['impact'] }}</p>
                                    </div>
                                </div>
                                <span data-task-status class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold {{ $toneBadge[$task['status_tone']] ?? $toneBadge['slate'] }}">{{ $task['status_label'] }}</span>
                            </div>

                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <code class="min-w-0 flex-1 truncate rounded-xl border border-slate-200 bg-white px-3 py-2 text-[11px] text-slate-600">{{ $task['command_preview'] }}</code>
                                <button type="button" data-action="copy" data-copy="{{ $task['command_preview'] }}" class="inline-flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-400 hover:text-primary-600" title="Copy command"><i class="fas fa-copy text-xs"></i></button>
                            </div>

                            <div class="mt-3 grid gap-2 text-[11px] sm:grid-cols-3">
                                <div class="rounded-xl bg-white px-3 py-2 ring-1 ring-slate-100">
                                    <p class="text-[9px] font-semibold uppercase tracking-wider text-slate-400">Schedule</p>
                                    <p class="mt-0.5 font-semibold text-slate-700">{{ $task['schedule'] }}</p>
                                </div>
                                <div class="rounded-xl bg-white px-3 py-2 ring-1 ring-slate-100">
                                    <p class="text-[9px] font-semibold uppercase tracking-wider text-slate-400">Next run</p>
                                    <p data-task-next class="mt-0.5 font-semibold {{ $task['enabled'] ? 'text-slate-700' : 'text-slate-400' }}">{{ $task['enabled'] ? $task['next_run_human'] : 'Paused' }}</p>
                                </div>
                                <div class="rounded-xl bg-white px-3 py-2 ring-1 ring-slate-100">
                                    <p class="text-[9px] font-semibold uppercase tracking-wider text-slate-400">Last run</p>
                                    <p data-task-last class="mt-0.5 font-semibold text-slate-700">{{ $task['last_run_human'] }}@if($task['duration_ms'] !== null) <span class="font-normal text-slate-400">&middot; {{ $task['duration_human'] }}</span>@endif</p>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center gap-2">
                                    <button type="button" role="switch" aria-checked="{{ $task['enabled'] ? 'true' : 'false' }}" data-action="toggle-task" data-task-toggle="{{ $task['key'] }}" data-enabled="{{ $task['enabled'] ? '1' : '0' }}" class="relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition {{ $task['enabled'] ? 'bg-emerald-500' : 'bg-slate-300' }}">
                                        <span data-toggle-knob class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition {{ $task['enabled'] ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                    </button>
                                    <span data-toggle-label class="text-[11px] font-semibold {{ $task['enabled'] ? 'text-emerald-600' : 'text-slate-400' }}">{{ $task['enabled'] ? 'Enabled' : 'Paused' }}</span>
                                    <span data-task-counts class="hidden text-[11px] text-slate-400 sm:inline">&middot; {{ $task['run_count'] }} runs &middot; {{ $task['fail_count'] }} failed</span>
                                </div>
                                <button type="button" data-action="run-task" data-task-run="{{ $task['key'] }}" @if(!$task['can_run']) disabled @endif class="inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-[11px] font-semibold transition {{ $task['can_run'] ? 'bg-primary-600 text-white hover:bg-primary-700' : 'cursor-not-allowed bg-slate-100 text-slate-400' }}">
                                    <i class="fas fa-play text-[9px]"></i> Run now
                                </button>
                            </div>

                            @if($task['last_error'] || $task['last_output'])
                                <details class="mt-3 rounded-xl border px-3 py-2 text-[11px] {{ $task['last_error'] ? 'border-rose-100 bg-rose-50/70 text-rose-700' : 'border-slate-200 bg-white text-slate-600' }}">
                                    <summary class="cursor-pointer font-semibold">{{ $task['last_error'] ? 'Last error' : 'Last output' }}</summary>
                                    @if($task['last_error'])
                                        <pre class="mt-2 whitespace-pre-wrap break-words font-sans">{{ $task['last_error'] }}</pre>
                                    @endif
                                    @if($task['last_output'])
                                        <div class="mt-2 {{ $task['last_error'] ? 'border-t border-rose-100 pt-2' : '' }}">
                                            <p class="mb-1 text-[10px] font-semibold uppercase tracking-wider {{ $task['last_error'] ? 'text-rose-500' : 'text-slate-400' }}">Command output</p>
                                            <pre class="max-h-40 overflow-auto whitespace-pre-wrap break-words font-mono">{{ $task['last_output'] }}</pre>
                                        </div>
                                    @endif
                                </details>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>

            <div class="min-w-0 space-y-5">
                {{-- Queue control --}}
                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-slate-500">Queue control</p>
                            <h2 class="text-lg font-semibold text-slate-900">Workers &amp; backlog</h2>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-600">{{ count($data['queues']) }} queues</span>
                    </div>

                    <div class="mt-4 space-y-2">
                        @forelse($data['queues'] as $row)
                            <div class="flex items-center justify-between gap-3 rounded-2xl bg-slate-50 px-3 py-2">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold text-slate-700">{{ $row['queue'] }}</p>
                                    <p class="text-[10px] text-slate-400">{{ $row['processing'] }} processing &middot; oldest {{ $row['oldest_human'] }}</p>
                                </div>
                                <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-bold text-slate-700 ring-1 ring-slate-200">{{ number_format($row['total']) }}</span>
                            </div>
                        @empty
                            <div class="rounded-2xl bg-emerald-50 px-3 py-3 text-xs text-emerald-700">
                                <i class="fas fa-circle-check mr-1"></i> No jobs waiting. The workers are keeping up.
                            </div>
                        @endforelse
                    </div>

                    <div class="mt-4 grid gap-2 sm:grid-cols-2">
                        <button type="button" data-action="ping-worker" class="inline-flex items-center justify-center gap-2 rounded-xl bg-violet-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-violet-700">
                            <i class="fas fa-satellite-dish"></i> Test worker
                        </button>
                        <button type="button" data-action="restart-workers" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-primary-200 hover:text-primary-700">
                            <i class="fas fa-rotate"></i> Restart workers
                        </button>
                    </div>

                    <p class="mt-3 rounded-2xl bg-slate-50 p-3 text-[11px] leading-relaxed text-slate-500">
                        <strong class="text-slate-700">Test worker</strong> queues a tiny job. If the heartbeat refreshes, a worker is alive.
                        <strong class="text-slate-700">Restart workers</strong> only sends Laravel's graceful stop signal; the watchdog must start them again.
                    </p>
                </section>

                {{-- Failed jobs --}}
                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-medium text-slate-500">Failed queue</p>
                            <h2 class="text-lg font-semibold text-slate-900">Jobs that gave up</h2>
                        </div>
                        <span class="rounded-full bg-rose-50 px-2.5 py-1 text-[11px] font-bold text-rose-700">{{ number_format($data['failed_total']) }}</span>
                    </div>

                    <div class="mt-4 space-y-2">
                        @forelse($data['failed_jobs'] as $job)
                            <div data-failed-job="{{ $job['uuid'] }}" class="rounded-2xl border border-rose-100 bg-rose-50/50 p-3">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="truncate text-xs font-semibold text-slate-800">{{ $job['job'] }}</p>
                                        <p class="text-[10px] text-slate-500">{{ $job['queue'] }} queue &middot; {{ $job['failed_at'] }}</p>
                                    </div>
                                    <div class="flex flex-shrink-0 gap-1">
                                        <button type="button" data-action="retry-failed" data-uuid="{{ $job['uuid'] }}" class="rounded-lg bg-white px-2 py-1 text-[10px] font-semibold text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-50">Retry</button>
                                        <button type="button" data-action="forget-failed" data-uuid="{{ $job['uuid'] }}" class="rounded-lg bg-white px-2 py-1 text-[10px] font-semibold text-rose-700 ring-1 ring-rose-200 hover:bg-rose-100">Remove</button>
                                    </div>
                                </div>
                                <p class="mt-2 break-words text-[10px] leading-relaxed text-rose-700/80">{{ $job['exception'] }}</p>
                            </div>
                        @empty
                            <div class="rounded-2xl bg-emerald-50 px-3 py-3 text-xs text-emerald-700">
                                <i class="fas fa-circle-check mr-1"></i> No failed jobs. Nice.
                            </div>
                        @endforelse
                    </div>

                    @if($data['failed_total'] > 0)
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" data-action="retry-all" class="rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-[11px] font-semibold text-emerald-700 hover:bg-emerald-100">Retry all</button>
                            <button type="button" data-action="forget-all" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-[11px] font-semibold text-rose-700 hover:bg-rose-100">Clear all</button>
                        </div>
                    @endif
                </section>

                {{-- Recent manual runs / failures --}}
                <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div>
                        <p class="text-sm font-medium text-slate-500">Audit trail</p>
                        <h2 class="text-lg font-semibold text-slate-900">Recent manual runs &amp; failures</h2>
                    </div>
                    <div class="mt-4 space-y-2">
                        @forelse($data['recent_runs'] as $run)
                            <div class="flex items-start gap-2 rounded-2xl bg-slate-50 px-3 py-2">
                                <span class="mt-1 h-1.5 w-1.5 flex-shrink-0 rounded-full {{ $toneDot[$run['status_tone']] ?? $toneDot['slate'] }}"></span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-[11px] font-semibold text-slate-700">{{ $run['name'] }}</p>
                                    <p class="text-[10px] text-slate-400">{{ $run['status_label'] }} &middot; {{ $run['trigger'] }} &middot; {{ $run['user'] }} &middot; {{ $run['when'] }}</p>
                                </div>
                                <span class="flex-shrink-0 text-[10px] font-semibold text-slate-400">{{ $run['duration_human'] }}</span>
                            </div>
                        @empty
                            <p class="rounded-2xl bg-slate-50 px-3 py-3 text-xs text-slate-500">Manual runs and scheduled failures will appear here.</p>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>

        {{-- How it fits together --}}
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <p class="text-sm font-medium text-slate-500">Cheat sheet</p>
                <h2 class="text-lg font-semibold text-slate-900">How the pieces fit together</h2>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-3">
                <div class="rounded-2xl bg-slate-50 p-4">
                    <div class="flex items-center gap-2 text-slate-700"><i class="fas fa-clock text-primary-500"></i><p class="text-sm font-semibold">Scheduler</p></div>
                    <p class="mt-2 text-[11px] leading-relaxed text-slate-500">Wakes up every minute and fires tasks whose time has come. If this is off, nothing below runs on its own.</p>
                    <code class="mt-2 block break-words rounded-xl bg-white px-2.5 py-2 text-[10px] text-slate-600 ring-1 ring-slate-200">{{ $data['commands']['scheduler'] }}</code>
                    <button type="button" data-action="copy" data-copy="{{ $data['commands']['scheduler'] }}" class="mt-2 text-[10px] font-semibold text-primary-600 hover:text-primary-700">Copy command</button>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <div class="flex items-center gap-2 text-slate-700"><i class="fas fa-microchip text-violet-500"></i><p class="text-sm font-semibold">Queue worker</p></div>
                    <p class="mt-2 text-[11px] leading-relaxed text-slate-500">Executes AI jobs, BIPAD sync and anything you queue with “Run now”. It should stay resident, not be started per request.</p>
                    <code class="mt-2 block break-words rounded-xl bg-white px-2.5 py-2 text-[10px] text-slate-600 ring-1 ring-slate-200">{{ $data['commands']['worker'] }}</code>
                    <button type="button" data-action="copy" data-copy="{{ $data['commands']['worker'] }}" class="mt-2 text-[10px] font-semibold text-primary-600 hover:text-primary-700">Copy command</button>
                </div>
                <div class="rounded-2xl bg-slate-50 p-4">
                    <div class="flex items-center gap-2 text-slate-700"><i class="fas fa-power-off text-emerald-500"></i><p class="text-sm font-semibold">All-in-one</p></div>
                    <p class="mt-2 text-[11px] leading-relaxed text-slate-500">Starts Redis, the queue worker, the scheduler and the dev server together. Use this on a development machine.</p>
                    <code class="mt-2 block break-words rounded-xl bg-white px-2.5 py-2 text-[10px] text-slate-600 ring-1 ring-slate-200">{{ $data['commands']['all'] }}</code>
                    <button type="button" data-action="copy" data-copy="{{ $data['commands']['all'] }}" class="mt-2 text-[10px] font-semibold text-primary-600 hover:text-primary-700">Copy command</button>
                </div>
            </div>
            <p class="mt-3 text-[11px] leading-relaxed text-slate-400">
                Timezone: {{ config('app.timezone') }}. “Next run” is calculated from the cron expression; a paused task is skipped even if its time matches.
            </p>
        </section>

        <div id="automationToast" class="pointer-events-none fixed bottom-6 right-6 z-50 hidden max-w-sm rounded-2xl px-4 py-3 text-sm font-medium shadow-xl"></div>
    </div>
@endsection

@section('scripts')
<script>
(function () {
    'use strict';

    var endpoints = {
        data: @json(route('admin.automation.data')),
        toggle: @json(route('admin.automation.tasks.toggle', ['key' => '__KEY__'])),
        run: @json(route('admin.automation.tasks.run', ['key' => '__KEY__'])),
        retry: @json(route('admin.automation.queue.retry')),
        forget: @json(route('admin.automation.queue.forget')),
        restart: @json(route('admin.automation.queue.restart')),
        ping: @json(route('admin.automation.queue.ping'))
    };

    var badges = {
        emerald: 'bg-emerald-50 text-emerald-700',
        amber: 'bg-amber-50 text-amber-700',
        rose: 'bg-rose-50 text-rose-700',
        sky: 'bg-sky-50 text-sky-700',
        slate: 'bg-slate-100 text-slate-500'
    };
    var dots = {
        emerald: 'bg-emerald-500',
        amber: 'bg-amber-500',
        rose: 'bg-rose-500',
        sky: 'bg-sky-500',
        slate: 'bg-slate-400'
    };
    var badgeBase = 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold ';
    var stateBase = 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-bold ';
    var stateDot = '<span class="h-1.5 w-1.5 rounded-full ';

    function csrf() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function toast(message, ok) {
        var el = document.getElementById('automationToast');
        if (!el) return;
        el.textContent = message;
        el.className = 'pointer-events-none fixed bottom-6 right-6 z-50 max-w-sm rounded-2xl px-4 py-3 text-sm font-medium shadow-xl ' + (ok === false ? 'bg-rose-600 text-white' : 'bg-slate-900 text-white');
        el.classList.remove('hidden');
        clearTimeout(window.__automationToastTimer);
        window.__automationToastTimer = setTimeout(function () { el.classList.add('hidden'); }, 4200);
    }

    function post(url, payload) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(payload || {})
        }).then(function (response) {
            return response.json().catch(function () { return {}; }).then(function (body) {
                if (!response.ok || !body.success) {
                    throw new Error(body.message || ('HTTP ' + response.status));
                }
                return body;
            });
        });
    }

    function setBusy(button, busy) {
        if (!button) return;
        button.classList.toggle('pointer-events-none', busy);
        button.classList.toggle('opacity-50', busy);
    }

    function updateTask(task) {
        var card = document.querySelector('[data-task-card="' + task.key + '"]');
        if (!card) return;

        var badge = card.querySelector('[data-task-status]');
        if (badge) {
            badge.className = badgeBase + (badges[task.status_tone] || badges.slate);
            badge.textContent = task.status_label;
        }

        var next = card.querySelector('[data-task-next]');
        if (next) {
            next.textContent = task.enabled ? task.next_run_human : 'Paused';
            next.className = 'mt-0.5 font-semibold ' + (task.enabled ? 'text-slate-700' : 'text-slate-400');
        }

        var last = card.querySelector('[data-task-last]');
        if (last) {
            last.textContent = task.last_run_human + (task.duration_ms !== null ? ' · ' + task.duration_human : '');
        }

        var toggle = card.querySelector('[data-task-toggle]');
        var knob = card.querySelector('[data-toggle-knob]');
        var label = card.querySelector('[data-toggle-label]');
        if (toggle) {
            toggle.dataset.enabled = task.enabled ? '1' : '0';
            toggle.setAttribute('aria-checked', task.enabled ? 'true' : 'false');
            toggle.className = 'relative inline-flex h-6 w-11 flex-shrink-0 items-center rounded-full transition ' + (task.enabled ? 'bg-emerald-500' : 'bg-slate-300');
        }
        if (knob) knob.className = 'inline-block h-4 w-4 transform rounded-full bg-white shadow transition ' + (task.enabled ? 'translate-x-6' : 'translate-x-1');
        if (label) {
            label.textContent = task.enabled ? 'Enabled' : 'Paused';
            label.className = 'text-[11px] font-semibold ' + (task.enabled ? 'text-emerald-600' : 'text-slate-400');
        }

        var counts = card.querySelector('[data-task-counts]');
        if (counts) {
            counts.innerHTML = '&middot; ' + Number(task.run_count || 0) + ' runs &middot; ' + Number(task.fail_count || 0) + ' failed';
        }

        var run = card.querySelector('[data-task-run]');
        if (run) {
            run.disabled = !task.can_run;
            run.className = 'inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-[11px] font-semibold transition ' + (task.can_run ? 'bg-primary-600 text-white hover:bg-primary-700' : 'cursor-not-allowed bg-slate-100 text-slate-400');
        }
    }

    function updateStatus(data) {
        var scheduler = data.scheduler;
        var worker = data.worker;
        var queue = data.queue;

        var schedulerEl = document.getElementById('schedulerState');
        if (schedulerEl) {
            schedulerEl.className = stateBase + (badges[scheduler.tone] || badges.slate);
            schedulerEl.innerHTML = stateDot + (dots[scheduler.tone] || dots.slate) + '"></span>' + scheduler.label;
        }
        var schedulerHeartbeat = document.getElementById('schedulerHeartbeat');
        if (schedulerHeartbeat) schedulerHeartbeat.textContent = scheduler.human;

        var workerEl = document.getElementById('workerState');
        if (workerEl) {
            workerEl.className = stateBase + (badges[worker.tone] || badges.slate);
            workerEl.innerHTML = stateDot + (dots[worker.tone] || dots.slate) + '"></span>' + worker.label;
        }
        var workerHeartbeat = document.getElementById('workerHeartbeat');
        if (workerHeartbeat) workerHeartbeat.textContent = worker.human;
        var workerDetail = document.getElementById('workerDetail');
        if (workerDetail) {
            var lastJob = worker.meta && worker.meta.job ? worker.meta.job : null;
            workerDetail.textContent = lastJob ? 'Last job: ' + lastJob : 'No job processed yet';
        }

        var pending = document.getElementById('pendingJobs');
        if (pending) pending.textContent = Number(queue.pending_jobs || 0).toLocaleString();
        var pendingDetail = document.getElementById('pendingDetail');
        if (pendingDetail) pendingDetail.textContent = Number(queue.processing_jobs || 0).toLocaleString() + ' processing · oldest ' + queue.oldest_human;

        var failed = document.getElementById('failedJobs');
        if (failed) {
            failed.textContent = Number(queue.failed_jobs || 0).toLocaleString();
            failed.className = 'mt-1 text-2xl font-semibold tracking-tight ' + (queue.failed_jobs > 0 ? 'text-rose-600' : 'text-slate-900');
        }
        var failedBadge = document.getElementById('failedBadge');
        if (failedBadge) {
            failedBadge.textContent = queue.failed_jobs > 0 ? 'Needs a look' : 'Clean';
            failedBadge.className = 'rounded-full px-2.5 py-1 text-[11px] font-bold ' + (queue.failed_jobs > 0 ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700');
        }

        var enabled = document.getElementById('enabledTasks');
        if (enabled) enabled.textContent = data.summary.tasks_enabled;

        var alert = document.getElementById('schedulerAlert');
        if (alert) alert.classList.toggle('hidden', scheduler.status === 'online');

        (data.tasks || []).forEach(updateTask);

        var updated = document.getElementById('automationUpdated');
        if (updated) updated.textContent = 'just now';
    }

    function loadData(silent) {
        if (!document.getElementById('automationPage')) {
            cleanup();
            return Promise.resolve();
        }

        var icon = document.getElementById('automationRefreshIcon');
        if (icon && !silent) icon.classList.add('fa-spin');

        return fetch(endpoints.data, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            cache: 'no-store'
        })
            .then(function (response) {
                if (!response.ok) throw new Error('HTTP ' + response.status);
                return response.json();
            })
            .then(function (payload) {
                if (payload && payload.success) updateStatus(payload.data);
            })
            .catch(function () {
                if (!silent) toast('Could not refresh automation status.', false);
            })
            .finally(function () {
                if (icon && !silent) icon.classList.remove('fa-spin');
            });
    }

    function cleanup() {
        if (window.__automationTimer) {
            clearInterval(window.__automationTimer);
            window.__automationTimer = null;
        }
    }

    if (!window.__automationActionsBound) {
        window.__automationActionsBound = true;

        document.addEventListener('click', function (event) {
            var button = event.target.closest ? event.target.closest('[data-action]') : null;
            if (!button || !document.getElementById('automationPage')) return;

            var action = button.getAttribute('data-action');

            if (action === 'copy') {
                event.preventDefault();
                var text = button.getAttribute('data-copy') || '';
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(function () { toast('Copied to clipboard.'); });
                }
                return;
            }

            if (action === 'refresh') {
                event.preventDefault();
                loadData(false);
                return;
            }

            if (action === 'toggle-task') {
                event.preventDefault();
                var key = button.getAttribute('data-task-toggle');
                var enabled = button.getAttribute('data-enabled') === '1';
                setBusy(button, true);
                post(endpoints.toggle.replace('__KEY__', encodeURIComponent(key)), { enabled: !enabled })
                    .then(function (body) {
                        updateTask(body.task);
                        toast(body.message);
                        setTimeout(function () { loadData(true); }, 250);
                    })
                    .catch(function (error) { toast(error.message, false); })
                    .finally(function () { setBusy(button, false); });
                return;
            }

            if (action === 'run-task') {
                event.preventDefault();
                var runKey = button.getAttribute('data-task-run');
                setBusy(button, true);
                post(endpoints.run.replace('__KEY__', encodeURIComponent(runKey)), {})
                    .then(function (body) {
                        updateTask(body.task);
                        toast(body.message);
                        setTimeout(function () { loadData(true); }, 3500);
                    })
                    .catch(function (error) { toast(error.message, false); })
                    .finally(function () { setBusy(button, false); });
                return;
            }

            if (action === 'ping-worker') {
                event.preventDefault();
                setBusy(button, true);
                post(endpoints.ping, {})
                    .then(function (body) {
                        toast(body.message);
                        setTimeout(function () { loadData(true); }, 2500);
                        setTimeout(function () { loadData(true); }, 7000);
                    })
                    .catch(function (error) { toast(error.message, false); })
                    .finally(function () { setBusy(button, false); });
                return;
            }

            if (action === 'restart-workers') {
                event.preventDefault();
                if (!window.confirm('Send a graceful restart signal to the queue workers?')) return;
                setBusy(button, true);
                post(endpoints.restart, {})
                    .then(function (body) { toast(body.message); })
                    .catch(function (error) { toast(error.message, false); })
                    .finally(function () { setBusy(button, false); });
                return;
            }

            if (action === 'retry-failed' || action === 'forget-failed') {
                event.preventDefault();
                var uuid = button.getAttribute('data-uuid');
                var forget = action === 'forget-failed';
                if (forget && !window.confirm('Remove this failed job permanently? It cannot be retried afterwards.')) return;
                setBusy(button, true);
                var endpoint = forget ? endpoints.forget : endpoints.retry;
                post(endpoint, { uuid: uuid })
                    .then(function (body) {
                        var row = document.querySelector('[data-failed-job="' + uuid + '"]');
                        if (row) row.remove();
                        toast(body.message);
                        if (body.data) updateStatus(body.data);
                    })
                    .catch(function (error) { toast(error.message, false); })
                    .finally(function () { setBusy(button, false); });
                return;
            }

            if (action === 'retry-all' || action === 'forget-all') {
                event.preventDefault();
                var clearAll = action === 'forget-all';
                if (clearAll && !window.confirm('Clear every failed job? This cannot be undone.')) return;
                setBusy(button, true);
                var allEndpoint = clearAll ? endpoints.forget : endpoints.retry;
                post(allEndpoint, {})
                    .then(function (body) {
                        toast(body.message);
                        if (body.data) updateStatus(body.data);
                        document.querySelectorAll('[data-failed-job]').forEach(function (row) { row.remove(); });
                    })
                    .catch(function (error) { toast(error.message, false); })
                    .finally(function () { setBusy(button, false); });
            }
        });

        window.addEventListener('popstate', cleanup);
    }

    cleanup();
    window.__automationTimer = setInterval(function () { loadData(true); }, 20000);
})();
</script>
@endsection
