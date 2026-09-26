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
    <body class="font-sans text-gray-900 antialiased">
        <div class="guest-stage min-h-screen flex flex-col items-center justify-center px-4 py-10 relative overflow-hidden">
            {{-- Soft, slow-drifting brand-colour glows behind everything — depth
                 without drawing attention away from the logo/form. --}}
            <div class="guest-glow guest-glow--pink" aria-hidden="true"></div>
            <div class="guest-glow guest-glow--orange" aria-hidden="true"></div>

            <a href="/" class="relative z-10 guest-logo-float">
                <img src="{{ asset('images/kretivco-logo.png') }}" alt="Kretivco Mediaworks" class="w-36 h-36 sm:w-44 sm:h-44 drop-shadow-2xl">
            </a>

            <div class="relative z-10 text-center mt-3 mb-6">
                <h1 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">Welcome to KretivOS</h1>
                <p class="text-base sm:text-lg text-white/60 mt-1">Your KretivWorkplace</p>
            </div>

            {{-- Dark glass panel instead of a stark white card — sits inside the
                 same gradient/glow language as the page instead of breaking it. --}}
            <div class="guest-card relative z-10 w-full sm:max-w-md px-6 py-7 overflow-hidden rounded-2xl">
                {{ $slot }}
            </div>

            <p class="relative z-10 mt-6 text-xs text-white/40">Kretivco Mediaworks</p>
        </div>
    </body>
</html>
