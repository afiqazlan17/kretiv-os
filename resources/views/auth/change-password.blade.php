<x-guest-layout>
    {{-- Forced after signing in with a temporary password from BOD (the rest of the app stays locked until done), or opened from the gear menu. --}}
    @php $forced = auth()->user()->must_change_password; @endphp
    <div class="mb-5">
        <h2 class="text-lg font-bold text-white">{{ $forced ? 'Set your own password' : 'Change your password' }}</h2>
        <p class="text-sm text-gray-600 mt-1">{{ $forced ? 'You signed in with a temporary password. Choose a new one to continue.' : 'Enter your current password, then choose a new one.' }}</p>
    </div>

    <form method="POST" action="{{ route('password.change.update') }}" x-data="{ show: false }">
        @csrf
        @method('PUT')

        @unless ($forced)
            <div class="mb-4">
                <x-input-label for="current_password" value="Current password" />
                <x-text-input id="current_password" class="block mt-1 w-full" type="password" x-bind:type="show ? 'text' : 'password'"
                              name="current_password" required autocomplete="current-password" />
                <x-input-error :messages="$errors->get('current_password')" class="mt-2" />
            </div>
        @endunless

        <div>
            <x-input-label for="password" value="New password" />
            <div class="relative">
                <x-text-input id="password" class="block mt-1 w-full pr-10" type="password" x-bind:type="show ? 'text' : 'password'"
                              name="password" required autofocus autocomplete="new-password" />
                <button type="button" @click="show = !show" class="guest-password-toggle" :aria-label="show ? 'Hide password' : 'Show password'">
                    <x-icon name="eye" class="h-5 w-5" />
                </button>
            </div>
            <p class="text-xs text-gray-600 mt-1.5">At least 8 characters.</p>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password_confirmation" value="Type it again" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" x-bind:type="show ? 'text' : 'password'"
                          name="password_confirmation" required autocomplete="new-password" />
        </div>

        <div class="flex items-center justify-between mt-6">
            @if ($forced)
                <button type="button" onclick="document.getElementById('logout-form').submit()" class="text-sm text-gray-600 underline">Log out</button>
            @else
                <a href="{{ url()->previous() !== url()->current() ? url()->previous() : '/' }}" class="text-sm text-gray-600 underline">Cancel</a>
            @endif
            <x-primary-button>Save and continue</x-primary-button>
        </div>
    </form>

    <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">@csrf</form>
</x-guest-layout>
