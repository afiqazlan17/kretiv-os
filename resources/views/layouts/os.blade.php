<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>KretivOS</title>
        @include('layouts.partials.pwa-head')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-gray-900">
        @php $user = auth()->user(); @endphp
        <div class="os-stage min-h-screen relative">
            <div class="guest-glow guest-glow--pink" aria-hidden="true" style="opacity:0.18;"></div>
            <div class="guest-glow guest-glow--orange" aria-hidden="true" style="opacity:0.14;"></div>

            <header class="relative z-30">
                <div class="max-w-[1600px] mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-4">
                    <a href="{{ route('os.home') }}" class="flex items-center gap-2.5">
                        <img src="{{ asset('images/kretivco-logo.png') }}" alt="Kretivco" class="w-9 h-9">
                        <span class="font-bold tracking-tight text-white text-lg">KretivOS</span>
                    </a>

                    <div class="flex items-center gap-3 text-sm" x-data="{ open: false }">
                        <span class="hidden sm:inline text-white/70">{{ $user->name }}</span>
                        <div class="relative" @click.outside="open = false">
                            <button type="button" @click="open = !open" title="Settings" aria-label="Settings"
                                    class="w-8 h-8 rounded-full flex items-center justify-center text-white/70 hover:text-white hover:bg-white/10">
                                <x-icon name="settings" class="w-5 h-5" />
                            </button>
                            <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-52 bg-white rounded-xl shadow-xl p-1.5 text-sm text-gray-700">
                                <a href="{{ route('password.change') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg hover:bg-[#FFF5F1]"><x-icon name="key-round" class="w-4 h-4 text-gray-400" /> Change password</a>
                                @if ($user->isBod())
                                    <a href="{{ route('settings.index') }}" class="flex items-center gap-2.5 px-3 py-2 rounded-lg hover:bg-[#FFF5F1]"><x-icon name="user-cog" class="w-4 h-4 text-gray-400" /> Users &amp; Access</a>
                                @endif
                            </div>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-white/60 hover:text-white">Log out</button>
                        </form>
                    </div>
                </div>
            </header>

            <main class="relative z-10 max-w-[1600px] mx-auto px-4 sm:px-6 pb-10">
                {{ $slot }}
            </main>
        </div>

        @stack('scripts')
    </body>
</html>
