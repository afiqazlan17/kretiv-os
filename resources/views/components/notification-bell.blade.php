@props(['dark' => false, 'align' => 'left', 'panel' => null])
{{-- Bell with the inbox count; opens the same list as the KretivOS home. --}}
@php $inbox = \App\Services\NotificationCenter::for(auth()->user()); @endphp
<div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape.window="open = false">
    <button type="button" @click="open = !open" class="relative w-9 h-9 rounded-xl flex items-center justify-center {{ $dark ? 'text-white/70 hover:text-white hover:bg-white/10' : 'text-gray-500 hover:text-gray-900 hover:bg-black/5' }}" aria-label="Notifications">
        <x-icon name="bell" class="w-5 h-5" />
        @if ($inbox['count'] > 0)
            <span class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-[#E91E63] text-white text-[10px] font-bold flex items-center justify-center">{{ $inbox['count'] > 99 ? '99+' : $inbox['count'] }}</span>
        @endif
    </button>
    <div x-show="open" x-cloak x-transition
         class="{{ $panel ?? ('absolute mt-2 '.($align === 'right' ? 'right-0' : 'left-0')) }} w-[min(360px,calc(100vw-2rem))] max-h-[70vh] overflow-y-auto rounded-2xl shadow-2xl p-3 z-[80] {{ $dark ? 'bg-[#2A1418] border border-white/10' : 'bg-white border border-black/5' }}">
        <x-notification-list :inbox="$inbox" :dark="$dark" />
    </div>
</div>
