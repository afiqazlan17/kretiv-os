<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        Enter your @kretiv.co email and we'll send you a link to set a new password. The link works for 60 minutes.
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" placeholder="name@kretiv.co" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between mt-4">
            <a href="{{ route('login') }}" class="text-sm text-gray-600 underline hover:text-gray-900">Back to log in</a>
            <x-primary-button>Email Reset Link</x-primary-button>
        </div>
    </form>
</x-guest-layout>
