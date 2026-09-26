<div x-data="{ mobileOpen: false }">
    {{-- Mobile hamburger --}}
    <button @click="mobileOpen = true" class="md:hidden fixed top-3 left-3 z-[60] w-10 h-10 rounded-lg bg-gradient-to-br from-[#E91E63] to-[#F25C54] text-white text-xl flex items-center justify-center shadow-lg">
        ☰
    </button>

    {{-- Desktop sidebar — always visible at md+, never toggled --}}
    <aside class="hidden md:flex md:fixed md:inset-y-0 md:left-0 md:z-50 md:w-60 md:flex-col bg-white text-gray-800 border-r border-[#F3E6E1] overflow-x-hidden">
        @include('layouts.partials.sidebar-nav')
    </aside>

    {{-- Mobile sidebar — off-canvas drawer toggled by the hamburger --}}
    <div x-show="mobileOpen" x-cloak x-transition.opacity @click="mobileOpen = false" class="md:hidden fixed inset-0 z-[49] bg-black/30"></div>
    <aside x-show="mobileOpen" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
           class="md:hidden fixed inset-y-0 left-0 z-50 w-60 bg-white text-gray-800 flex flex-col overflow-x-hidden">
        @include('layouts.partials.sidebar-nav')
    </aside>
</div>
