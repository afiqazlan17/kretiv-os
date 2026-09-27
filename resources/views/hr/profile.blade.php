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
                <div class="text-sm text-gray-500">{{ $user->title ?: config("kretivco.roles.{$user->role}.label") }}{{ $user->department ? ' · '.\App\Support\Departments::label($user->department) : '' }}</div>
                <div class="text-xs text-gray-400">{{ $user->email }}{{ $employee->start_date ? ' · joined '.$employee->start_date->format('d M Y') : '' }}</div>
            </div>
        </div>

        @php
            $groups = [
                'Contact' => ['phone', 'personal_email', 'address'],
                'Bank (salary goes here)' => ['bank_name', 'bank_account'],
                'Emergency contact' => ['emergency_name', 'emergency_relation', 'emergency_phone'],
                'Statutory' => ['ic_number', 'epf_number', 'socso_number', 'tax_number'],
            ];
            $labels = \App\Models\ProfileChangeRequest::FIELDS;
        @endphp

        <div class="k-card p-5 md:p-6">
            <h3 class="text-base font-bold text-gray-900 mb-1">Employment</h3>
            <p class="text-xs text-gray-400 mb-4">Kept by HR.</p>
            <dl class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-sm">
                <div><dt class="text-xs text-gray-400">Type</dt><dd class="text-gray-800">{{ \App\Models\Employee::EMPLOYMENT_TYPES[$employee->employment_type] ?? 'Not set' }}</dd></div>
                <div><dt class="text-xs text-gray-400">Department</dt><dd class="text-gray-800">{{ \App\Support\Departments::label($user->department) ?? 'Not set' }}</dd></div>
                <div><dt class="text-xs text-gray-400">Joined</dt><dd class="text-gray-800">{{ $employee->start_date?->format('d M Y') ?? 'Not set' }}</dd></div>
            </dl>
        </div>

        @if ($pendingRequest)
            <div class="k-card p-5 md:p-6 border-l-4 border-l-[#A855F7]">
                <h3 class="text-base font-bold text-gray-900 mb-1">Waiting for HR</h3>
                <p class="text-xs text-gray-400 mb-3">Sent {{ $pendingRequest->created_at->format('d M Y, g:ia') }}. Your details update once HR approves.</p>
                <ul class="text-sm space-y-1">
                    @foreach ($pendingRequest->changes as $field => $value)
                        <li><span class="text-gray-500">{{ $labels[$field] ?? $field }}:</span> <span class="text-gray-900">{{ $value ?: '(blank)' }}</span></li>
                    @endforeach
                </ul>
            </div>
        @elseif ($lastDecision && $lastDecision->reviewed_at?->gt(now()->subDays(14)))
            <div class="rounded-xl border text-sm px-4 py-3 {{ $lastDecision->status === 'approved' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-amber-50 border-amber-200 text-amber-800' }}">
                Your last change request was {{ $lastDecision->status }} on {{ $lastDecision->reviewed_at->format('d M') }}{{ $lastDecision->review_note ? ': '.$lastDecision->review_note : '.' }}
            </div>
        @endif

        <form method="POST" action="{{ route('hr.profile.update') }}" class="k-card p-5 md:p-6" x-data="{ editing: false }">
            @csrf @method('PUT')
            <div class="flex items-start justify-between gap-3 mb-4">
                <div>
                    <h3 class="text-base font-bold text-gray-900 mb-1">My details</h3>
                    <p class="text-xs text-gray-400">To change anything, send a request. HR checks it and updates your record.</p>
                </div>
                @unless ($pendingRequest)
                    <button type="button" x-show="!editing" @click="editing = true" class="shrink-0 text-sm font-semibold px-3 py-1.5 rounded-xl border border-[#A855F7]/40 text-[#6D28D9] hover:bg-[#A855F7]/10">Request a change</button>
                @endunless
            </div>

            @foreach ($groups as $title => $fields)
                <p class="text-[11px] uppercase tracking-wide text-gray-400 mt-4 mb-2">{{ $title }}</p>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    @foreach ($fields as $f)
                        <div class="{{ in_array($f, ['address', 'personal_email', 'bank_account']) ? 'sm:col-span-'.($f === 'address' ? 3 : 2) : '' }}">
                            <label class="text-xs text-gray-500">{{ $labels[$f] }}</label>
                            <p x-show="!editing" class="text-sm text-gray-900 py-1.5">{{ $employee->$f ?: 'Not set' }}</p>
                            @if ($f === 'address')
                                <textarea x-show="editing" x-cloak name="address" rows="2" class="{{ $field }}">{{ old('address', $employee->address) }}</textarea>
                            @else
                                <input x-show="editing" x-cloak type="{{ $f === 'personal_email' ? 'email' : 'text' }}" name="{{ $f }}" value="{{ old($f, $employee->$f) }}" class="{{ $field }}">
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach

            <div x-show="editing" x-cloak class="mt-4 space-y-3">
                <div><label class="text-xs text-gray-500">Reason (optional)</label><input type="text" name="reason" maxlength="255" placeholder="e.g. changed bank" class="{{ $field }}"></div>
                <div class="flex gap-2">
                    <button type="submit" class="text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#6D28D9] to-[#A855F7] hover:brightness-110">Send to HR</button>
                    <button type="button" @click="editing = false" class="text-sm px-4 py-2 rounded-xl text-gray-500 hover:text-gray-800">Cancel</button>
                </div>
            </div>
        </form>
    </div>
</x-app-layout>
