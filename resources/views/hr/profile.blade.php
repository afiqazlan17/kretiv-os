<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">My Profile</h2>
    </x-slot>

    @php $field = 'block w-full text-sm'; @endphp
    <div class="p-5 md:p-7 space-y-4 max-w-4xl">
        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif

        <div class="k-card p-5 md:p-6 flex flex-wrap items-center gap-4">
            <span class="w-14 h-14 rounded-2xl bg-gradient-to-br from-[#7C3AED] to-[#C084FC] text-white flex items-center justify-center text-xl font-bold">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
            <div class="flex-1 min-w-[200px]">
                <div class="text-lg font-bold text-gray-900">{{ $user->name }}</div>
                <div class="text-sm text-gray-500">{{ $user->title ?: config("kretivco.roles.{$user->role}.label") }}{{ $user->department ? ' · '.config("kretivco.departments.{$user->department}.label") : '' }}</div>
                <div class="text-xs text-gray-400">{{ $user->email }}{{ $employee->start_date ? ' · joined '.$employee->start_date->format('d M Y') : '' }}</div>
            </div>
        </div>

        {{-- Set by HR: shown, not editable here. --}}
        <div class="k-card p-5 md:p-6">
            <h3 class="text-base font-bold text-gray-900 mb-1">Employment</h3>
            <p class="text-xs text-gray-400 mb-4">Kept by HR. Ask HR if anything here is wrong.</p>
            <dl class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                <div><dt class="text-xs text-gray-400">Type</dt><dd class="text-gray-800">{{ \App\Models\Employee::EMPLOYMENT_TYPES[$employee->employment_type] ?? 'Not set' }}</dd></div>
                <div><dt class="text-xs text-gray-400">IC number</dt><dd class="text-gray-800">{{ $employee->ic_number ?: 'Not set' }}</dd></div>
                <div><dt class="text-xs text-gray-400">EPF no.</dt><dd class="text-gray-800">{{ $employee->epf_number ?: 'Not set' }}</dd></div>
                <div><dt class="text-xs text-gray-400">SOCSO no.</dt><dd class="text-gray-800">{{ $employee->socso_number ?: 'Not set' }}</dd></div>
                <div><dt class="text-xs text-gray-400">Income tax no.</dt><dd class="text-gray-800">{{ $employee->tax_number ?: 'Not set' }}</dd></div>
            </dl>
        </div>

        <form method="POST" action="{{ route('hr.profile.update') }}" class="k-card p-5 md:p-6">
            @csrf @method('PUT')
            <h3 class="text-base font-bold text-gray-900 mb-1">Contact, bank and emergency</h3>
            <p class="text-xs text-gray-400 mb-4">Keep these up to date. Your salary goes to the bank account below.</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div><label class="text-xs text-gray-500">Phone</label><input type="text" name="phone" value="{{ old('phone', $employee->phone) }}" class="{{ $field }}"></div>
                <div class="sm:col-span-2"><label class="text-xs text-gray-500">Personal email</label><input type="email" name="personal_email" value="{{ old('personal_email', $employee->personal_email) }}" class="{{ $field }}"></div>
                <div class="sm:col-span-3"><label class="text-xs text-gray-500">Home address</label><textarea name="address" rows="2" class="{{ $field }}">{{ old('address', $employee->address) }}</textarea></div>
                <div><label class="text-xs text-gray-500">Bank</label><input type="text" name="bank_name" value="{{ old('bank_name', $employee->bank_name) }}" class="{{ $field }}"></div>
                <div class="sm:col-span-2"><label class="text-xs text-gray-500">Bank account no.</label><input type="text" name="bank_account" value="{{ old('bank_account', $employee->bank_account) }}" class="{{ $field }}"></div>
                <div><label class="text-xs text-gray-500">Emergency contact</label><input type="text" name="emergency_name" value="{{ old('emergency_name', $employee->emergency_name) }}" class="{{ $field }}"></div>
                <div><label class="text-xs text-gray-500">Relationship</label><input type="text" name="emergency_relation" value="{{ old('emergency_relation', $employee->emergency_relation) }}" class="{{ $field }}"></div>
                <div><label class="text-xs text-gray-500">Emergency phone</label><input type="text" name="emergency_phone" value="{{ old('emergency_phone', $employee->emergency_phone) }}" class="{{ $field }}"></div>
            </div>
            <button type="submit" class="mt-4 text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#6D28D9] to-[#A855F7] hover:brightness-110">Save</button>
        </form>
    </div>
</x-app-layout>
