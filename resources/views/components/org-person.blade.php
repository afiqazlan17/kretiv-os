@props(['person', 'color' => '#7C3AED', 'tag' => null, 'size' => 'md', 'stack' => false, 'showTitle' => true])
@php
    $initials = \Illuminate\Support\Str::of($person->name)->explode(' ')
        ->reject(fn ($w) => in_array(strtolower($w), ['bin', 'binti', 'bt', 'b.', 'bte', 'a/l', 'a/p'], true))
        ->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->join('');
    $big = $size === 'lg';
    $title = $person->title ?: config("kretivco.roles.{$person->role}.label");
@endphp
<div {{ $attributes->merge(['class' => 'oc-card relative bg-white rounded-2xl border border-black/[0.06] shadow-[0_8px_24px_-14px_rgba(76,29,149,0.35)] '.($big ? 'px-5 py-4' : ($stack ? 'px-3 py-3.5' : 'px-3.5 py-3'))]) }}>
    <div class="{{ $stack ? 'flex flex-col items-center text-center gap-2' : 'flex items-center gap-3' }} min-w-0">
        <span class="{{ $big ? 'w-12 h-12 text-base' : 'w-9 h-9 text-xs' }} shrink-0 rounded-full flex items-center justify-center font-bold text-white"
              style="background: linear-gradient(135deg, {{ $color }}, {{ $color }}B3)">{{ strtoupper($initials) }}</span>
        <div class="min-w-0">
            <p class="{{ $big ? 'text-base' : 'text-[13px]' }} font-bold text-gray-900 leading-tight break-words">{{ $person->name }}</p>
            @if ($showTitle)
                <p class="{{ $big ? 'text-sm' : 'text-xs' }} text-gray-500 leading-snug break-words mt-0.5">{{ $title }}</p>
            @endif
            @if ($tag)
                <span class="inline-block mt-1.5 text-[10px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded whitespace-nowrap" style="color: {{ $color }}; background: {{ $color }}14">{{ $tag }}</span>
            @endif
        </div>
    </div>
</div>
