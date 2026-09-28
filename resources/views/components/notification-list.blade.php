@props(['inbox', 'dark' => false, 'limit' => 12, 'only' => null, 'headings' => true])
{{-- The notification inbox (App\Services\NotificationCenter): actions first, then updates. --}}
@php
    $tone = $dark
        ? ['red' => 'bg-red-500/10 hover:bg-red-500/15 text-red-200', 'amber' => 'bg-amber-500/10 hover:bg-amber-500/15 text-amber-100', 'blue' => 'bg-violet-500/10 hover:bg-violet-500/15 text-violet-100']
        : ['red' => 'bg-red-50 hover:bg-red-100 text-red-800', 'amber' => 'bg-amber-50 hover:bg-amber-100 text-amber-900', 'blue' => 'bg-violet-50 hover:bg-violet-100 text-violet-900'];
    $iconTone = $dark ? ['red' => 'text-red-300', 'amber' => 'text-amber-300', 'blue' => 'text-violet-300'] : ['red' => 'text-red-500', 'amber' => 'text-amber-500', 'blue' => 'text-violet-500'];
    $head = $dark ? 'text-white/40' : 'text-gray-400';
@endphp
<div class="space-y-3">
    @if ($only !== 'updates' && $inbox['actions']->isNotEmpty())
        <div class="space-y-1.5">
            @if ($headings)<p class="text-[10px] font-semibold uppercase tracking-wider {{ $head }}">Needs your action</p>@endif
            @foreach ($inbox['actions']->take($limit) as $n)
                <a href="{{ $n['url'] }}" class="flex items-center gap-2.5 text-sm px-3 py-2 rounded-lg {{ $tone[$n['tone']] }}">
                    <x-icon :name="$n['icon']" class="w-4 h-4 shrink-0 {{ $iconTone[$n['tone']] }}" />
                    <span class="min-w-0 leading-snug">{{ $n['text'] }}</span>
                </a>
            @endforeach
        </div>
    @endif
    @if ($only !== 'actions' && $inbox['updates']->isNotEmpty())
        <div class="space-y-1.5">
            @if ($headings)
                <div class="flex items-center justify-between">
                    <p class="text-[10px] font-semibold uppercase tracking-wider {{ $head }}">Updates</p>
                    <form method="POST" action="{{ route('notifications.read-all') }}">@csrf
                        <button class="text-[11px] font-semibold {{ $dark ? 'text-white/50 hover:text-white' : 'text-gray-500 hover:text-gray-900' }}">Mark all as read</button>
                    </form>
                </div>
            @endif
            @foreach ($inbox['updates']->take($limit) as $n)
                <a href="{{ route('notifications.open', $n['key']) }}" class="flex items-center gap-2.5 text-sm px-3 py-2 rounded-lg {{ $tone['blue'] }}">
                    <x-icon :name="$n['icon']" class="w-4 h-4 shrink-0 {{ $iconTone['blue'] }}" />
                    <span class="min-w-0 leading-snug flex-1">{{ $n['text'] }}</span>
                    <span class="text-[10px] shrink-0 {{ $head }}">{{ $n['at'] && $n['at']->gt(now()->subMinute()) ? 'now' : $n['at']?->diffForHumans(short: true) }}</span>
                </a>
            @endforeach
        </div>
    @endif
    @php $shown = $only === 'actions' ? $inbox['actions']->count() : ($only === 'updates' ? $inbox['updates']->count() : $inbox['count']); @endphp
    @if ($shown === 0)
        <p class="text-sm px-1 py-1 {{ $dark ? 'text-white/40' : 'text-gray-400' }}">{{ $only === 'updates' ? 'No new announcements or updates.' : "You're all caught up." }}</p>
    @endif
</div>
