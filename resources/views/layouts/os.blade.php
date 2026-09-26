<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Kretiv OS</title>
        @include('layouts.partials.pwa-head')
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-gray-900">
        @php $user = auth()->user(); @endphp
        <div class="os-stage min-h-screen relative">
            <div class="guest-glow guest-glow--pink" aria-hidden="true" style="opacity:0.18;"></div>
            <div class="guest-glow guest-glow--orange" aria-hidden="true" style="opacity:0.14;"></div>

            <header class="relative z-10">
                <div class="max-w-[1600px] mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-4">
                    <a href="{{ route('os.home') }}" class="flex items-center gap-2.5">
                        <img src="{{ asset('images/kretivco-logo.png') }}" alt="Kretivco" class="w-9 h-9">
                        <span class="font-bold tracking-tight text-white text-lg">KretivOS</span>
                    </a>

                    <div class="flex items-center gap-3 text-sm" x-data="{ open: false }">
                        <span class="hidden sm:inline text-white/70">{{ $user->name }}</span>
                        @if ($user->isBod())
                            <div class="relative" @click.outside="open = false">
                                <button type="button" @click="open = !open" title="Users &amp; Access"
                                        class="w-8 h-8 rounded-full flex items-center justify-center text-white/70 hover:text-white hover:bg-white/10">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.53 1.53 0 0 1-2.28.94c-1.37-.83-2.93.73-2.1 2.1.53.88.18 2-.94 2.28-1.56.38-1.56 2.6 0 2.98a1.53 1.53 0 0 1 .94 2.28c-.83 1.37.73 2.93 2.1 2.1a1.53 1.53 0 0 1 2.28.94c.38 1.56 2.6 1.56 2.98 0a1.53 1.53 0 0 1 2.28-.94c1.37.83 2.93-.73 2.1-2.1a1.53 1.53 0 0 1 .94-2.28c1.56-.38 1.56-2.6 0-2.98a1.53 1.53 0 0 1-.94-2.28c.83-1.37-.73-2.93-2.1-2.1a1.53 1.53 0 0 1-2.28-.94zM10 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                                <div x-show="open" x-cloak x-transition class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl py-1 text-sm text-gray-700">
                                    <a href="{{ route('os.access') }}" class="block px-4 py-2 hover:bg-gray-50">👤 Users &amp; Access</a>
                                </div>
                            </div>
                        @endif
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
