<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4" x-data="{ show: false }">
            <x-input-label for="password" :value="__('Password')" />

            <div class="relative">
                <x-text-input id="password" class="block mt-1 w-full pr-10"
                                type="password"
                                x-bind:type="show ? 'text' : 'password'"
                                name="password"
                                required autocomplete="current-password" />

                <button type="button" @click="show = !show" class="guest-password-toggle" :aria-label="show ? 'Hide password' : 'Show password'">
                    <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10 3.5c-4.5 0-8.3 3-9.9 6.5 1.6 3.5 5.4 6.5 9.9 6.5s8.3-3 9.9-6.5C18.3 6.5 14.5 3.5 10 3.5zm0 11a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9z"/>
                        <path d="M10 7.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z"/>
                    </svg>
                    <svg x-show="show" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l14.5 14.5a.75.75 0 1 0 1.06-1.06l-1.86-1.86c1.68-1.24 2.96-2.96 3.78-4.86-1.6-3.5-5.4-6.5-9.9-6.5-1.36 0-2.65.27-3.83.76L3.28 2.22zM10 7.5c.24 0 .48.03.7.08l-3.12 3.12A2.5 2.5 0 0 1 10 7.5z"/>
                        <path d="M2.1 4.62 4.5 7.02A9.77 9.77 0 0 0 .1 10c1.6 3.5 5.4 6.5 9.9 6.5 1.2 0 2.36-.2 3.44-.58l-1.6-1.6a4.5 4.5 0 0 1-5.75-5.75L2.1 4.62z"/>
                    </svg>
                </button>
            </div>

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
