<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>
        @include('layouts.partials.pwa-head')

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.sidebar')

            <div class="md:ml-60">
                <!-- Page Heading -->
                @isset($header)
                    {{-- pl-16 on phones clears the sidebar's fixed hamburger button
                         (top-3 left-3, a 40px square) — every page's header content
                         starts at the same top-left corner the button sits in, so
                         without this the first line or two renders right behind it. --}}
                    <header class="bg-gradient-to-br from-[#E91E63] to-[#AD1457] pl-16 pr-6 py-6 md:px-8">
                        {{ $header }}
                    </header>
                @endisset

                <!-- Page Content -->
                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>

        @stack('scripts')
    </body>
</html>
