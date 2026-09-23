<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Kretiv OS</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-100 text-gray-900">
        @php
            $user = auth()->user();
            $moduleLinks = ['jobs' => route('dashboard'), 'finance' => route('finance.index'), 'hr' => null];
        @endphp
        <header class="bg-[#1A1025] text-white">
            <div class="max-w-[1600px] mx-auto px-4 sm:px-6 h-14 flex items-center justify-between gap-4">
                <a href="{{ route('os.home') }}" class="font-extrabold tracking-tight text-lg">Kretiv <span class="text-[#E91E63]">OS</span></a>

                <nav class="flex items-center gap-1 text-[13px]">
                    <a href="{{ route('os.home') }}" class="px-3 py-1.5 rounded-md {{ request()->routeIs('os.*') ? 'bg-white/10 text-white font-semibold' : 'text-white/60 hover:text-white' }}">OS</a>
                    @foreach (config('kretivco.modules') as $key => $module)
                        @continue(! $user->canAccess($key))
                        @if ($moduleLinks[$key])
                            <a href="{{ $moduleLinks[$key] }}" target="_blank" rel="noopener" class="px-3 py-1.5 rounded-md text-white/60 hover:text-white">{{ $module['label'] }}</a>
                        @else
                            <span class="px-3 py-1.5 rounded-md text-white/30 cursor-default" title="Coming soon">{{ $module['label'] }}</span>
                        @endif
                    @endforeach
                </nav>

                <div class="flex items-center gap-3 text-[13px]">
                    @if ($user->isBod())
                        <a href="{{ route('os.access') }}" class="text-white/60 hover:text-white {{ request()->routeIs('os.access') ? 'text-white font-semibold' : '' }}">Users &amp; Access</a>
                    @endif
                    <span class="hidden sm:inline text-white/70">{{ $user->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-white/60 hover:text-white">Log out</button>
                    </form>
                </div>
            </div>
        </header>

        <main class="max-w-[1600px] mx-auto px-4 sm:px-6 py-6">
            {{ $slot }}
        </main>

        @stack('scripts')
    </body>
</html>
