<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h2 class="font-semibold text-xl text-white leading-tight">Dashboard</h2>
                <p class="text-xs text-white/60 mt-0.5">Kretivco Job Management · {{ now()->format('l, j F Y') }}</p>
            </div>
            <span class="text-xs font-medium bg-white/20 rounded-full px-4 py-1.5 text-white">
                {{ auth()->user()->isBod() ? 'All Departments' : $visibleDepartments->count().' Dept View' }}
            </span>
        </div>
    </x-slot>

    <div class="p-6 space-y-6">

        {{-- Row 1: Stat cards. Each card's icon sits in a colour-tinted badge
        (not just a left border strip) so the eye has a distinct anchor per
        category instead of four identical glass boxes in a row. --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="os-card os-card--stat rounded-xl p-5">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center text-base mb-3" style="background:#E91E6326">💼</div>
                <div class="text-[28px] font-bold leading-tight text-white">{{ $stats['total'] }}</div>
                <div class="text-glow text-[11px] font-medium uppercase tracking-wide mt-1">Total jobs</div>
                <div class="text-xs text-white/50 mt-2">{{ $stats['completed_count'] }} completed</div>
            </div>
            <div class="os-card os-card--stat rounded-xl p-5">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center text-base mb-3" style="background:#6366F126">🎯</div>
                <div class="text-[28px] font-bold leading-tight text-white">{{ $stats['potential_count'] }}</div>
                <div class="text-glow text-[11px] font-medium uppercase tracking-wide mt-1">Potential</div>
                <div class="text-xs text-white/50 mt-2">RM {{ number_format($stats['potential_value'], 2) }}</div>
            </div>
            <div class="os-card os-card--stat rounded-xl p-5">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center text-base mb-3" style="background:#3A86FF26">⚡</div>
                <div class="text-[28px] font-bold leading-tight text-white">{{ $stats['in_progress_count'] }}</div>
                <div class="text-glow text-[11px] font-medium uppercase tracking-wide mt-1">In progress</div>
                <div class="text-xs text-white/50 mt-2">Claimed &amp; working</div>
            </div>
            <div class="os-card os-card--stat rounded-xl p-5">
                <div class="w-9 h-9 rounded-lg flex items-center justify-center text-base mb-3" style="background:#EF444426">🚫</div>
                <div class="text-[28px] font-bold leading-tight text-white">{{ $stats['cancelled_count'] }}</div>
                <div class="text-glow text-[11px] font-medium uppercase tracking-wide mt-1">Cancelled</div>
                <div class="text-xs text-white/50 mt-2">Not proceeded</div>
            </div>
        </div>

        {{-- Needs Attention — surfaced right under the stat cards so the
        most actionable jobs (overdue / due soon) sit above the fold,
        instead of being the last thing on the page. --}}
        <div class="os-card rounded-xl p-5">
            <div class="flex items-center gap-2 mb-4">
                <span class="text-red-500">⚠</span>
                <span class="text-base font-bold text-white">Needs Attention</span>
                @if ($alerts->isNotEmpty())
                    <span class="text-[11px] font-semibold text-white bg-red-500 rounded-full px-2.5 py-0.5">{{ $alerts->count() }}</span>
                @endif
            </div>
            @if ($alerts->isEmpty())
                <div class="py-8 text-center text-white/40 text-sm">No jobs nearing deadline. Great job!</div>
            @else
                <div class="flex flex-col gap-2">
                    @foreach ($alerts as $job)
                        @php
                            $days = (int) now()->startOfDay()->diffInDays($job->deadline, false);
                            $over = $days < 0;
                            $dept = config('kretivco.departments.'.$job->department);
                        @endphp
                        <a href="{{ route('jobs.show', $job) }}" class="flex items-center gap-3 px-4 py-3 rounded-lg border-l-4" style="border-color:{{ $over ? '#EF4444' : '#F59E0B' }};background:{{ $over ? '#EF444414' : '#F59E0B14' }}">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs font-semibold text-white">{{ $job->job_id }}</span>
                                    @if ($dept)
                                        <span class="text-[11px] font-semibold rounded px-2 py-0.5" style="color:{{ $dept['color'] }};background:{{ $dept['color'] }}22">{{ $dept['label'] }}</span>
                                    @endif
                                </div>
                                <div class="text-sm mt-0.5 truncate text-white/80">{{ $job->customer?->name }}</div>
                            </div>
                            <div class="text-right text-sm font-semibold shrink-0" style="color:{{ $over ? '#EF4444' : '#F59E0B' }}">
                                {{ $over ? abs($days).' days overdue' : $days.' days left' }}
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Row 2: Financial summary. These three carry the page's biggest
        numbers, so they get a slightly heavier treatment than the stat row
        above — a top accent bar in the card's colour instead of a badge,
        reading as "the money row" at a glance. --}}
        <div class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-[180px] os-card rounded-xl p-5 border-t-2" style="border-top-color:#E91E63">
                <div class="text-glow text-[11px] font-medium uppercase tracking-wide mb-2">Pipeline value</div>
                <div class="text-2xl font-bold" style="color:#F0668F">RM {{ number_format($stats['pipeline_value'], 2) }}</div>
                <div class="text-xs text-white/50 mt-1">Potential + in progress</div>
            </div>
            <div class="flex-1 min-w-[180px] os-card rounded-xl p-5 border-t-2" style="border-top-color:#6366F1">
                <div class="text-glow text-[11px] font-medium uppercase tracking-wide mb-2">Potential value</div>
                <div class="text-2xl font-bold" style="color:#9B95F5">RM {{ number_format($stats['potential_value'], 2) }}</div>
                <div class="text-xs text-white/50 mt-1">Not confirmed yet</div>
            </div>
            <div class="flex-1 min-w-[180px] os-card rounded-xl p-5 border-t-2" style="border-top-color:#10B981">
                <div class="text-glow text-[11px] font-medium uppercase tracking-wide mb-2">Actual revenue</div>
                <div class="text-2xl font-bold" style="color:#5DCAA5">RM {{ number_format($stats['actual_revenue'], 2) }}</div>
                <div class="text-xs text-white/50 mt-1">{{ $stats['completed_count'] }} jobs completed</div>
            </div>
        </div>

        {{-- Row 3: Job by Department + Conversion Funnel --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="os-card rounded-xl p-5">
                <div class="text-base font-bold text-white mb-4">Job by Department</div>
                @php $deptTotal = $deptBreakdown->sum(); @endphp
                @if ($deptTotal === 0)
                    <div class="py-8 text-center text-white/40 text-sm">No jobs to display.</div>
                @else
                    <div class="flex items-center justify-center gap-6 flex-wrap">
                        @php
                            $r = 80; $cx = 100; $cy = 100; $stroke = 32;
                            $circumference = 2 * M_PI * $r;
                            $accumulated = 0;
                        @endphp
                        <svg width="200" height="200" viewBox="0 0 200 200" class="shrink-0">
                            @foreach ($deptBreakdown as $key => $count)
                                @continue($count === 0)
                                @php
                                    $pct = $count / $deptTotal;
                                    $dashLen = $pct * $circumference;
                                    $dashOff = -$accumulated * $circumference;
                                    $accumulated += $pct;
                                    $color = config("kretivco.departments.$key.color");
                                @endphp
                                <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" fill="none" stroke="{{ $color }}" stroke-width="{{ $stroke }}"
                                    stroke-dasharray="{{ $dashLen }} {{ $circumference - $dashLen }}" stroke-dashoffset="{{ $dashOff }}"
                                    style="transform:rotate(-90deg);transform-origin:center" />
                            @endforeach
                            <text x="{{ $cx }}" y="{{ $cy - 6 }}" text-anchor="middle" style="font-size:24px;font-weight:700;fill:#fff">{{ $deptTotal }}</text>
                            <text x="{{ $cx }}" y="{{ $cy + 12 }}" text-anchor="middle" style="font-size:11px;fill:rgba(255,255,255,0.5)">Jobs</text>
                        </svg>
                        <div class="flex flex-col gap-2.5">
                            @foreach ($visibleDepartments as $key => $dept)
                                <div class="flex items-center gap-2">
                                    <div class="w-2.5 h-2.5 rounded-full shrink-0" style="background:{{ $dept['color'] }}"></div>
                                    <span class="text-[13px] font-semibold min-w-[110px]" style="color:{{ $dept['color'] }}">{{ $dept['label'] }}</span>
                                    <span class="text-[13px] font-bold text-white">{{ $deptBreakdown[$key] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="os-card rounded-xl p-5">
                <div class="text-base font-bold text-white">Conversion Funnel</div>
                <p class="text-xs text-white/50 mb-5">Potential → In Progress → Completed</p>
                <div class="flex items-center gap-1.5">
                    @php
                        $stages = [
                            ['l' => 'Potential', 'n' => $stats['potential_count'], 'v' => $stats['potential_value'], 'c' => '#6366F1'],
                            ['l' => 'In Progress', 'n' => $stats['in_progress_count'], 'v' => $stats['in_progress_value'], 'c' => '#3A86FF'],
                            ['l' => 'Completed', 'n' => $stats['completed_count'], 'v' => $stats['actual_revenue'], 'c' => '#6B7280', 'last' => true],
                        ];
                    @endphp
                    @foreach ($stages as $s)
                        <div class="flex items-center gap-1.5 flex-1">
                            <div class="text-center w-full py-3.5 px-2 min-h-[100px] flex flex-col items-center justify-center rounded-lg border" style="background:{{ $s['c'] }}1a;border-color:{{ $s['c'] }}55">
                                <div class="text-[11px] font-medium uppercase tracking-wide" style="color:{{ $s['c'] }}">{{ $s['l'] }}</div>
                                <div class="text-xl font-bold mt-1 text-white">{{ $s['n'] }}</div>
                                <div class="text-[11px] text-white/50 mt-0.5">RM {{ number_format($s['v'], 2) }}</div>
                                @if (!empty($s['last']))
                                    <div class="text-[10px] text-white/40 mt-1">{{ $conversionPct }}% conversion</div>
                                @endif
                            </div>
                            @if (empty($s['last']))
                                <div class="text-white/30 text-base shrink-0">›</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Row 4: Recent Jobs --}}
        <div class="os-card rounded-xl p-5">
            <div class="text-base font-bold text-white mb-4">Recent Jobs</div>
            @if ($recent->isEmpty())
                <div class="text-white/40 text-sm">No jobs.</div>
            @else
                <div class="divide-y divide-white/10">
                    @foreach ($recent as $job)
                        @php $st = config('kretivco.job_statuses.'.$job->status); $dept = config('kretivco.departments.'.$job->department); @endphp
                        <a href="{{ route('jobs.show', $job) }}" class="flex items-center gap-3 py-2.5 hover:bg-white/[.03] rounded-lg px-2 -mx-2">
                            <span class="font-mono text-xs font-semibold text-white/80">{{ $job->job_id }}</span>
                            <span class="flex-1 text-sm truncate text-white/80">{{ $job->customer?->name ?? '—' }}{{ $job->customer?->company ? ' · '.$job->customer->company : '' }}</span>
                            @if ($dept)
                                <span class="text-[11px] font-semibold rounded px-2 py-0.5" style="color:{{ $dept['color'] }};background:{{ $dept['color'] }}22">{{ $dept['label'] }}</span>
                            @endif
                            @if ($st)
                                <span class="text-xs font-semibold rounded-full px-3 py-1" style="color:{{ $st['color'] }};background:{{ $st['color'] }}22">{{ $st['icon'] ?? '' }} {{ $st['label'] }}</span>
                            @endif
                            <span class="font-semibold text-sm min-w-[90px] text-right text-white">RM {{ number_format($job->estimation_value ?? 0, 2) }}</span>
                            <span class="text-xs text-white/40 min-w-[80px]">{{ $job->created_at->format('d M Y') }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

    </div>
</x-app-layout>
