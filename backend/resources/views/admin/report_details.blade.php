@extends('admin.layout')
@section('title', 'Report #'.$report->id)

@section('content')
@php
    $status = $report->status ?: 'pending';
    $priority = $report->priority ?: 'medium';
    $reporter = $report->user;
    $verifier = $report->verifier;
    $canApprove = auth()->user()?->hasPermission('approve_reports') ?? false;

    $categoryName = $report->category?->name;
    $district = $report->district;
    $placeName = $report->place?->name;
    $locationLine = collect([$report->location_name, $district])->filter()->implode(', ');

    $confidence = $report->authenticity_score !== null
        ? max(0, min(100, (int) round((float) $report->authenticity_score * 100)))
        : null;

    $statusMeta = [
        'pending' => [
            'label' => 'Pending review',
            'icon' => 'fa-hourglass-half',
            'chip' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'dot' => 'bg-amber-500',
            'soft' => 'bg-amber-50 text-amber-700 ring-amber-200',
        ],
        'approved' => [
            'label' => 'Approved',
            'icon' => 'fa-circle-check',
            'chip' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'dot' => 'bg-emerald-500',
            'soft' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        ],
        'rejected' => [
            'label' => 'Rejected',
            'icon' => 'fa-circle-xmark',
            'chip' => 'bg-rose-50 text-rose-700 ring-rose-200',
            'dot' => 'bg-rose-500',
            'soft' => 'bg-rose-50 text-rose-700 ring-rose-200',
        ],
    ];
    $statusMeta = $statusMeta[$status] ?? $statusMeta['pending'];

    $priorityMeta = [
        'critical' => ['label' => 'Critical', 'chip' => 'bg-rose-50 text-rose-700 ring-rose-200', 'soft' => 'bg-rose-500'],
        'high' => ['label' => 'High', 'chip' => 'bg-orange-50 text-orange-700 ring-orange-200', 'soft' => 'bg-orange-500'],
        'medium' => ['label' => 'Medium', 'chip' => 'bg-amber-50 text-amber-700 ring-amber-200', 'soft' => 'bg-amber-500'],
        'low' => ['label' => 'Low', 'chip' => 'bg-slate-100 text-slate-600 ring-slate-200', 'soft' => 'bg-slate-400'],
    ];
    $priorityMeta = $priorityMeta[$priority] ?? $priorityMeta['medium'];

    $gpsVerification = $report->gps_verification_status ?: 'none';
    $gpsMeta = [
        'verified' => ['label' => 'GPS verified', 'icon' => 'fa-location-dot', 'class' => 'text-emerald-600', 'chip' => 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
        'mismatched' => ['label' => 'GPS mismatch', 'icon' => 'fa-triangle-exclamation', 'class' => 'text-rose-600', 'chip' => 'bg-rose-50 text-rose-700 ring-rose-200'],
        'no_gps_data' => ['label' => 'No photo GPS', 'icon' => 'fa-location-crosshairs', 'class' => 'text-amber-600', 'chip' => 'bg-amber-50 text-amber-700 ring-amber-200'],
        'none' => ['label' => 'No GPS check', 'icon' => 'fa-location-dot', 'class' => 'text-slate-500', 'chip' => 'bg-slate-100 text-slate-600 ring-slate-200'],
    ];
    $gpsMeta = $gpsMeta[$gpsVerification] ?? $gpsMeta['none'];

    $locationIntegrity = $report->location_integrity_status;
    $liMeta = [
        'genuine' => ['label' => 'Genuine', 'icon' => 'fa-shield-check', 'class' => 'text-emerald-600'],
        'suspicious' => ['label' => 'Suspicious', 'icon' => 'fa-triangle-exclamation', 'class' => 'text-rose-600'],
        'cannot_determine' => ['label' => 'Could not determine', 'icon' => 'fa-circle-question', 'class' => 'text-amber-600'],
    ];
    $liMeta = $liMeta[$locationIntegrity] ?? ['label' => 'Not evaluated', 'icon' => 'fa-circle-minus', 'class' => 'text-slate-400'];

    $analysis = is_array($report->ai_analysis) ? $report->ai_analysis : [];
    $imageCheck = $analysis['image_check'] ?? [];
    $imageCheckVerdict = $imageCheck['verdict'] ?? null;
    $imageCheckEvaluated = in_array($imageCheckVerdict, ['clean', 'suspicious', 'violation', 'duplicate'], true);

    $images = $report->media->where('type', 'image')->values();
    $otherMedia = $report->media->where('type', '!=', 'image')->values();

    $mediaUrl = function ($media) {
        $url = null;
        try {
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($media->media_url)) {
                $url = \Illuminate\Support\Facades\Storage::disk('public')->url($media->media_url);
            }
        } catch (\Throwable $e) {
            $url = null;
        }

        return $url ?: asset('storage/'.$media->media_url);
    };

    $initials = function (?string $name): string {
        $name = trim($name ?: 'Anonymous');
        $parts = preg_split('/\s+/', $name) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_substr($part, 0, 1);
        }

        return mb_strtoupper($letters ?: 'A');
    };

    $statusDetail = match ($status) {
        'approved' => $report->verified_at ? 'Approved '.$report->verified_at->diffForHumans() : 'Marked approved',
        'rejected' => $report->verified_at ? 'Rejected '.$report->verified_at->diffForHumans() : 'Marked rejected',
        default => 'Awaiting a moderator decision',
    };
@endphp

<div id="adminReportDetail" class="space-y-5 p-4 sm:p-5 lg:p-6">
    {{-- Command header --}}
    <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-primary-900 via-primary-800 to-primary-700 p-5 text-white shadow-xl sm:p-6">
        <div class="pointer-events-none absolute -right-16 -top-24 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
        <div class="relative flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div class="min-w-0">
                <a href="{{ route('admin.reports', $listParams ?? []) }}" class="inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.28em] text-primary-200 transition hover:text-white">
                    <i class="fas fa-arrow-left text-[10px]"></i> Reports
                </a>

                <div class="mt-3 flex flex-wrap items-center gap-2 text-[11px] font-semibold">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-white">
                        <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $statusMeta['label'] }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-white">
                        <i class="fas fa-flag text-[9px]"></i>{{ $priorityMeta['label'] }} priority
                    </span>
                    <button type="button" onclick="copyReportId()" class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-2.5 py-1 text-white transition hover:bg-white/20" title="Copy report ID">
                        <i class="fas fa-hashtag text-[9px]"></i>{{ $report->id }}
                        <i class="fas fa-copy text-[9px] text-primary-200"></i>
                    </button>
                </div>

                <h1 class="mt-2 max-w-3xl text-2xl font-semibold leading-tight sm:text-3xl">{{ $report->title }}</h1>

                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-primary-100">
                    <span class="inline-flex items-center gap-1.5">
                        <i class="fas fa-user text-[10px] text-primary-200"></i>
                        {{ $reporter?->name ?? 'Anonymous' }}
                    </span>
                    <span class="inline-flex items-center gap-1.5" title="{{ $report->created_at?->format('d M Y, H:i') }}">
                        <i class="fas fa-clock text-[10px] text-primary-200"></i>
                        {{ $report->created_at?->diffForHumans() }}
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <i class="fas fa-folder text-[10px] text-primary-200"></i>
                        {{ $categoryName ?? 'Uncategorised' }}
                    </span>
                    @if($locationLine)
                        <span class="inline-flex items-center gap-1.5">
                            <i class="fas fa-location-dot text-[10px] text-primary-200"></i>
                            {{ $locationLine }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="flex flex-col gap-2 sm:items-end">
                <div class="flex flex-wrap items-center gap-2">
                    @if($prevReportId)
                        <a href="{{ route('admin.reports.view', array_merge(['id' => $prevReportId], $listParams ?? [])) }}" class="report-nav-prev inline-flex h-10 items-center gap-2 rounded-2xl bg-white/10 px-3 text-xs font-semibold text-white transition hover:bg-white/20" title="Previous report in current list (←)">
                            <i class="fas fa-arrow-left text-[10px]"></i> Previous
                        </a>
                    @endif
                    @if($nextReportId)
                        <a href="{{ route('admin.reports.view', array_merge(['id' => $nextReportId], $listParams ?? [])) }}" class="report-nav-next inline-flex h-10 items-center gap-2 rounded-2xl bg-white/10 px-3 text-xs font-semibold text-white transition hover:bg-white/20" title="Next report in current list (→)">
                            Next <i class="fas fa-arrow-right text-[10px]"></i>
                        </a>
                    @endif
                    <a href="{{ route('admin.reports', $listParams ?? []) }}" class="inline-flex h-10 items-center gap-2 rounded-2xl bg-white px-3 text-xs font-semibold text-primary-900 transition hover:bg-primary-50">
                        <i class="fas fa-list-ul text-[10px]"></i> Back to reports
                    </a>
                </div>
                @if($prevReportId || $nextReportId)
                    <p class="text-[11px] text-primary-200">Use <kbd class="rounded bg-white/10 px-1 py-0.5 font-mono">←</kbd> <kbd class="rounded bg-white/10 px-1 py-0.5 font-mono">→</kbd> to move through the current list</p>
                @endif
            </div>
        </div>
    </section>

    {{-- KPI strip --}}
    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Status</p>
                    <p class="mt-2 text-lg font-semibold text-slate-900">{{ $statusMeta['label'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $statusDetail }}</p>
                </div>
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl ring-1 {{ $statusMeta['soft'] }}">
                    <i class="fas {{ $statusMeta['icon'] }}"></i>
                </span>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Priority</p>
                    <p class="mt-2 text-lg font-semibold text-slate-900">{{ $priorityMeta['label'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">Reporter-selected urgency</p>
                </div>
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-slate-50 text-slate-500 ring-1 ring-slate-200">
                    <i class="fas fa-flag"></i>
                </span>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Stored confidence</p>
                    <p class="mt-2 text-lg font-semibold text-slate-900">{{ $confidence !== null ? $confidence.'%' : '—' }}</p>
                    <p class="mt-1 text-xs text-slate-500">Text + location + image signals</p>
                </div>
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary-50 text-primary-700 ring-1 ring-primary-100">
                    <i class="fas fa-gauge-high"></i>
                </span>
            </div>
            <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100">
                <div class="h-full rounded-full bg-primary-600" style="width: {{ $confidence ?? 0 }}%"></div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">GPS verification</p>
                    <p class="mt-2 text-lg font-semibold {{ $gpsMeta['class'] }}">{{ $gpsMeta['label'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">
                        @if($report->gps_distance_km !== null)
                            {{ $report->gps_distance_km }} km from photo location
                        @elseif($gpsVerification === 'none')
                            No verification stored
                        @else
                            Photo evidence only
                        @endif
                    </p>
                </div>
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl ring-1 {{ $gpsMeta['chip'] }}">
                    <i class="fas {{ $gpsMeta['icon'] }}"></i>
                </span>
            </div>
        </div>
    </section>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="min-w-0 space-y-5">
            {{-- Narrative + submission meta --}}
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <span class="grid h-9 w-9 place-items-center rounded-xl bg-primary-50 text-primary-700">
                            <i class="fas fa-align-left text-sm"></i>
                        </span>
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">Report narrative</h2>
                            <p class="text-xs text-slate-400">What the reporter submitted, in their own words.</p>
                        </div>
                    </div>
                    @if($report->is_live_capture)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-sky-50 px-2.5 py-1 text-[11px] font-semibold text-sky-700 ring-1 ring-sky-200">
                            <i class="fas fa-bolt text-[9px]"></i> Live capture
                        </span>
                    @endif
                </div>

                <div class="p-5">
                    @if(filled($report->description))
                        <p class="text-sm leading-7 text-slate-700">{!! nl2br(e($report->description)) !!}</p>
                    @else
                        <p class="text-sm italic text-slate-400">No description provided.</p>
                    @endif

                    <dl class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                            <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Category</dt>
                            <dd class="mt-1 text-sm font-medium text-slate-700">{{ $categoryName ?? '—' }}</dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                            <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Subcategory</dt>
                            <dd class="mt-1 text-sm font-medium text-slate-700">{{ $report->report_subcategory ?: '—' }}</dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                            <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">District</dt>
                            <dd class="mt-1 text-sm font-medium text-slate-700">{{ $district ?: '—' }}</dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                            <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Place</dt>
                            <dd class="mt-1 text-sm font-medium text-slate-700">{{ $placeName ?: ($report->location_name ?: '—') }}</dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                            <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Source</dt>
                            <dd class="mt-1 text-sm font-medium text-slate-700">{{ ucfirst($report->source ?: 'app') }}</dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                            <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Submitted</dt>
                            <dd class="mt-1 text-sm font-medium text-slate-700">{{ $report->created_at?->format('d M Y, H:i') ?? '—' }}</dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                            <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Expires</dt>
                            <dd class="mt-1 text-sm font-medium text-slate-700">{{ $report->expires_at?->format('d M Y, H:i') ?? 'No expiry' }}</dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                            <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Reference</dt>
                            <dd class="mt-1 text-sm font-medium text-slate-700">{{ $report->bipad_id ? 'BIPAD #'.$report->bipad_id : ($report->provenance ?: '—') }}</dd>
                        </div>
                    </dl>

                    <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div class="rounded-xl border border-slate-100 bg-white px-3 py-2.5 text-center">
                            <p class="text-lg font-semibold text-slate-900">{{ number_format((int) $report->confirmed_by_count) }}</p>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Confirmations</p>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-white px-3 py-2.5 text-center">
                            <p class="text-lg font-semibold text-slate-900">{{ number_format((int) $report->comments_count) }}</p>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Comments</p>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-white px-3 py-2.5 text-center">
                            <p class="text-lg font-semibold text-emerald-600">{{ number_format((int) $report->helpful_count) }}</p>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Helpful</p>
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-white px-3 py-2.5 text-center">
                            <p class="text-lg font-semibold text-rose-600">{{ number_format((int) $report->unhelpful_count) }}</p>
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Unhelpful</p>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Evidence media --}}
            <section id="evidence" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <span class="grid h-9 w-9 place-items-center rounded-xl bg-sky-50 text-sky-700">
                            <i class="fas fa-photo-film text-sm"></i>
                        </span>
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">Evidence media</h2>
                            <p class="text-xs text-slate-400">Photos and files attached by the reporter.</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">
                        <i class="fas fa-paperclip text-[9px]"></i>{{ $report->media->count() }} {{ \Illuminate\Support\Str::plural('file', $report->media->count()) }}
                    </span>
                </div>

                <div class="p-5">
                    @if($images->count())
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach($images as $media)
                                @php $url = $mediaUrl($media); @endphp
                                <figure class="group overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                                    <a href="{{ $url }}" target="_blank" rel="noopener" class="block overflow-hidden">
                                        <img src="{{ $url }}" alt="Report media #{{ $media->id }}" loading="lazy" class="h-52 w-full object-cover transition duration-300 group-hover:scale-[1.02]">
                                    </a>
                                    <figcaption class="flex items-center justify-between gap-2 border-t border-slate-200 bg-white px-3 py-2 text-[11px] text-slate-500">
                                        <span class="font-semibold text-slate-600"><i class="fas fa-image mr-1 text-slate-400"></i>Media #{{ $media->id }}</span>
                                        <a href="{{ $url }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-semibold text-primary-700 transition hover:text-primary-900">
                                            Open full size <i class="fas fa-arrow-up-right-from-square text-[9px]"></i>
                                        </a>
                                    </figcaption>
                                </figure>
                            @endforeach
                        </div>
                    @else
                        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-4 py-10 text-center">
                            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-white text-slate-300 shadow-sm">
                                <i class="fas fa-image text-lg"></i>
                            </span>
                            <p class="mt-3 text-sm font-semibold text-slate-700">No media attached</p>
                            <p class="mt-1 text-xs text-slate-400">This report was submitted without photo or file evidence.</p>
                        </div>
                    @endif

                    @if($otherMedia->count())
                        <ul class="mt-4 space-y-2">
                            @foreach($otherMedia as $media)
                                @php $url = $mediaUrl($media); @endphp
                                <li class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2.5">
                                    <span class="inline-flex min-w-0 items-center gap-2 text-xs text-slate-600">
                                        <i class="fas fa-file text-slate-400"></i>
                                        <span class="truncate font-medium">{{ ucfirst($media->type ?: 'File') }} #{{ $media->id }}</span>
                                    </span>
                                    <a href="{{ $url }}" target="_blank" rel="noopener" class="text-[11px] font-semibold text-primary-700 transition hover:text-primary-900">Open</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>

            {{-- Location + map --}}
            <section id="location-evidence" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <div class="flex items-center gap-3">
                        <span class="grid h-9 w-9 place-items-center rounded-xl bg-emerald-50 text-emerald-700">
                            <i class="fas fa-map-location-dot text-sm"></i>
                        </span>
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">Location &amp; map</h2>
                            <p class="text-xs text-slate-400">Device fix compared with the photo's EXIF coordinates.</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $gpsMeta['chip'] }}">
                        <i class="fas {{ $gpsMeta['icon'] }} text-[9px]"></i>{{ $gpsMeta['label'] }}
                    </span>
                </div>

                <div class="p-5">
                    <div id="map" class="h-[360px] w-full overflow-hidden rounded-xl border border-slate-200 bg-slate-100"></div>
                    <p class="mt-2 text-[11px] text-slate-400">
                        <span class="mr-3 inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-sky-500"></span>Blue = Device location</span>
                        <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-rose-500"></span>Red = Photo EXIF location</span>
                    </p>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2.5">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Device location</p>
                            <p class="mt-1 font-mono text-xs text-slate-700">
                                {{ $report->latitude && $report->longitude ? $report->latitude.', '.$report->longitude : 'Not captured' }}
                            </p>
                            @if($report->location_accuracy !== null)
                                <p class="mt-1 text-[11px] text-slate-400">Accuracy ±{{ rtrim(rtrim((string) $report->location_accuracy, '0'), '.') }} m</p>
                            @endif
                        </div>
                        <div class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2.5">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Photo EXIF location</p>
                            <p class="mt-1 font-mono text-xs text-slate-700">
                                {{ $report->photo_gps_lat && $report->photo_gps_lng ? $report->photo_gps_lat.', '.$report->photo_gps_lng : 'No GPS data' }}
                            </p>
                            @if($report->gps_distance_km !== null)
                                <p class="mt-1 text-[11px] text-slate-400">Distance {{ $report->gps_distance_km }} km</p>
                            @endif
                        </div>
                    </div>

                    <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-2 text-xs sm:grid-cols-2">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-1.5">
                            <dt class="text-slate-400">Photo captured at</dt>
                            <dd class="font-medium text-slate-700">{{ $report->photo_captured_at?->format('d M Y, H:i') ?? 'N/A' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-1.5">
                            <dt class="text-slate-400">Location fix time</dt>
                            <dd class="font-medium text-slate-700">{{ $report->location_timestamp?->format('d M Y, H:i') ?? 'N/A' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-1.5">
                            <dt class="text-slate-400">Location integrity</dt>
                            <dd class="inline-flex items-center gap-1.5 font-semibold {{ $liMeta['class'] }}">
                                <i class="fas {{ $liMeta['icon'] }} text-[10px]"></i>{{ $liMeta['label'] }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-1.5">
                            <dt class="text-slate-400">Mock location</dt>
                            <dd class="font-medium text-slate-700">
                                @if($report->mock_location_detected)
                                    <span class="text-rose-600">Detected</span>
                                @elseif($locationIntegrity)
                                    Not detected
                                @else
                                    Not evaluated
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            {{-- AI review --}}
            @if($report->moderation_message || !empty($analysis))
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <span class="grid h-9 w-9 place-items-center rounded-xl bg-violet-50 text-violet-700">
                                <i class="fas fa-robot text-sm"></i>
                            </span>
                            <div>
                                <h2 class="text-sm font-semibold text-slate-900">AI review</h2>
                                <p class="text-xs text-slate-400">Stored automated assessment — a signal, not the final decision.</p>
                            </div>
                        </div>
                        @if($report->ai_analyzed_at)
                            <span class="text-[11px] text-slate-400">Analysed {{ $report->ai_analyzed_at->diffForHumans() }}</span>
                        @endif
                    </div>

                    <div class="p-5">
                        @php $moderationTone = $status === 'approved' ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : ($status === 'rejected' ? 'bg-rose-50 text-rose-800 ring-rose-200' : 'bg-amber-50 text-amber-800 ring-amber-200'); @endphp
                        @if($report->moderation_message)
                            <div class="rounded-xl p-4 text-sm ring-1 {{ $moderationTone }}">
                                @if($report->authenticity_score !== null)
                                    <p class="text-xs mb-1">
                                        <strong>Overall stored confidence: {{ round((float) $report->authenticity_score * 100) }}%</strong>
                                        <span class="opacity-70">(text + location + image components — not a Vision AI image score)</span>
                                        @if(!$imageCheckEvaluated)
                                            <span class="opacity-70">— Vision AI image check was unavailable, so this reflects only the evaluated checks</span>
                                        @endif
                                    </p>
                                @endif
                                <p class="font-medium">{{ $report->moderation_message }}</p>
                            </div>
                        @endif

                        @if(!empty($analysis['summary']))
                            <div class="mt-4">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Summary</p>
                                <p class="mt-1 text-sm leading-6 text-slate-700">{{ $analysis['summary'] }}</p>
                            </div>
                        @endif

                        @if(!empty($analysis['location_check']['reason']))
                            <div class="mt-3">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Location check</p>
                                <p class="mt-1 text-sm leading-6 text-slate-700">{{ $analysis['location_check']['reason'] }}</p>
                            </div>
                        @endif

                        @if(!empty($imageCheck['message']) && ($imageCheck['reviewed'] ?? 0) > 0)
                            <div class="mt-3">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Images ({{ $imageCheck['reviewed'] }} reviewed)</p>
                                <p class="mt-1 text-sm leading-6 text-slate-700">{{ $imageCheck['message'] }}</p>
                            </div>
                        @endif

                        @if(isset($imageCheck['images']) && count($imageCheck['images']))
                            <ul class="mt-3 space-y-2">
                                @foreach($imageCheck['images'] as $img)
                                    <li class="rounded-xl border border-slate-100 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                        @if(isset($img['screen_probability']) || isset($img['report_match']))
                                            <span class="font-semibold text-slate-700">Media #{{ $img['media_id'] ?? '?' }}</span>
                                            — verdict {{ $img['verdict'] ?? '?' }}
                                            @if(isset($img['screen_probability'])) · screen {{ round((float) $img['screen_probability'] * 100) }}% @endif
                                            @if(isset($img['real_scene_probability'])) · real scene {{ round((float) $img['real_scene_probability'] * 100) }}% @endif
                                            @if(isset($img['report_match'])) · match {{ round((float) $img['report_match'] * 100) }}% @endif
                                        @else
                                            <span class="font-semibold text-slate-700">Media #{{ $img['media_id'] ?? '?' }}</span>
                                        @endif
                                        @if(!empty($img['verdict_reason']) || !empty($img['reason']))
                                            <span class="block mt-1 text-slate-500">{{ $img['verdict_reason'] ?? $img['reason'] }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </section>
            @endif

            {{-- Image Integrity: stored evidence indicators for moderators. Pure display of existing
                 ai_analysis / GPS / media data — no AI call, no image read, no recomputation.
                 Evidence, not conclusions: unknown is never rendered as a negative verdict. --}}
            @isset($imageIntegrity)
            @php
                $iiPillClass = [
                    'ok' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                    'suspicious' => 'bg-amber-50 text-amber-700 ring-amber-200',
                    'unknown' => 'bg-slate-100 text-slate-600 ring-slate-200',
                    'info' => 'bg-sky-50 text-sky-700 ring-sky-200',
                ];
                $iiBannerClass = [
                    'ok' => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
                    'suspicious' => 'bg-amber-50 text-amber-800 ring-amber-200',
                    'unknown' => 'bg-slate-50 text-slate-600 ring-slate-200',
                ];
                $iiBannerIcon = [
                    'ok' => 'fa-check-circle',
                    'suspicious' => 'fa-exclamation-triangle',
                    'unknown' => 'fa-circle-question',
                ];
            @endphp
            <section id="image-integrity" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <div class="flex items-start gap-3">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-amber-50 text-amber-700">
                            <i class="fas fa-shield-halved text-sm"></i>
                        </span>
                        <div>
                            <h2 class="text-sm font-semibold text-slate-900">Image Integrity</h2>
                            <p class="mt-1 max-w-3xl text-xs leading-relaxed text-slate-500">
                                Deterministic screening &amp; AI evidence read from this report's <strong>stored</strong> analysis only —
                                opening this page runs no new AI, image, or fingerprint processing.
                                Indicators are evidence for human review, <strong>not proof</strong> that an image is fake,
                                downloaded, or a screenshot. A moderator makes the final decision.
                            </p>
                        </div>
                    </div>
                    @if(!empty($imageIntegrity['has_analysis']))
                        <div class="text-right text-[11px] text-slate-500">
                            <div><strong>Stored analysis:</strong> {{ $imageIntegrity['analyzed_at'] ?? '—' }}</div>
                            @if($imageIntegrity['check_verdict'])
                                <div><strong>Image check verdict:</strong> {{ $imageIntegrity['check_verdict'] }}</div>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="p-5">
                    @php $iiStatus = $imageIntegrity['status']; @endphp
                    <div class="rounded-xl p-3.5 text-sm ring-1 {{ $iiBannerClass[$iiStatus['tone']] ?? $iiBannerClass['unknown'] }}">
                        <i class="fas {{ $iiBannerIcon[$iiStatus['tone']] ?? $iiBannerIcon['unknown'] }} mr-1"></i>{{ $iiStatus['text'] }}
                    </div>

                    <div class="mt-4 overflow-x-auto rounded-xl border border-slate-100">
                        <table class="w-full min-w-[640px] text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Indicator</th>
                                    <th class="w-56 px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Result</th>
                                    <th class="px-4 py-2.5 text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">Meaning</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($imageIntegrity['indicators'] as $row)
                                    <tr class="align-top">
                                        <td class="px-4 py-2.5 font-medium text-slate-700">{{ $row['label'] }}</td>
                                        <td class="px-4 py-2.5">
                                            <span class="inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold ring-1 {{ $iiPillClass[$row['tone']] ?? $iiPillClass['unknown'] }}">{{ $row['result'] }}</span>
                                        </td>
                                        <td class="px-4 py-2.5 text-xs leading-relaxed text-slate-500">{{ $row['meaning'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if(count($imageIntegrity['images']))
                        <div class="mt-6">
                            <h4 class="text-sm font-semibold text-slate-900">Per-image results</h4>
                            <p class="mt-1 text-xs text-slate-500">Stored result per attached image. "Not evaluated" means the check never ran or its result is not stored — it is not a negative verdict.</p>
                            @foreach($imageIntegrity['images'] as $img)
                                <div class="mt-3 rounded-xl border border-slate-100 bg-slate-50/60 p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <strong class="text-sm text-slate-700"><i class="fas fa-image mr-1 text-slate-400"></i>Image media #{{ $img['media_id'] ?? '?' }}</strong>
                                        @if(count($img['technical']))
                                            <details class="text-xs text-slate-500">
                                                <summary class="cursor-pointer select-none font-medium text-primary-700 hover:text-primary-900"><i class="fas fa-microscope mr-1"></i>Technical details</summary>
                                                <dl class="mt-2 space-y-1">
                                                    @foreach($img['technical'] as $t)
                                                        <div class="flex gap-2"><dt class="shrink-0 font-medium">{{ $t['label'] }}:</dt><dd class="break-all font-mono">{{ $t['value'] }}</dd></div>
                                                    @endforeach
                                                </dl>
                                            </details>
                                        @endif
                                    </div>
                                    <div class="mt-2 overflow-x-auto">
                                        <table class="w-full min-w-[560px]">
                                            <tbody class="divide-y divide-slate-200">
                                                @foreach($img['rows'] as $row)
                                                    <tr class="align-top">
                                                        <td class="w-52 py-1.5 pr-3 font-medium text-slate-700">{{ $row['label'] }}</td>
                                                        <td class="w-48 py-1.5 pr-3">
                                                            <span class="inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold ring-1 {{ $iiPillClass[$row['tone']] ?? $iiPillClass['unknown'] }}">{{ $row['result'] }}</span>
                                                        </td>
                                                        <td class="py-1.5 text-xs leading-relaxed text-slate-500">{{ $row['meaning'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if(count($imageIntegrity['technical']))
                        <div class="mt-5">
                            <details>
                                <summary class="cursor-pointer select-none text-sm font-semibold text-primary-700 hover:text-primary-900"><i class="fas fa-code mr-1"></i>Technical details (stored data)</summary>
                                <dl class="mt-3 grid grid-cols-1 gap-x-6 gap-y-1 text-xs text-slate-600 md:grid-cols-2">
                                    @foreach($imageIntegrity['technical'] as $t)
                                        <div class="flex gap-2"><dt class="shrink-0 font-medium">{{ $t['label'] }}:</dt><dd class="break-all font-mono">{{ $t['value'] }}</dd></div>
                                    @endforeach
                                </dl>
                            </details>
                        </div>
                    @endif
                </div>
            </section>
            @endisset

            {{-- Moderation queue --}}
            @isset($queueItem)
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <span class="grid h-9 w-9 place-items-center rounded-xl bg-slate-100 text-slate-600">
                                <i class="fas fa-list-check text-sm"></i>
                            </span>
                            <div>
                                <h2 class="text-sm font-semibold text-slate-900">Moderation Queue</h2>
                                <p class="text-xs text-slate-400">Queue record and reviewer audit trail.</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1
                            {{ $queueItem->status === 'approved' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : '' }}
                            {{ $queueItem->status === 'pending' ? 'bg-amber-50 text-amber-700 ring-amber-200' : '' }}
                            {{ $queueItem->status === 'rejected' ? 'bg-rose-50 text-rose-700 ring-rose-200' : '' }}">
                            {{ ucfirst($queueItem->status) }}
                        </span>
                    </div>
                    <div class="p-5">
                        <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Queue Status</dt>
                                <dd class="mt-1 text-sm font-medium text-slate-700">{{ ucfirst($queueItem->status) }}</dd>
                            </div>
                            <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                                <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Priority</dt>
                                <dd class="mt-1 text-sm font-medium text-slate-700">{{ ucfirst($queueItem->priority) }}</dd>
                            </div>
                            @if($queueItem->ai_spam_score > 0)
                                <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">AI Spam Score</dt>
                                    <dd class="mt-1 text-sm font-medium text-slate-700">{{ $queueItem->ai_spam_score }}</dd>
                                </div>
                            @endif
                            @if($queueItem->reviewed_by)
                                <div class="rounded-xl bg-slate-50 px-3 py-2.5">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Reviewed by</dt>
                                    <dd class="mt-1 text-sm font-medium text-slate-700">{{ $queueItem->reviewer?->name ?? 'Unknown' }} ({{ $queueItem->reviewed_at?->diffForHumans() }})</dd>
                                </div>
                            @endif
                        </dl>
                        @if($queueItem->rejection_reason)
                            <div class="mt-3 rounded-xl border border-rose-100 bg-rose-50 px-3 py-2.5">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-rose-500">Rejection Reason</p>
                                <p class="mt-1 text-sm leading-relaxed text-rose-800">{{ $queueItem->rejection_reason }}</p>
                            </div>
                        @endif
                    </div>
                </section>
            @endisset
        </div>

        {{-- Action rail --}}
        <aside class="space-y-5">
            {{-- Decision --}}
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">Review decision</h2>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 {{ $statusMeta['chip'] }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $statusMeta['dot'] }}"></span>{{ $statusMeta['label'] }}
                    </span>
                </div>

                <div class="p-5">
                    @if($status === 'pending')
                        @if($canApprove)
                            <p class="text-xs leading-relaxed text-slate-500">Approving publishes an alert on the public map and awards XP to the reporter. Rejecting keeps it off the map.</p>
                            <form method="POST" action="{{ route('admin.reports.approve', $report->id) }}" class="mt-4" onsubmit="return confirm('Approve this report?\n\nThis will publish an alert and award XP to the reporter.')">
                                @csrf
                                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">
                                    <i class="fas fa-check"></i> Approve report
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.reports.reject', $report->id) }}" class="mt-2" onsubmit="return confirm('Reject this report?')">
                                @csrf
                                <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-700">
                                    <i class="fas fa-times"></i> Reject report
                                </button>
                            </form>
                            <p class="mt-3 text-[11px] text-slate-400">
                                Shortcuts: <kbd class="rounded border border-slate-200 bg-slate-50 px-1 py-0.5 font-mono text-slate-600">A</kbd> approve ·
                                <kbd class="rounded border border-slate-200 bg-slate-50 px-1 py-0.5 font-mono text-slate-600">R</kbd> reject
                            </p>
                        @else
                            <div class="rounded-xl border border-amber-100 bg-amber-50 px-3 py-2.5 text-xs leading-relaxed text-amber-800">
                                <i class="fas fa-lock mr-1"></i>Your role can view reports but cannot approve or reject them.
                            </div>
                        @endif
                    @else
                        @php
                            $decisionTone = $status === 'approved' ? 'border-emerald-100 bg-emerald-50 text-emerald-800' : 'border-rose-100 bg-rose-50 text-rose-800';
                        @endphp
                        <div class="rounded-xl border px-4 py-3.5 {{ $decisionTone }}">
                            <p class="inline-flex items-center gap-2 text-sm font-semibold">
                                <i class="fas {{ $statusMeta['icon'] }}"></i>{{ $statusMeta['label'] }}
                            </p>
                            <p class="mt-1 text-xs opacity-80">
                                {{ $report->verified_at?->format('d M Y, H:i') ?? 'Decision recorded' }}
                                @if($verifier)
                                    · by {{ $verifier->name }}
                                @endif
                            </p>
                        </div>
                        @if($status === 'approved')
                            <p class="mt-3 text-xs leading-relaxed text-slate-500">This report is live on the public map. Rejecting it again revokes the reporter's approval XP.</p>
                        @else
                            <p class="mt-3 text-xs leading-relaxed text-slate-500">This report stays hidden from the public map. Approving it publishes the alert and awards XP.</p>
                        @endif

                        @if($canApprove)
                            <div class="mt-4 border-t border-slate-100 pt-4">
                                <p class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Change decision</p>
                                <div class="mt-2 flex flex-col gap-2">
                                    @if($status !== 'approved')
                                        <form method="POST" action="{{ route('admin.reports.approve', $report->id) }}" onsubmit="return confirm('Approve this report?\n\nThis will publish an alert and award XP to the reporter.')">
                                            @csrf
                                            <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100">
                                                <i class="fas fa-check"></i> Approve this report
                                            </button>
                                        </form>
                                    @endif
                                    @if($status !== 'rejected')
                                        <form method="POST" action="{{ route('admin.reports.reject', $report->id) }}" onsubmit="return confirm('Reject this report?')">
                                            @csrf
                                            <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-100">
                                                <i class="fas fa-times"></i> Reject this report
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </section>

            {{-- Verification signals --}}
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">Verification signals</h2>
                    <p class="text-xs text-slate-400">Stored checks to support the decision.</p>
                </div>
                <div class="p-5">
                    <div class="flex items-center justify-between gap-3 text-xs">
                        <span class="font-medium text-slate-600">Stored confidence</span>
                        <span class="font-semibold text-slate-900">{{ $confidence !== null ? $confidence.'%' : '—' }}</span>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full {{ $confidence !== null && $confidence >= 70 ? 'bg-emerald-500' : ($confidence !== null && $confidence >= 40 ? 'bg-amber-500' : 'bg-slate-300') }}" style="width: {{ $confidence ?? 0 }}%"></div>
                    </div>

                    <dl class="mt-4 space-y-2.5 text-xs">
                        <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-2.5">
                            <dt class="text-slate-400">GPS verification</dt>
                            <dd class="inline-flex items-center gap-1.5 text-right font-semibold {{ $gpsMeta['class'] }}">
                                <i class="fas {{ $gpsMeta['icon'] }} text-[10px]"></i>
                                {{ $gpsMeta['label'] }}
                                @if($report->gps_distance_km !== null)
                                    <span class="font-normal text-slate-400">({{ $report->gps_distance_km }} km)</span>
                                @endif
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-2.5">
                            <dt class="text-slate-400">Location integrity</dt>
                            <dd class="inline-flex items-center gap-1.5 text-right font-semibold {{ $liMeta['class'] }}">
                                <i class="fas {{ $liMeta['icon'] }} text-[10px]"></i>{{ $liMeta['label'] }}
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-2.5">
                            <dt class="text-slate-400">Mock location</dt>
                            <dd class="font-semibold text-slate-700">
                                @if($report->mock_location_detected)
                                    <span class="text-rose-600">Detected</span>
                                @elseif($locationIntegrity)
                                    Not detected
                                @else
                                    Not evaluated
                                @endif
                            </dd>
                        </div>
                        <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-2.5">
                            <dt class="text-slate-400">Live capture</dt>
                            <dd class="font-semibold text-slate-700">{{ $report->is_live_capture ? 'Yes' : 'No' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-3">
                            <dt class="text-slate-400">Vision AI image check</dt>
                            <dd class="text-right font-semibold {{ $imageCheckEvaluated ? 'text-slate-700' : 'text-slate-400' }}">
                                {{ $imageCheckEvaluated ? ucfirst($imageCheckVerdict) : 'Not evaluated' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </section>

            {{-- Reporter --}}
            <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="text-sm font-semibold text-slate-900">Reporter</h2>
                    <p class="text-xs text-slate-400">Account behind this submission.</p>
                </div>
                <div class="p-5">
                    <div class="flex items-center gap-3">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-primary-700 text-base font-semibold text-white">
                            {{ $initials($reporter?->name) }}
                        </span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ $reporter?->name ?? 'Anonymous' }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $reporter?->email ?? 'No account linked' }}</p>
                        </div>
                    </div>

                    @if($reporter)
                        <dl class="mt-4 space-y-2 text-xs">
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-400">Account status</dt>
                                <dd class="font-semibold {{ $reporter->status === 'active' ? 'text-emerald-600' : 'text-amber-600' }}">{{ ucfirst($reporter->status ?? 'unknown') }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-400">Member since</dt>
                                <dd class="font-medium text-slate-700">{{ $reporter->created_at?->format('M Y') ?? '—' }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-400">Total XP</dt>
                                <dd class="font-medium text-slate-700">{{ number_format((int) $reporter->total_xp) }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-400">User ID</dt>
                                <dd class="font-mono text-slate-500">#{{ $reporter->id }}</dd>
                            </div>
                        </dl>
                    @endif
                </div>
            </section>

            {{-- Shortcuts --}}
            @if(($status === 'pending' && $canApprove) || $prevReportId || $nextReportId)
                <section class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 p-4">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-400">Keyboard shortcuts</p>
                    <div class="mt-2 flex flex-wrap gap-2 text-[11px] text-slate-500">
                        @if($status === 'pending' && $canApprove)
                            <span class="inline-flex items-center gap-1.5"><kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-slate-600">A</kbd> Approve</span>
                            <span class="inline-flex items-center gap-1.5"><kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-slate-600">R</kbd> Reject</span>
                        @endif
                        @if($prevReportId)
                            <span class="inline-flex items-center gap-1.5"><kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-slate-600">←</kbd> Previous</span>
                        @endif
                        @if($nextReportId)
                            <span class="inline-flex items-center gap-1.5"><kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-slate-600">→</kbd> Next</span>
                        @endif
                        <span class="inline-flex items-center gap-1.5"><kbd class="rounded border border-slate-200 bg-white px-1.5 py-0.5 font-mono text-slate-600">Esc</kbd> Back to list</span>
                    </div>
                </section>
            @endif
        </aside>
    </div>
</div>

<div id="reportToast" class="pointer-events-none fixed inset-x-0 top-4 z-[60] flex justify-center px-4 opacity-0 transition-opacity duration-200">
    <div class="rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white shadow-xl" data-toast-message>Report copied</div>
</div>
@endsection

@section('scripts')
<script>
(function () {
    'use strict';

    var root = document.getElementById('adminReportDetail');
    if (!root) return;

    var reportId = @json($report->id);
    var canApprove = @json($status === 'pending' && $canApprove);

    // ---------------------------------------------------------------
    // Toast + clipboard
    // ---------------------------------------------------------------
    window.showToast = function (message) {
        var host = document.getElementById('reportToast');
        if (!host) return;
        var label = host.querySelector('[data-toast-message]');
        if (label) label.textContent = message;
        host.classList.remove('opacity-0');
        host.classList.add('opacity-100');
        clearTimeout(window.__reportToastTimer);
        window.__reportToastTimer = setTimeout(function () {
            host.classList.add('opacity-0');
            host.classList.remove('opacity-100');
        }, 2000);
    };

    window.copyReportId = function () {
        var text = '#' + reportId;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                window.showToast('Report ID copied');
            }).catch(function () {
                window.showToast(text);
            });
        } else {
            window.showToast(text);
        }
    };

    // ---------------------------------------------------------------
    // Keyboard shortcuts (page-scoped: stale listeners from a previous
    // SPA page must not fire once this report pane is gone).
    // ---------------------------------------------------------------
    document.addEventListener('keydown', function (event) {
        if (!document.body.contains(root)) return;
        if (event.defaultPrevented) return;

        var target = event.target;
        if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT' || target.isContentEditable)) return;
        if (event.metaKey || event.ctrlKey || event.altKey) return;

        if (canApprove && (event.key === 'a' || event.key === 'A')) {
            var approveForm = root.querySelector('form[action*="approve"]');
            if (approveForm && confirm('Approve this report?\n\nThis will publish an alert and award XP to the reporter.')) {
                event.preventDefault();
                approveForm.submit();
            }
            return;
        }

        if (canApprove && (event.key === 'r' || event.key === 'R')) {
            var rejectForm = root.querySelector('form[action*="reject"]');
            if (rejectForm && confirm('Reject this report?')) {
                event.preventDefault();
                rejectForm.submit();
            }
            return;
        }

        @if($prevReportId)
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            window.location.href = '{{ route("admin.reports.view", array_merge(["id" => $prevReportId], $listParams ?? [])) }}';
            return;
        }
        @endif
        @if($nextReportId)
        if (event.key === 'ArrowRight') {
            event.preventDefault();
            window.location.href = '{{ route("admin.reports.view", array_merge(["id" => $nextReportId], $listParams ?? [])) }}';
            return;
        }
        @endif

        if (event.key === 'Escape') {
            event.preventDefault();
            window.location.href = '{{ route("admin.reports", $listParams ?? []) }}';
        }
    });

    // ---------------------------------------------------------------
    // Map (Leaflet is loaded on demand so SPA navigation from the list
    // never runs the inline init before the library exists).
    // ---------------------------------------------------------------
    function initReportMap() {
        var el = document.getElementById('map');
        if (!el || el._leaflet_map || !window.L) return;

        var map = L.map(el).setView([27.7, 85.3], 7);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        var deviceLat = {{ $report->latitude ?? 'null' }};
        var deviceLng = {{ $report->longitude ?? 'null' }};
        var photoLat = {{ $report->photo_gps_lat ?? 'null' }};
        var photoLng = {{ $report->photo_gps_lng ?? 'null' }};

        var bounds = [];
        if (deviceLat && deviceLng) {
            L.circleMarker([deviceLat, deviceLng], {
                radius: 11, color: '#0ea5e9', weight: 3, fillColor: '#ffffff', fillOpacity: 1
            }).addTo(map).bindPopup('Device location');
            bounds.push([deviceLat, deviceLng]);
        }
        if (photoLat && photoLng) {
            L.circleMarker([photoLat, photoLng], {
                radius: 5, color: '#ffffff', weight: 2, fillColor: '#f43f5e', fillOpacity: 1
            }).addTo(map).bindPopup('Photo EXIF location').bringToFront();
            bounds.push([photoLat, photoLng]);
        }

        if (bounds.length === 1) {
            map.setView(bounds[0], 13);
        } else if (bounds.length > 1) {
            map.fitBounds(bounds, { padding: [40, 40] });
        }

        el._leaflet_map = map;
        setTimeout(function () { map.invalidateSize(); }, 60);
    }

    function ensureLeaflet(callback) {
        if (window.L) { callback(); return; }

        if (!document.getElementById('reportLeafletCss')) {
            var link = document.createElement('link');
            link.id = 'reportLeafletCss';
            link.rel = 'stylesheet';
            link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            document.head.appendChild(link);
        }

        var existing = document.querySelector('script[data-report-leaflet]');
        if (existing) {
            if (existing.getAttribute('data-loaded') === '1' && window.L) { callback(); return; }
            existing.addEventListener('load', callback, { once: true });
            return;
        }

        var script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.setAttribute('data-report-leaflet', '1');
        script.addEventListener('load', function () {
            script.setAttribute('data-loaded', '1');
            callback();
        }, { once: true });
        script.addEventListener('error', function () {
            var el = document.getElementById('map');
            if (el) {
                el.innerHTML = '<div class="grid h-full place-items-center text-xs text-slate-400">Map unavailable — check your connection.</div>';
            }
        }, { once: true });
        document.head.appendChild(script);
    }

    ensureLeaflet(initReportMap);
})();
</script>
@endsection
