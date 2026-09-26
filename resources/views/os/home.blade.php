<x-os-layout>
    @php
        $user = auth()->user();
        $hour = (int) now()->format('G');
        $greeting = $hour < 12 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat tengah hari' : ($hour < 19 ? 'Selamat petang' : 'Selamat malam'));
        $first = \Illuminate\Support\Str::of($user->name)->before(' ');
    @endphp

    <div class="space-y-4">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 items-stretch">
            {{-- Greeting + attendance (Clock In/Out, WFH/WFO) — both are visual
                 placeholders for now; they need a real attendance_logs table
                 and backend before they do anything. --}}
            <div class="os-card rounded-2xl p-5 flex flex-col">
                <div class="flex items-start justify-between gap-3"
                     x-data="{ time: '', date: '' }"
                     x-init="
                        const tick = () => {
                            const now = new Date();
                            time = now.toLocaleTimeString('en-GB', { hour12: false });
                            date = now.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
                        };
                        tick();
                        setInterval(tick, 1000);
                     ">
                    <h1 class="text-2xl font-semibold text-white">{{ $greeting }}, {{ $first }}</h1>
                    <div class="text-right shrink-0">
                        <div class="font-mono text-2xl font-semibold text-[#FCB03C] tracking-wide" x-text="time"></div>
                        <div class="text-xs text-white/50 mt-1" x-text="date"></div>
                    </div>
                </div>

                <div class="mt-5 pt-4 border-t border-white/10">
                    <p class="text-[11px] uppercase tracking-wide text-white/30 mb-2">Attendance · Coming soon</p>
                    <div class="grid grid-cols-2 gap-1.5 p-1 rounded-lg bg-white/5 text-xs font-semibold text-white/40 mb-2">
                        <span class="text-center py-1.5 rounded-md">Work From Office</span>
                        <span class="text-center py-1.5 rounded-md">Work From Home</span>
                    </div>
                    <button type="button" disabled class="w-full text-xs font-semibold py-2 rounded-md bg-white/5 text-white/30 cursor-not-allowed">
                        Clock In
                    </button>
                </div>
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
                    <p class="text-xs text-white/50 mt-1">{{ $user->canAccess('jobs') ? 'Projects & Clients' : 'No access — ask BOD' }}</p>
                </div>

                <div class="os-card rounded-2xl p-5 pt-0 flex flex-col items-center text-center overflow-hidden opacity-50">
                    <div class="-mx-5 mb-3 aspect-square w-[calc(100%+2.5rem)] overflow-hidden">
                        <img src="{{ asset('images/os-cards/hr.jpg') }}" alt="" class="w-full h-full object-cover">
                    </div>
                    <h2 class="font-semibold text-white">HR</h2>
                    <p class="text-xs text-white/50 mt-1">People & Culture</p>
                    <span class="mt-2 text-[10px] font-semibold px-2 py-0.5 rounded bg-white/10 text-white/40">Coming soon</span>
                </div>

                <div class="os-card rounded-2xl p-5 pt-0 flex flex-col items-center text-center overflow-hidden {{ $user->canAccess('finance') ? 'os-card--clickable' : 'opacity-50' }}">
                    @if ($user->canAccess('finance'))
                        <a href="{{ route('finance.index') }}" target="_blank" rel="noopener" class="os-card-link" aria-label="Open Finance"></a>
                    @endif
                    <div class="-mx-5 mb-3 aspect-square w-[calc(100%+2.5rem)] overflow-hidden">
                        <img src="{{ asset('images/os-cards/finance.jpg') }}" alt="" class="w-full h-full object-cover">
                    </div>
                    <h2 class="font-semibold text-white">Finance</h2>
                    <p class="text-xs text-white/50 mt-1">{{ $user->canAccess('finance') ? 'Numbers & Reports' : 'No access — ask BOD' }}</p>
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
                        <span>⚠️</span>
                        <span class="text-red-300"><strong class="text-white">{{ $job->job_id }}</strong> {{ $job->job_type }} — {{ $job->deadline->startOfDay()->eq($today) ? 'deadline today' : 'overdue since '.$job->deadline->format('j M') }}</span>
                        <span class="ml-auto text-xs underline text-white/50">Open</span>
                    </a>
                @empty
                @endforelse

                @if ($queueCount > 0)
                    <a href="{{ route('jobs.index', ['view' => 'queue']) }}" target="_blank" rel="noopener" class="flex items-center gap-3 text-sm px-3 py-2.5 rounded-lg bg-amber-500/10 hover:bg-amber-500/15">
                        <span>📥</span><span class="text-amber-300">{{ $queueCount }} {{ \Illuminate\Support\Str::plural('job', $queueCount) }} in the queue waiting to be taken in</span>
                        <span class="ml-auto text-xs underline text-white/50">View queue</span>
                    </a>
                @endif

                @if ($dueJobs->isEmpty() && $queueCount === 0)
                    <p class="text-sm text-white/30 px-3 py-2">Nothing needs your attention right now.</p>
                @endif

                <div class="flex items-center gap-3 text-sm px-3 py-2.5 rounded-lg bg-white/[0.03] text-white/30">
                    <span>📝</span><span>No memos from HR yet</span>
                    <span class="ml-auto text-[10px] font-semibold px-2 py-0.5 rounded bg-white/10">Coming soon</span>
                </div>
                <div class="flex items-center gap-3 text-sm px-3 py-2.5 rounded-lg bg-white/[0.03] text-white/30">
                    <span>📣</span><span>No announcements yet</span>
                    <span class="ml-auto text-[10px] font-semibold px-2 py-0.5 rounded bg-white/10">Coming soon</span>
                </div>
            </div>
        </div>
    </div>
</x-os-layout>
