<x-os-layout>
    @php
        $user = auth()->user();
        $greeting = \App\Support\Greeting::for($user, now());
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
                    <h1 class="text-2xl font-semibold text-white leading-snug">{{ $greeting['title'] }}</h1>
                    <p class="text-sm italic text-white/55 mt-0.5">{{ $greeting['line'] }}</p>
                    <div class="mt-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-mono text-3xl font-semibold text-[#FCB03C] tracking-wide" x-text="time"></div>
                            {{-- Rizq (BOD only): glowing circle beside the clock. Tap to jot down a lead; the dot counts notes nobody has taken. --}}
                            @if ($rizqOpen !== null)
                                <div class="relative shrink-0" x-data="{ open: false, count: {{ $rizqOpen }} }" @click.outside="open = false" @rizq-saved="count = $event.detail.open ?? count">
                                    <button type="button" @click="open = !open" aria-label="Rizq: write down a lead"
                                            class="rizq-orb relative w-14 h-14 rounded-full flex items-center justify-center border border-white/20 bg-white/5 hover:bg-white/10 transition-colors">
                                        <span class="rizq-word text-base font-extrabold tracking-tight">Rizq</span>
                                        <span x-show="count > 0" x-text="count" class="absolute -top-1 -right-1 min-w-[20px] h-5 px-1 rounded-full bg-[#FCB03C] text-[11px] font-bold text-gray-900 flex items-center justify-center"></span>
                                    </button>
                                    <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-[min(20rem,calc(100vw-2.5rem))] rounded-2xl bg-white text-gray-800 p-3 shadow-xl z-30">
                                        <div class="flex items-center justify-between mb-2">
                                            <p class="text-sm font-bold text-gray-900">New lead for Rizq</p>
                                            <a href="{{ route('rizq.index') }}" class="text-xs font-semibold text-[#C2185B] hover:underline">Open Rizq</a>
                                        </div>
                                        @include('rizq.partials.compose', ['action' => route('os.rizq.store'), 'dark' => true])
                                    </div>
                                </div>
                                <style>
                                    .rizq-orb { box-shadow: 0 0 18px -4px rgba(255, 92, 138, .55), inset 0 0 12px rgba(252, 176, 60, .12); animation: rizq-pulse 3.2s ease-in-out infinite; }
                                    .rizq-word { background: linear-gradient(90deg, #FF7AA2, #FF8A5B, #FFC65C); -webkit-background-clip: text; background-clip: text; color: transparent; filter: drop-shadow(0 0 3px rgba(255, 122, 162, .95)) drop-shadow(0 0 10px rgba(244, 106, 58, .75)); animation: rizq-text 3.2s ease-in-out infinite; }
                                    @keyframes rizq-text { 0%, 100% { filter: drop-shadow(0 0 2px rgba(255, 122, 162, .8)) drop-shadow(0 0 6px rgba(244, 106, 58, .5)); } 50% { filter: drop-shadow(0 0 4px rgba(255, 122, 162, 1)) drop-shadow(0 0 14px rgba(252, 176, 60, .85)); } }
                                    @keyframes rizq-pulse { 0%, 100% { box-shadow: 0 0 14px -4px rgba(255, 92, 138, .45), inset 0 0 10px rgba(252, 176, 60, .1); } 50% { box-shadow: 0 0 26px -2px rgba(255, 92, 138, .8), inset 0 0 14px rgba(252, 176, 60, .2); } }
                                    @media (prefers-reduced-motion: reduce) { .rizq-orb, .rizq-word { animation: none; } }
                                </style>
                            @endif
                        </div>
                        <div class="text-xs text-white/50 mt-1" x-text="date"></div>
                    </div>
                </div>

                <div class="mt-5 pt-4 border-t border-white/10">
                    <p class="text-[11px] uppercase tracking-wide text-white/30 mb-2">Attendance</p>
                    @if (session('success') && str_starts_with(session('success'), 'Clock'))
                        <p class="text-xs text-emerald-300 mb-2">{{ session('success') }}</p>
                    @endif
                    @if (! $attendance && $onLeave)
                        <p class="text-xs text-white/60 mb-2">You're on {{ strtolower($onLeave->typeLabel()) }} today{{ $onLeave->half_day ? ' ('.($onLeave->half_day === 'am' ? 'morning' : 'afternoon').')' : '' }}. Enjoy.</p>
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
                        @php $endsAt = $attendance->expectedEnd(); @endphp
                        <form method="POST" action="{{ route('os.clock-out') }}" @if ($attendance->day_type === 'normal') onsubmit="return new Date() >= new Date('{{ $endsAt->toIso8601String() }}') || confirm('Your day ends at {{ $endsAt->format('g:i a') }}. Clock out now?')" @endif>
                            @csrf
                            <button type="submit" class="w-full text-xs font-semibold py-2 rounded-md bg-white/10 text-white hover:bg-white/15">Clock Out</button>
                        </form>
                    @else
                        <p class="text-xs text-white/60">{{ $attendance->clock_in->format('g:i a') }} to {{ $attendance->clock_out->format('g:i a') }} · {{ $attendance->work_mode === 'wfh' ? 'WFH' : 'Office' }}</p>
                        @if ($attendance->ot_minutes > 0)
                            <p class="text-xs text-white/40 mt-1">Overtime {{ $attendance->otHours() }} h sent for approval.</p>
                        @endif
                        @if (\App\Services\AttendanceService::canUndo($attendance))
                            <form method="POST" action="{{ route('os.clock-out.undo') }}" class="mt-2">
                                @csrf
                                <button class="text-xs font-semibold text-[#FCB03C] hover:text-white">Clocked out by mistake? Undo</button>
                            </form>
                        @else
                            <p class="text-xs text-white/40 mt-1">Done for today.</p>
                        @endif
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
                @unless (config('demo.enabled'))
                <a href="https://sc171.mschosting.cloud:2096/" target="_blank" rel="noopener" class="mt-2 flex items-center gap-3 rounded-xl bg-white/5 border border-white/10 px-4 py-3.5 hover:border-white/25 transition-colors">
                    <span class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 text-white" style="background:linear-gradient(135deg,#3A86FF,#6FB7FF)"><x-icon name="mail" class="w-5 h-5" /></span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-white">Open Your Email</p>
                        <p class="text-xs text-white/40 truncate">Kretivco webmail</p>
                    </div>
                    <x-icon name="arrow-right" class="w-4 h-4 text-white/40 shrink-0" />
                </a>
                @endunless
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
                        <img src="{{ asset('images/os-cards/jobs.jpg').'?v=2' }}" alt="" class="w-full h-full object-cover">
                    </div>
                    <h2 class="font-semibold text-white">Jobs</h2>
                    <p class="text-xs text-white/50 mt-1">{{ $user->canAccess('jobs') ? 'Projects & Clients' : 'No access, ask BOD' }}</p>
                </div>

                <div class="os-card rounded-2xl p-5 pt-0 flex flex-col items-center text-center overflow-hidden {{ $user->canAccess('hr') ? 'os-card--clickable' : 'opacity-50' }}">
                    @if ($user->canAccess('hr'))
                        <a href="{{ route('hr.home') }}" target="_blank" rel="noopener" class="os-card-link" aria-label="Open HR"></a>
                    @endif
                    <div class="-mx-5 mb-3 aspect-square w-[calc(100%+2.5rem)] overflow-hidden">
                        <img src="{{ asset('images/os-cards/hr.jpg').'?v=2' }}" alt="" class="w-full h-full object-cover">
                    </div>
                    <h2 class="font-semibold text-white">HR</h2>
                    <p class="text-xs text-white/50 mt-1">{{ $user->canAccess('hr') ? 'People & Culture' : 'No access, ask BOD' }}</p>
                </div>

                <div class="os-card rounded-2xl p-5 pt-0 flex flex-col items-center text-center overflow-hidden {{ $user->canAccess('finance') ? 'os-card--clickable' : 'opacity-50' }}">
                    @if ($user->canAccess('finance'))
                        <a href="{{ route('finance.index') }}" target="_blank" rel="noopener" class="os-card-link" aria-label="Open Finance"></a>
                    @endif
                    <div class="-mx-5 mb-3 aspect-square w-[calc(100%+2.5rem)] overflow-hidden">
                        <img src="{{ asset('images/os-cards/finance.jpg').'?v=2' }}" alt="" class="w-full h-full object-cover">
                    </div>
                    <h2 class="font-semibold text-white">Finance</h2>
                    <p class="text-xs text-white/50 mt-1">{{ $user->canAccess('finance') ? 'Numbers & Reports' : 'No access, ask BOD' }}</p>
                </div>
            </div>
        </div>

        {{-- Notifications: everything waiting on this person across Jobs,
             Finance and HR (App\Services\NotificationCenter). --}}
        @php $inbox = \App\Services\NotificationCenter::for($user); @endphp
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="os-card rounded-2xl p-5">
                <div class="flex items-center gap-2 mb-3">
                    <h2 class="font-semibold text-white">Needs your action</h2>
                    @if ($inbox['actions']->isNotEmpty())<span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-[#E91E63] text-white">{{ $inbox['actions']->count() }}</span>@endif
                </div>
                <x-notification-list :inbox="$inbox" dark :limit="20" only="actions" :headings="false" />
            </div>
            <div class="os-card rounded-2xl p-5">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <h2 class="font-semibold text-white">Announcements &amp; updates</h2>
                    @if ($inbox['updates']->isNotEmpty())
                        <form method="POST" action="{{ route('notifications.read-all') }}">@csrf
                            <button class="text-xs font-semibold text-white/50 hover:text-white">Mark all as read</button>
                        </form>
                    @endif
                </div>
                <x-notification-list :inbox="$inbox" dark :limit="20" only="updates" :headings="false" />
                @if ($user->canAccess('hr'))
                    <a href="{{ route('hr.announcements') }}" target="_blank" rel="noopener" class="inline-block mt-3 text-xs underline text-white/40 hover:text-white">All announcements</a>
                @endif
            </div>
        </div>
    </div>
</x-os-layout>
