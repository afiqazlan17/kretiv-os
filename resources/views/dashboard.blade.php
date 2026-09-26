<x-app-layout>
    @php
        $user = auth()->user();
        $hour = (int) now()->format('G');
        $greeting = $hour < 12 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat tengah hari' : ($hour < 19 ? 'Selamat petang' : 'Selamat malam'));
        $first = \Illuminate\Support\Str::of($user->name)->before(' ');

        // One plain line under the greeting, driven by the same deadline
        // data as "Needs Attention", so the first thing staff read is a
        // sentence about their day rather than a wall of numbers.
        $overdueCount = $alerts->filter(fn ($j) => now()->startOfDay()->diffInDays($j->deadline, false) < 0)->count();
        $mood = match (true) {
            $overdueCount > 0 => $overdueCount.' job dah lepas deadline. Jom settle hari ni.',
            $alerts->isNotEmpty() => $alerts->count().' job hampir deadline dalam 3 hari.',
            $stats['in_progress_count'] > 0 => $stats['in_progress_count'].' job sedang berjalan, semua ikut jadual.',
            default => 'Semua job dah beres. Apa projek seterusnya?',
        };
    @endphp

    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
                <h2 class="font-bold text-2xl text-white leading-tight">{{ $greeting }}, {{ $first }}</h2>
                <p class="text-sm text-white/90 mt-1">{{ $mood }}</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-medium bg-white/20 rounded-full px-3.5 py-1.5 text-white">{{ now()->format('l, j M Y') }}</span>
                <span class="text-xs font-semibold bg-white text-[#C2185B] rounded-full px-3.5 py-1.5">
                    {{ $user->isBod() ? 'All Departments' : $visibleDepartments->count().' Dept View' }}
                </span>
            </div>
        </div>
    </x-slot>

    <div class="p-5 md:p-7 space-y-6">

        {{-- Row 1: KPI tiles. Each has a solid gradient icon badge in its own
        colour plus a faint matching bubble in the corner, so the row reads as
        four friendly, distinct tiles rather than four identical boxes.
        Numbers stay in dark ink; colour marks identity, text stays readable. --}}
        @php
            $tiles = [
                ['icon' => 'briefcase-business', 'n' => $stats['total'], 'label' => 'Total jobs', 'sub' => $stats['completed_count'].' completed', 'c1' => '#E91E63', 'c2' => '#FF7A9C'],
                ['icon' => 'target', 'n' => $stats['potential_count'], 'label' => 'Potential', 'sub' => 'RM '.number_format($stats['potential_value'], 2), 'c1' => '#6366F1', 'c2' => '#9B8CFF'],
                ['icon' => 'zap', 'n' => $stats['in_progress_count'], 'label' => 'In progress', 'sub' => 'Claimed & working', 'c1' => '#3A86FF', 'c2' => '#6FB7FF'],
                ['icon' => 'circle-x', 'n' => $stats['cancelled_count'], 'label' => 'Cancelled', 'sub' => 'Did not proceed', 'c1' => '#EF4444', 'c2' => '#FF8A7A'],
            ];
        @endphp
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach ($tiles as $t)
                <div class="k-card k-card--hover relative overflow-hidden p-5">
                    <div class="absolute -top-8 -right-8 w-28 h-28 rounded-full" style="background:{{ $t['c1'] }}12"></div>
                    <div class="relative w-11 h-11 rounded-2xl flex items-center justify-center text-white"
                         style="background:linear-gradient(135deg,{{ $t['c1'] }},{{ $t['c2'] }});box-shadow:0 8px 18px -8px {{ $t['c1'] }}"><x-icon :name="$t['icon']" class="w-5 h-5" /></div>
                    <div class="relative mt-4 text-3xl font-extrabold text-gray-900 leading-none">{{ $t['n'] }}</div>
                    <div class="relative mt-1.5 text-sm font-semibold text-gray-700">{{ $t['label'] }}</div>
                    <div class="relative text-xs text-gray-400 mt-0.5 truncate">{{ $t['sub'] }}</div>
                </div>
            @endforeach
        </div>

        {{-- Needs Attention sits right under the KPIs so the most actionable
        jobs are above the fold. When there's nothing to chase it shows a calm
        green "all clear" instead of a grey "nothing here". --}}
        <div class="k-card p-5 md:p-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="w-8 h-8 rounded-xl bg-[#FFF1EC] text-[#E4572E] flex items-center justify-center"><x-icon name="alarm-clock" class="w-4 h-4" /></span>
                <span class="text-base font-bold text-gray-900">Needs Attention</span>
                @if ($alerts->isNotEmpty())
                    <span class="text-[11px] font-bold text-white bg-gradient-to-r from-[#EF4444] to-[#F97316] rounded-full px-2.5 py-0.5">{{ $alerts->count() }}</span>
                @endif
            </div>
            @if ($alerts->isEmpty())
                <div class="flex items-center gap-4 rounded-2xl bg-gradient-to-r from-[#ECFDF5] to-[#F0FDF4] border border-[#BBF7D0] px-5 py-4">
                    <span class="w-10 h-10 rounded-full bg-[#10B981] text-white flex items-center justify-center shrink-0"><x-icon name="circle-check" class="w-5 h-5" /></span>
                    <div>
                        <div class="font-bold text-[#047857]">Semua on track</div>
                        <div class="text-sm text-[#059669]">Tiada job yang hampir deadline dalam 3 hari.</div>
                    </div>
                </div>
            @else
                <div class="flex flex-col gap-2">
                    @foreach ($alerts as $job)
                        @php
                            $days = (int) now()->startOfDay()->diffInDays($job->deadline, false);
                            $over = $days < 0;
                            $dept = config('kretivco.departments.'.$job->department);
                        @endphp
                        <a href="{{ route('jobs.show', $job) }}"
                           class="flex items-center gap-3 px-4 py-3 rounded-xl border transition-colors {{ $over ? 'bg-[#FFF5F5] border-[#FDE2E2] hover:bg-[#FFECEC]' : 'bg-[#FFFAF0] border-[#FDEBC8] hover:bg-[#FFF4DD]' }}">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0 {{ $over ? 'bg-[#EF4444]' : 'bg-[#F59E0B]' }}"></span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs font-semibold text-gray-500 whitespace-nowrap">{{ $job->job_id }}</span>
                                    @if ($dept)
                                        <span class="text-[11px] font-semibold rounded-full px-2 py-0.5" style="color:{{ $dept['color'] }};background:{{ $dept['color'] }}14">{{ $dept['label'] }}</span>
                                    @endif
                                </div>
                                <div class="text-sm font-medium mt-0.5 truncate text-gray-800">{{ $job->customer?->name }}</div>
                            </div>
                            <span class="text-xs font-bold shrink-0 rounded-full px-3 py-1 {{ $over ? 'bg-[#FEE2E2] text-[#B91C1C]' : 'bg-[#FEF3C7] text-[#B45309]' }}">
                                {{ $over ? abs($days).' hari lewat' : ($days === 0 ? 'Hari ini' : $days.' hari lagi') }}
                            </span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Row 2: Money. Dark ink figures with a coloured accent pill, so
        the amounts are the easiest thing on the page to read. --}}
        @php
            $money = [
                ['icon' => 'trending-up', 'label' => 'Pipeline value', 'v' => $stats['pipeline_value'], 'sub' => 'Potential + in progress', 'c' => '#E91E63', 'bg' => '#FFF0F5'],
                ['icon' => 'sprout', 'label' => 'Potential value', 'v' => $stats['potential_value'], 'sub' => 'Not confirmed yet', 'c' => '#6366F1', 'bg' => '#F1F1FF'],
                ['icon' => 'wallet', 'label' => 'Actual revenue', 'v' => $stats['actual_revenue'], 'sub' => $stats['completed_count'].' '.\Illuminate\Support\Str::plural('job', $stats['completed_count']).' completed', 'c' => '#10B981', 'bg' => '#ECFDF5'],
            ];
        @endphp
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach ($money as $m)
                <div class="k-card k-card--hover p-5 flex items-start gap-4">
                    <div class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0" style="background:{{ $m['bg'] }};color:{{ $m['c'] }}"><x-icon :name="$m['icon']" class="w-6 h-6" /></div>
                    <div class="min-w-0">
                        <div class="text-xs font-semibold uppercase tracking-wide" style="color:{{ $m['c'] }}">{{ $m['label'] }}</div>
                        <div class="text-2xl font-extrabold text-gray-900 mt-1 truncate">RM {{ number_format($m['v'], 2) }}</div>
                        <div class="text-xs text-gray-400 mt-0.5">{{ $m['sub'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Row 3: Job by Department + Conversion Funnel --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="k-card p-5 md:p-6">
                <div class="text-base font-bold text-gray-900 mb-4">Job by Department</div>
                @php $deptTotal = $deptBreakdown->sum(); @endphp
                @if ($deptTotal === 0)
                    <div class="py-8 text-center text-gray-400 text-sm">No jobs to display yet.</div>
                @else
                    <div class="flex items-center justify-center gap-8 flex-wrap">
                        @php
                            $r = 76; $cx = 100; $cy = 100; $stroke = 26;
                            $circumference = 2 * M_PI * $r;
                            $accumulated = 0;
                            // 2px white gap between segments (only when there's more than one)
                            $gap = $deptBreakdown->filter()->count() > 1 ? 3 : 0;
                        @endphp
                        <svg width="200" height="200" viewBox="0 0 200 200" class="shrink-0" role="img" aria-label="Jobs by department">
                            <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" fill="none" stroke="#FBF1EC" stroke-width="{{ $stroke }}" />
                            @foreach ($deptBreakdown as $key => $count)
                                @continue($count === 0)
                                @php
                                    $pct = $count / $deptTotal;
                                    $dashLen = max($pct * $circumference - $gap, 1);
                                    $dashOff = -$accumulated * $circumference;
                                    $accumulated += $pct;
                                    $dept = config("kretivco.departments.$key");
                                @endphp
                                <circle cx="{{ $cx }}" cy="{{ $cy }}" r="{{ $r }}" fill="none" stroke="{{ $dept['color'] }}" stroke-width="{{ $stroke }}"
                                    stroke-dasharray="{{ $dashLen }} {{ $circumference - $dashLen }}" stroke-dashoffset="{{ $dashOff }}"
                                    style="transform:rotate(-90deg);transform-origin:center"><title>{{ $dept['label'] }}: {{ $count }} ({{ round($pct * 100) }}%)</title></circle>
                            @endforeach
                            <text x="{{ $cx }}" y="{{ $cy + 2 }}" text-anchor="middle" style="font-size:30px;font-weight:800;fill:#111827">{{ $deptTotal }}</text>
                            <text x="{{ $cx }}" y="{{ $cy + 22 }}" text-anchor="middle" style="font-size:12px;fill:#9CA3AF">jobs</text>
                        </svg>
                        <div class="flex flex-col gap-3">
                            @foreach ($visibleDepartments as $key => $dept)
                                <div class="flex items-center gap-2.5">
                                    <span class="w-3 h-3 rounded-full shrink-0" style="background:{{ $dept['color'] }}"></span>
                                    <span class="text-sm font-medium text-gray-700 min-w-[100px]">{{ $dept['label'] }}</span>
                                    <span class="text-sm font-bold text-gray-900 w-5 text-right">{{ $deptBreakdown[$key] }}</span>
                                    <span class="text-xs text-gray-400 w-9 text-right">{{ $deptTotal ? round($deptBreakdown[$key] / $deptTotal * 100) : 0 }}%</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="k-card p-5 md:p-6">
                <div class="flex items-start justify-between gap-3 mb-5">
                    <div>
                        <div class="text-base font-bold text-gray-900">Conversion Funnel</div>
                        <p class="text-xs text-gray-400 mt-0.5">Potential → In Progress → Completed</p>
                    </div>
                    <span class="text-xs font-bold rounded-full px-3 py-1 bg-[#ECFDF5] text-[#047857] shrink-0">{{ $conversionPct }}% converted</span>
                </div>
                @php
                    $stages = [
                        ['l' => 'Potential', 'icon' => 'target', 'n' => $stats['potential_count'], 'v' => $stats['potential_value'], 'c' => '#6366F1', 'bg' => '#F1F1FF', 'ink' => '#4338CA'],
                        ['l' => 'In Progress', 'icon' => 'zap', 'n' => $stats['in_progress_count'], 'v' => $stats['in_progress_value'], 'c' => '#3A86FF', 'bg' => '#EEF5FF', 'ink' => '#1D4ED8'],
                        ['l' => 'Completed', 'icon' => 'circle-check', 'n' => $stats['completed_count'], 'v' => $stats['actual_revenue'], 'c' => '#10B981', 'bg' => '#ECFDF5', 'ink' => '#047857', 'last' => true],
                    ];
                @endphp
                <div class="flex items-stretch gap-2">
                    @foreach ($stages as $s)
                        <div class="flex-1 rounded-2xl p-3.5 text-center flex flex-col items-center justify-center min-h-[120px]" style="background:{{ $s['bg'] }}">
                            <x-icon :name="$s['icon']" class="w-5 h-5" style="color:{{ $s['c'] }}" />
                            <div class="text-[11px] font-bold uppercase tracking-wide mt-1" style="color:{{ $s['ink'] }}">{{ $s['l'] }}</div>
                            <div class="text-2xl font-extrabold text-gray-900 mt-0.5">{{ $s['n'] }}</div>
                            <div class="text-[11px] text-gray-500">RM {{ number_format($s['v'], 2) }}</div>
                        </div>
                        @if (empty($s['last']))
                            <x-icon name="arrow-right" class="self-center w-4 h-4 text-gray-300 shrink-0" />
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Row 4: Recent Jobs --}}
        <div class="k-card p-5 md:p-6">
            <div class="flex items-center justify-between mb-3">
                <div class="text-base font-bold text-gray-900">Recent Jobs</div>
                <a href="{{ route('jobs.index') }}" class="inline-flex items-center gap-1 text-xs font-semibold text-[#C2185B] hover:underline">View all <x-icon name="arrow-right" class="w-3.5 h-3.5" /></a>
            </div>
            @if ($recent->isEmpty())
                <div class="py-6 text-center text-gray-400 text-sm">No jobs yet. New jobs will show up here.</div>
            @else
                <div class="divide-y divide-[#F5ECE8]">
                    @foreach ($recent as $job)
                        @php $st = config('kretivco.job_statuses.'.$job->status); $dept = config('kretivco.departments.'.$job->department); @endphp
                        <a href="{{ route('jobs.show', $job) }}" class="flex items-center gap-3 py-3 px-3 -mx-3 rounded-xl hover:bg-[#FFF7F3] transition-colors">
                            <span class="w-9 h-9 rounded-xl flex items-center justify-center text-xs font-bold shrink-0"
                                  style="color:{{ $dept['color'] ?? '#6B7280' }};background:{{ $dept['color'] ?? '#6B7280' }}14">{{ strtoupper(substr($job->customer?->name ?? '?', 0, 1)) }}</span>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-semibold text-gray-800 truncate">{{ $job->customer?->name ?? 'No customer' }}{{ $job->customer?->company ? ' · '.$job->customer->company : '' }}</div>
                                <div class="font-mono text-[11px] text-gray-400">{{ $job->job_id }} · {{ $job->created_at->format('d M Y') }}</div>
                            </div>
                            @if ($dept)
                                <span class="hidden sm:inline text-[11px] font-semibold rounded-full px-2.5 py-0.5" style="color:{{ $dept['color'] }};background:{{ $dept['color'] }}14">{{ $dept['label'] }}</span>
                            @endif
                            @if ($st)
                                <span class="hidden sm:inline text-xs font-semibold rounded-full px-3 py-1" style="color:{{ $st['color'] }};background:{{ $st['color'] }}14">{{ $st['label'] }}</span>
                            @endif
                            <span class="font-bold text-sm text-right text-gray-900 whitespace-nowrap">RM {{ number_format($job->estimation_value ?? 0, 2) }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

    </div>
</x-app-layout>
