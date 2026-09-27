<x-os-layout>
    @php
        $user = auth()->user();
        $hour = (int) now()->format('G');
        $greeting = $hour < 12 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat tengah hari' : ($hour < 19 ? 'Selamat petang' : 'Selamat malam'));
        $first = \Illuminate\Support\Str::of($user->name)->before(' ');
    @endphp

    <div class="space-y-4">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 items-stretch">
            {{-- Greeting + attendance. Staff only see their own times; lateness is
                 for HR, Dept Heads and BOD (HR > Team attendance). --}}
            <div class="os-card rounded-2xl p-5 flex flex-col">
                <div x-data="{ time: '', date: '' }"
                     x-init="
                        const tick = () => {
                            const now = new Date();
                            time = now.toLocaleTimeString('en-GB', { hour12: false });
                            date = now.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
                        };
                        tick();
                        setInterval(tick, 1000);
                     ">
                    <h1 class="text-2xl font-semibold text-white leading-snug">{{ $greeting }}, {{ $first }}</h1>
                    <div class="mt-4">
                        <div class="font-mono text-3xl font-semibold text-[#FCB03C] tracking-wide" x-text="time"></div>
                        <div class="text-xs text-white/50 mt-1" x-text="date"></div>
                    </div>
                </div>

                <div class="mt-5 pt-4 border-t border-white/10">
                    <p class="text-[11px] uppercase tracking-wide text-white/30 mb-2">Attendance</p>
                    @if (session('success') && str_starts_with(session('success'), 'Clocked'))
                        <p class="text-xs text-emerald-300 mb-2">{{ session('success') }}</p>
                    @endif
                    @if (! $attendance)
                        <form method="POST" action="{{ route('os.clock-in') }}" x-data="{ mode: 'wfo' }">
                            @csrf
                            <input type="hidden" name="work_mode" :value="mode">
                            <div class="grid grid-cols-2 gap-1.5 p-1 rounded-lg bg-white/5 text-xs font-semibold mb-2">
                                <button type="button" @click="mode = 'wfo'" :class="mode === 'wfo' ? 'bg-white/15 text-white' : 'text-white/40'" class="py-1.5 rounded-md">Work From Office</button>
                                <button type="button" @click="mode = 'wfh'" :class="mode === 'wfh' ? 'bg-white/15 text-white' : 'text-white/40'" class="py-1.5 rounded-md">Work From Home</button>
                            </div>
                            <button type="submit" class="w-full text-xs font-semibold py-2 rounded-md text-white" style="background:linear-gradient(135deg,#E91E63,#F46A3A)">Clock In</button>
                        </form>
                    @elseif (! $attendance->clock_out)
                        <div class="flex items-center justify-between text-xs text-white/60 mb-2">
                            <span>In at <span class="text-white font-semibold">{{ $attendance->clock_in->format('g:i a') }}</span> · {{ $attendance->work_mode === 'wfh' ? 'WFH' : 'Office' }}</span>
                            <span>Day ends {{ $attendance->expectedEnd()->format('g:i a') }}</span>
                        </div>
                        <form method="POST" action="{{ route('os.clock-out') }}">
                            @csrf
                            <button type="submit" class="w-full text-xs font-semibold py-2 rounded-md bg-white/10 text-white hover:bg-white/15">Clock Out</button>
                        </form>
                    @else
                        <p class="text-xs text-white/60">{{ $attendance->clock_in->format('g:i a') }} to {{ $attendance->clock_out->format('g:i a') }} · {{ $attendance->work_mode === 'wfh' ? 'WFH' : 'Office' }}</p>
                        @if ($attendance->ot_minutes > 0)
                            <p class="text-xs text-white/40 mt-1">Overtime {{ $attendance->otHours() }} h sent for approval.</p>
                        @endif
                        <p class="text-xs text-white/40 mt-1">Done for today.</p>
                    @endif
                </div>

                {{-- Quick links out: the public site and the company webmail (cPanel). --}}
                <a href="https://kretiv.co" target="_blank" rel="noopener" class="mt-4 flex items-center gap-3 rounded-xl bg-white/5 border border-white/10 px-4 py-3.5 hover:border-white/25 transition-colors">
                    <span class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 text-white" style="background:linear-gradient(135deg,#E91E63,#F46A3A)"><x-icon name="globe" class="w-5 h-5" /></span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-white">Visit our website</p>
                        <p class="text-xs text-white/40 truncate">kretiv.co</p>
                    </div>
                    <x-icon name="arrow-right" class="w-4 h-4 text-white/40 shrink-0" />
                </a>
                <a href="https://sc171.mschosting.cloud:2096/" target="_blank" rel="noopener" class="mt-2 flex items-center gap-3 rounded-xl bg-white/5 border border-white/10 px-4 py-3.5 hover:border-white/25 transition-colors">
                    <span class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 text-white" style="background:linear-gradient(135deg,#3A86FF,#6FB7FF)"><x-icon name="mail" class="w-5 h-5" /></span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-white">Open Your Email</p>
                        <p class="text-xs text-white/40 truncate">Kretivco webmail</p>
                    </div>
                    <x-icon name="arrow-right" class="w-4 h-4 text-white/40 shrink-0" />
                </a>
            </div>

            {{-- Module launcher tiles — simple/uniform, the whole tile is
                 clickable (.os-card-link). Details (job lists, alerts) live in
                 the Notifications panel below instead of cluttering these. --}}
            <div class="lg:col-span-3 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="os-card rounded-2xl p-5 pt-0 flex flex-col items-center text-center overflow-hidden {{ $user->canAccess('jobs') ? 'os-card--clickable' : 'opacity-50' }}">
                    @if ($user->canAccess('jobs'))
                        <a href="{{ route('dashboard') }}" target="_blank" rel="noopener" class="os-card-link" aria-label="Open Jobs"></a>
                    @endif
                    <div class="-mx-5 mb-3 aspect-square w-[calc(100%+2.5rem)] overflow-hidden">
                        <img src="{{ asset('images/os-cards/jobs.jpg') }}" alt="" class="w-full h-full object-cover">
                    </div>
                    <h2 class="font-semibold text-white">Jobs</h2>
                    <p class="text-xs text-white/50 mt-1">{{ $user->canAccess('jobs') ? 'Projects & Clients' : 'No access, ask BOD' }}</p>
                </div>

                <div class="os-card rounded-2xl p-5 pt-0 flex flex-col items-center text-center overflow-hidden {{ $user->canAccess('hr') ? 'os-card--clickable' : 'opacity-50' }}">
                    @if ($user->canAccess('hr'))
                        <a href="{{ route('hr.home') }}" target="_blank" rel="noopener" class="os-card-link" aria-label="Open HR"></a>
                    @endif
                    <div class="-mx-5 mb-3 aspect-square w-[calc(100%+2.5rem)] overflow-hidden">
                        <img src="{{ asset('images/os-cards/hr.jpg') }}" alt="" class="w-full h-full object-cover">
                    </div>
                    <h2 class="font-semibold text-white">HR</h2>
                    <p class="text-xs text-white/50 mt-1">{{ $user->canAccess('hr') ? 'People & Culture' : 'No access, ask BOD' }}</p>
                </div>

                <div class="os-card rounded-2xl p-5 pt-0 flex flex-col items-center text-center overflow-hidden {{ $user->canAccess('finance') ? 'os-card--clickable' : 'opacity-50' }}">
                    @if ($user->canAccess('finance'))
                        <a href="{{ route('finance.index') }}" target="_blank" rel="noopener" class="os-card-link" aria-label="Open Finance"></a>
                    @endif
                    <div class="-mx-5 mb-3 aspect-square w-[calc(100%+2.5rem)] overflow-hidden">
                        <img src="{{ asset('images/os-cards/finance.jpg') }}" alt="" class="w-full h-full object-cover">
                    </div>
                    <h2 class="font-semibold text-white">Finance</h2>
                    <p class="text-xs text-white/50 mt-1">{{ $user->canAccess('finance') ? 'Numbers & Reports' : 'No access, ask BOD' }}</p>
                </div>
            </div>
        </div>

        {{-- Notifications — one merged feed. Job alerts are real (deadline /
             queue data already computed in OsController); Memo and
             Announcement are placeholders until an HR/announcements module
             actually exists. --}}
        <div class="os-card rounded-2xl p-5">
            <h2 class="font-semibold text-white mb-3">Notifications</h2>

            <div class="space-y-2">
                @forelse ($dueJobs as $job)
                    <a href="{{ route('jobs.show', $job) }}" target="_blank" rel="noopener" class="flex items-center gap-3 text-sm px-3 py-2.5 rounded-lg bg-red-500/10 hover:bg-red-500/15">
                        <x-icon name="triangle-alert" class="w-4 h-4 text-red-300 shrink-0" />
                        <span class="text-red-300"><strong class="text-white">{{ $job->job_id }}</strong> {{ $job->job_type }}: {{ $job->deadline->startOfDay()->eq($today) ? 'deadline today' : 'overdue since '.$job->deadline->format('j M') }}</span>
                        <span class="ml-auto text-xs underline text-white/50">Open</span>
                    </a>
                @empty
                @endforelse

                @if ($queueCount > 0)
                    <a href="{{ route('jobs.index', ['view' => 'queue']) }}" target="_blank" rel="noopener" class="flex items-center gap-3 text-sm px-3 py-2.5 rounded-lg bg-amber-500/10 hover:bg-amber-500/15">
                        <x-icon name="inbox" class="w-4 h-4 text-amber-300 shrink-0" /><span class="text-amber-300">{{ $queueCount }} {{ \Illuminate\Support\Str::plural('job', $queueCount) }} in the queue waiting to be taken in</span>
                        <span class="ml-auto text-xs underline text-white/50">View queue</span>
                    </a>
                @endif

                @if ($dueJobs->isEmpty() && $queueCount === 0)
                    <p class="text-sm text-white/30 px-3 py-2">Nothing needs your attention right now.</p>
                @endif

                <div class="flex items-center gap-3 text-sm px-3 py-2.5 rounded-lg bg-white/[0.03] text-white/30">
                    <x-icon name="notebook-pen" class="w-4 h-4 shrink-0" /><span>No memos from HR yet</span>
                    <span class="ml-auto text-[10px] font-semibold px-2 py-0.5 rounded bg-white/10">Coming soon</span>
                </div>
                <div class="flex items-center gap-3 text-sm px-3 py-2.5 rounded-lg bg-white/[0.03] text-white/30">
                    <x-icon name="megaphone" class="w-4 h-4 shrink-0" /><span>No announcements yet</span>
                    <span class="ml-auto text-[10px] font-semibold px-2 py-0.5 rounded bg-white/10">Coming soon</span>
                </div>
            </div>
        </div>
    </div>
</x-os-layout>
