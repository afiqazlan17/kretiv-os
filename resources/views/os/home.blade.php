<x-os-layout>
    @php
        $user = auth()->user();
        $hour = (int) now()->format('G');
        $greeting = $hour < 12 ? 'Selamat pagi' : ($hour < 15 ? 'Selamat tengah hari' : ($hour < 19 ? 'Selamat petang' : 'Selamat malam'));
        $first = \Illuminate\Support\Str::of($user->name)->before(' ');
    @endphp

    <div class="space-y-4">
        <div class="bg-white rounded-xl shadow-sm p-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold">{{ $greeting }}, {{ $first }}</h1>
                <p class="text-sm text-gray-500">{{ now()->translatedFormat('l, j F Y') }}</p>
            </div>
        </div>

        @if ($dueJobs->isNotEmpty() || $queueCount > 0)
            <div class="space-y-2">
                @foreach ($dueJobs as $job)
                    <a href="{{ route('jobs.show', $job) }}" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-lg bg-red-50 text-red-700 text-sm px-4 py-2.5 hover:bg-red-100">
                        <span>⚠️</span>
                        <span><strong>{{ $job->job_id }}</strong> {{ $job->job_type }} — {{ $job->deadline->startOfDay()->eq($today) ? 'deadline today' : 'overdue since '.$job->deadline->format('j M') }}</span>
                        <span class="ml-auto text-xs underline">Open</span>
                    </a>
                @endforeach
                @if ($queueCount > 0)
                    <a href="{{ route('jobs.index', ['view' => 'queue']) }}" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-lg bg-amber-50 text-amber-800 text-sm px-4 py-2.5 hover:bg-amber-100">
                        <span>📥</span><span>{{ $queueCount }} {{ \Illuminate\Support\Str::plural('job', $queueCount) }} in the queue waiting to be taken in</span>
                        <span class="ml-auto text-xs underline">View queue</span>
                    </a>
                @endif
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
            {{-- Jobs --}}
            <div class="bg-white rounded-xl shadow-sm p-5 flex flex-col {{ $user->canAccess('jobs') ? '' : 'opacity-50' }}">
                <div class="flex items-center gap-2 mb-1"><span class="text-xl">📋</span><h2 class="font-semibold">Jobs</h2></div>
                @if ($user->canAccess('jobs'))
                    <p class="text-xs text-gray-500">{{ $user->isBod() ? 'Your jobs in progress' : 'My jobs in progress' }}</p>
                    <p class="text-2xl font-semibold mt-1">{{ $myJobs->count() }}</p>
                    <div class="mt-3 flex-1 divide-y divide-gray-100 text-sm">
                        @forelse ($myJobs as $job)
                            @php $days = $job->deadline ? (int) $today->diffInDays($job->deadline->startOfDay(), false) : null; @endphp
                            <a href="{{ route('jobs.show', $job) }}" target="_blank" rel="noopener" class="flex items-center justify-between gap-2 py-2 hover:bg-gray-50">
                                <span class="truncate"><span class="text-xs text-gray-400 mr-1">{{ $job->job_id }}</span>{{ $job->job_type }}</span>
                                @if ($days === null)
                                    <span class="text-[11px] px-2 py-0.5 rounded bg-gray-100 text-gray-500 whitespace-nowrap">No deadline</span>
                                @elseif ($days <= 0)
                                    <span class="text-[11px] px-2 py-0.5 rounded bg-red-100 text-red-700 whitespace-nowrap">{{ $days === 0 ? 'Today' : abs($days).'d late' }}</span>
                                @elseif ($days <= 3)
                                    <span class="text-[11px] px-2 py-0.5 rounded bg-amber-100 text-amber-800 whitespace-nowrap">{{ $days }}d</span>
                                @else
                                    <span class="text-[11px] px-2 py-0.5 rounded bg-gray-100 text-gray-500 whitespace-nowrap">{{ $days }}d</span>
                                @endif
                            </a>
                        @empty
                            <p class="py-2 text-gray-400">Nothing assigned to you right now.</p>
                        @endforelse
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if (\Illuminate\Support\Facades\Gate::allows('create', \App\Models\Job::class))
                            <a href="{{ route('jobs.create') }}" target="_blank" rel="noopener" class="text-xs font-semibold px-3 py-1.5 rounded-md bg-[#E91E63] text-white hover:opacity-90">+ New job</a>
                        @endif
                        <a href="{{ route('dashboard') }}" target="_blank" rel="noopener" class="text-xs font-semibold px-3 py-1.5 rounded-md border border-gray-200 hover:bg-gray-50">Open Jobs</a>
                        <a href="{{ route('jobs.index', ['view' => 'queue']) }}" target="_blank" rel="noopener" class="text-xs font-semibold px-3 py-1.5 rounded-md border border-gray-200 hover:bg-gray-50">Queue{{ $queueCount ? " ({$queueCount})" : '' }}</a>
                    </div>
                @else
                    <p class="text-sm text-gray-500 mt-2">No access. Ask BOD if you need it.</p>
                @endif
            </div>

            {{-- Finance --}}
            <div class="bg-white rounded-xl shadow-sm p-5 flex flex-col {{ $user->canAccess('finance') ? '' : 'opacity-50' }}">
                <div class="flex items-center gap-2 mb-1"><span class="text-xl">💰</span><h2 class="font-semibold">Finance</h2></div>
                @if ($user->canAccess('finance'))
                    <p class="text-sm text-gray-500 flex-1">Ledger, reports and vendor payments.</p>
                    <div class="mt-4"><a href="{{ route('finance.index') }}" target="_blank" rel="noopener" class="text-xs font-semibold px-3 py-1.5 rounded-md border border-gray-200 hover:bg-gray-50">Open Finance</a></div>
                @else
                    <p class="text-sm text-gray-500 mt-2">No access. Ask BOD if you need it.</p>
                @endif
            </div>

            {{-- HR --}}
            <div class="bg-white rounded-xl shadow-sm p-5 flex flex-col {{ $user->canAccess('hr') ? '' : 'opacity-50' }}">
                <div class="flex items-center gap-2 mb-1"><span class="text-xl">👥</span><h2 class="font-semibold">HR</h2></div>
                @if ($user->canAccess('hr'))
                    <p class="text-sm text-gray-500 flex-1">Leave, announcements, payslips and more.</p>
                    <div class="mt-4"><span class="text-xs font-semibold px-3 py-1.5 rounded-md bg-gray-100 text-gray-500">Coming soon</span></div>
                @else
                    <p class="text-sm text-gray-500 mt-2">No access. Ask BOD if you need it.</p>
                @endif
            </div>
        </div>
    </div>
</x-os-layout>
