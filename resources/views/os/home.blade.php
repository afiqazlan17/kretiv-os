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
                <div class="relative" x-data="{ time: '', date: '' }"
                     x-init="
                        const tick = () => {
                            const now = new Date();
                            time = now.toLocaleTimeString('en-GB', { hour12: false });
                            date = now.toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
                        };
                        tick();
                        setInterval(tick, 1000);
                     ">
                    {{-- Radar (BOD only, and only here): a sonar sweep with a blip for each item that needs
                         attention (not taken, overdue, or a reminder not yet seen). Tap to check or add. --}}
                    @if ($radar !== null)
                        @php
                            $blips = [[40, .62], [130, .74], [215, .5], [300, .7], [80, .4], [255, .8]];
                            $orbSweep = 4; // seconds per turn
                            $radarUrgent = $radar->contains(fn ($i) => $i->daysLeft() !== null && $i->daysLeft() <= 3);
                        @endphp
                        <div class="absolute right-0 top-1/2 -translate-y-1/2 z-20" x-data="{ open: false, count: {{ $radar->count() }} }" @click.outside="open = false" @radar-saved="count = $event.detail.count ?? count">
                            <button type="button" @click="open = !open" aria-label="Radar" class="radar-orb {{ $radarUrgent ? 'radar-orb--urgent' : '' }}">
                                <span class="radar-rings"></span>
                                <span class="radar-sweep" style="animation-duration: {{ $orbSweep }}s"></span>
                                <template x-for="(b, i) in {{ json_encode($blips) }}.slice(0, Math.min(count, 6))" :key="i">
                                    <span class="radar-blip" :style="`left: calc(50% + ${Math.sin(b[0] * Math.PI / 180) * b[1] * 38}px); top: calc(50% - ${Math.cos(b[0] * Math.PI / 180) * b[1] * 38}px); animation-duration: {{ $orbSweep }}s; animation-delay: ${b[0] / 360 * {{ $orbSweep }}}s`"></span>
                                </template>
                                <span class="radar-word">Radar</span>
                            </button>
                            <span x-show="count > 0" x-text="count" class="absolute -top-1.5 -right-1.5 z-10 min-w-[22px] h-[22px] px-1.5 rounded-full ring-[3px] ring-[#1c0f12] shadow-md {{ $radarUrgent ? 'bg-red-500 text-white' : 'bg-[#FCB03C] text-gray-900' }} text-[11px] font-bold flex items-center justify-center pointer-events-none"></span>
                            <div x-show="open" x-cloak x-transition class="absolute right-0 top-full mt-2 w-[min(21rem,calc(100vw-2.5rem))] rounded-2xl bg-white text-gray-800 p-3 shadow-xl">
                                @if ($radar->isNotEmpty())
                                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-1.5">Needs attention</p>
                                    <div class="space-y-1 mb-3">
                                        @foreach ($radar->sortBy(fn ($i) => $i->daysLeft() ?? 9999)->take(4) as $item)
                                            <a href="{{ route('radar.index', ['tab' => $item->status]) }}" class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs hover:bg-[#FFF5F1]">
                                                <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ ($item->daysLeft() ?? 99) <= 3 ? 'bg-red-500' : 'bg-[#FCB03C]' }}"></span>
                                                <span class="truncate flex-1 text-gray-800">{{ $item->headline(48) }}</span>
                                                <span class="shrink-0 text-gray-400">{{ $item->dueLabel() ?? 'Not taken' }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                                <a href="{{ route('radar.index') }}" class="flex items-center justify-center gap-1.5 w-full mb-3 text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110"><x-icon name="radar" class="w-4 h-4" /> Open Radar</a>
                                <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-1.5">Quick add</p>
                                @include('radar.partials.compose', ['action' => route('radar.store'), 'dark' => true])
                            </div>
                        </div>
                        <style>
                            .radar-orb { position: relative; display: block; width: 80px; height: 80px; border-radius: 9999px; overflow: hidden;
                                background: radial-gradient(circle, rgba(255, 92, 138, .14) 0%, rgba(20, 8, 12, .85) 70%); border: 1px solid rgba(255, 138, 91, .45);
                                box-shadow: 0 0 22px -6px rgba(255, 92, 138, .7), inset 0 0 14px rgba(255, 92, 138, .18); }
                            .radar-rings { position: absolute; inset: 0; border-radius: 9999px;
                                background: repeating-radial-gradient(circle, transparent 0 12px, rgba(255, 170, 140, .16) 12px 13px),
                                    linear-gradient(transparent calc(50% - .5px), rgba(255, 170, 140, .16) calc(50% - .5px) calc(50% + .5px), transparent calc(50% + .5px)),
                                    linear-gradient(90deg, transparent calc(50% - .5px), rgba(255, 170, 140, .16) calc(50% - .5px) calc(50% + .5px), transparent calc(50% + .5px)); }
                            .radar-sweep { position: absolute; inset: 0; border-radius: 9999px; animation: radar-spin linear infinite;
                                background: conic-gradient(from 0deg, transparent 0deg 270deg, rgba(255, 92, 138, .05) 290deg, rgba(244, 106, 58, .35) 345deg, rgba(252, 176, 60, .75) 360deg); }
                            .radar-blip { position: absolute; width: 6px; height: 6px; margin: -3px 0 0 -3px; border-radius: 9999px; background: #FCB03C;
                                box-shadow: 0 0 6px 1px rgba(252, 176, 60, .9); opacity: .25; animation: radar-blip linear infinite; }
                            .radar-word { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 15px; font-weight: 800; letter-spacing: -.01em;
                                background: linear-gradient(90deg, #FF7AA2, #FF8A5B, #FFC65C); -webkit-background-clip: text; background-clip: text; color: transparent;
                                filter: drop-shadow(0 0 3px rgba(255, 122, 162, .95)) drop-shadow(0 0 9px rgba(244, 106, 58, .7)); }
                            .radar-orb--urgent .radar-blip { background: #FF4D6D; box-shadow: 0 0 7px 1px rgba(255, 77, 109, .95); }
                            .radar-orb--urgent { border-color: rgba(255, 77, 109, .6); }
                            @keyframes radar-spin { to { transform: rotate(360deg); } }
                            @keyframes radar-blip { 0% { opacity: 1; transform: scale(1.4); } 35% { opacity: .55; transform: scale(1); } 100% { opacity: .25; transform: scale(1); } }
                            @media (prefers-reduced-motion: reduce) { .radar-sweep, .radar-blip { animation: none; } .radar-blip { opacity: 1; } }
                        </style>
                    @endif
                    <h1 class="text-2xl font-semibold text-white leading-snug {{ $radar !== null ? 'pr-24' : '' }}">{{ $greeting['title'] }}</h1>
                    <p class="text-sm italic text-white/55 mt-0.5 {{ $radar !== null ? 'pr-24' : '' }}">{{ $greeting['line'] }}</p>
                    <div class="mt-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-mono text-3xl font-semibold text-[#FCB03C] tracking-wide" x-text="time"></div>
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
