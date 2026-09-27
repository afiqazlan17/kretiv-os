<x-app-layout>
    @php
        $isNew = ! $user->exists;
        $v = fn (string $field, $fallback = null) => old($field, $fallback);
        $allow = collect($employee->allowances ?? [])->pluck('amount', 'type');
        $field = 'block w-full text-sm';
    @endphp
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-3">
            <h2 class="font-bold text-2xl text-white leading-tight">{{ $isNew ? 'New Joiner' : $user->name }}</h2>
            @unless ($isNew)
                <span class="text-xs font-semibold bg-white/20 rounded-full px-3.5 py-1.5 text-white">{{ $user->active ? 'Active' : 'Inactive' }}</span>
            @endunless
        </div>
    </x-slot>

    <div class="p-5 md:p-7 space-y-4 max-w-4xl">
        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ $isNew ? route('hr.staff.store') : route('hr.staff.update', $user) }}" class="space-y-4"
              x-data="staffForm(@js(['email' => $v('email', $user->email), 'name' => $v('name', $user->name), 'userId' => $user->id, 'salary' => $v('basic_salary', $employee->basic_salary), 'limit' => (float) config('kretivco.ot_wage_limit')]))">
            @csrf
            @unless ($isNew) @method('PUT') @endunless

            <div class="k-card p-5 md:p-6">
                <h3 class="text-base font-bold text-gray-900 mb-4">Account and job</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div><label class="text-xs text-gray-500">Full name (as in IC) *</label>
                        <input type="text" name="name" x-model="name" @input.debounce.500ms="autoSuggest()" required class="{{ $field }}"></div>
                    <div><label class="text-xs text-gray-500">Company email (also their KretivOS login) *</label>
                        <div class="flex gap-2">
                            <input type="email" name="email" x-model="email" @input="touched = true" required class="{{ $field }}">
                            <button type="button" @click="suggest()" class="shrink-0 text-xs font-semibold px-3 rounded-xl border border-[#E9D5FF] text-[#6D28D9] hover:bg-[#F5F3FF]">Suggest</button>
                        </div>
                        <p class="text-[11px] text-gray-400 mt-1">Also create this mailbox in cPanel &gt; Email Accounts.</p></div>
                    <div><label class="text-xs text-gray-500">Role in KretivOS *</label>
                        <select name="role" class="{{ $field }}">
                            @foreach (config('kretivco.roles') as $key => $r)
                                <option value="{{ $key }}" @selected($v('role', $user->role) === $key)>{{ $r['label'] }}</option>
                            @endforeach
                        </select></div>
                    <div><label class="text-xs text-gray-500">Department</label>
                        <select name="department" class="{{ $field }}">
                            <option value="">None / company-wide</option>
                            @foreach (\App\Support\Departments::all() as $key => $d)
                                <option value="{{ $key }}" @selected($v('department', $user->department) === $key)>{{ $d['label'] }}</option>
                            @endforeach
                        </select></div>
                    <div><label class="text-xs text-gray-500">Position / title</label>
                        <input type="text" name="title" value="{{ $v('title', $user->title) }}" placeholder="Graphic Designer" class="{{ $field }}"></div>
                    <div><label class="text-xs text-gray-500">Reports to (Board members, for the Organisation Chart)</label>
                        <select name="reports_to_user_id" class="{{ $field }}">
                            <option value="">Nobody (top of the chart)</option>
                            @foreach (\App\Models\User::where('role', \App\Models\User::ROLE_BOD)->where('active', true)->where('id', '!=', $user->id ?? 0)->orderBy('name')->get() as $boss)
                                <option value="{{ $boss->id }}" @selected((string) $v('reports_to_user_id', $employee->reports_to_user_id) === (string) $boss->id)>{{ $boss->name }}</option>
                            @endforeach
                        </select></div>
                    <div><label class="text-xs text-gray-500">Staff no.</label>
                        <input type="text" name="staff_no" value="{{ $v('staff_no', $employee->staff_no) }}" class="{{ $field }}"></div>
                </div>
            </div>

            <div class="k-card p-5 md:p-6">
                <h3 class="text-base font-bold text-gray-900 mb-4">Package</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div><label class="text-xs text-gray-500">Employment type *</label>
                        <select name="employment_type" class="{{ $field }}">
                            @foreach (\App\Models\Employee::EMPLOYMENT_TYPES as $key => $label)
                                <option value="{{ $key }}" @selected($v('employment_type', $employee->employment_type) === $key)>{{ $label }}</option>
                            @endforeach
                        </select></div>
                    <div><label class="text-xs text-gray-500">Start date</label>
                        <input type="date" name="start_date" value="{{ $v('start_date', $employee->start_date?->toDateString()) }}" class="{{ $field }}"></div>
                    <div><label class="text-xs text-gray-500">End date (contract / intern)</label>
                        <input type="date" name="end_date" value="{{ $v('end_date', $employee->end_date?->toDateString()) }}" class="{{ $field }}"></div>
                    <div><label class="text-xs text-gray-500">Basic salary (RM / month)</label>
                        <input type="number" step="0.01" min="0" name="basic_salary" x-model="salary" class="{{ $field }}"></div>
                    @foreach (config('kretivco.allowance_types') as $type => $label)
                        <div><label class="text-xs text-gray-500">{{ $label }} allowance (RM)</label>
                            <input type="number" step="0.01" min="0" name="allowances[{{ $type }}]" value="{{ $v("allowances.{$type}", $allow[$type] ?? '') }}" class="{{ $field }}"></div>
                    @endforeach
                </div>
                <label class="mt-4 flex items-start gap-2 text-sm text-gray-700">
                    <input type="hidden" name="ot_eligible" value="0">
                    <input type="checkbox" name="ot_eligible" value="1" class="mt-0.5 rounded" @checked($v('ot_eligible', $isNew ? true : $employee->ot_eligible))>
                    <span>Eligible for overtime
                        <span class="block text-xs text-gray-400" x-text="Number(salary) > limit ? 'Basic salary is above RM ' + limit.toLocaleString() + ', so the Employment Act overtime rules don\'t apply. Tick only if you pay OT anyway.' : 'Employment Act overtime applies (basic salary RM ' + limit.toLocaleString() + ' or less).'"></span>
                    </span>
                </label>
            </div>

            <div class="k-card p-5 md:p-6">
                <h3 class="text-base font-bold text-gray-900 mb-4">Personal and statutory</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div><label class="text-xs text-gray-500">IC number</label><input type="text" name="ic_number" value="{{ $v('ic_number', $employee->ic_number) }}" placeholder="900101-10-1234" class="{{ $field }}"></div>
                    <div><label class="text-xs text-gray-500">Date of birth</label><input type="date" name="date_of_birth" value="{{ $v('date_of_birth', $employee->date_of_birth?->toDateString()) }}" class="{{ $field }}"></div>
                    <div><label class="text-xs text-gray-500">Gender</label>
                        <select name="gender" class="{{ $field }}"><option value=""></option>
                            <option value="male" @selected($v('gender', $employee->gender) === 'male')>Male</option>
                            <option value="female" @selected($v('gender', $employee->gender) === 'female')>Female</option>
                        </select></div>
                    <div><label class="text-xs text-gray-500">Phone</label><input type="text" name="phone" value="{{ $v('phone', $employee->phone) }}" class="{{ $field }}"></div>
                    <div class="sm:col-span-2"><label class="text-xs text-gray-500">Personal email</label><input type="email" name="personal_email" value="{{ $v('personal_email', $employee->personal_email) }}" class="{{ $field }}"></div>
                    <div class="sm:col-span-3"><label class="text-xs text-gray-500">Home address</label><textarea name="address" rows="2" class="{{ $field }}">{{ $v('address', $employee->address) }}</textarea></div>
                    <div><label class="text-xs text-gray-500">EPF (KWSP) no.</label><input type="text" name="epf_number" value="{{ $v('epf_number', $employee->epf_number) }}" class="{{ $field }}"></div>
                    <div><label class="text-xs text-gray-500">SOCSO (PERKESO) no.</label><input type="text" name="socso_number" value="{{ $v('socso_number', $employee->socso_number) }}" class="{{ $field }}"></div>
                    <div><label class="text-xs text-gray-500">Income tax no.</label><input type="text" name="tax_number" value="{{ $v('tax_number', $employee->tax_number) }}" class="{{ $field }}"></div>
                    <div><label class="text-xs text-gray-500">Bank</label><input type="text" name="bank_name" value="{{ $v('bank_name', $employee->bank_name) }}" placeholder="Maybank" class="{{ $field }}"></div>
                    <div class="sm:col-span-2"><label class="text-xs text-gray-500">Bank account no.</label><input type="text" name="bank_account" value="{{ $v('bank_account', $employee->bank_account) }}" class="{{ $field }}"></div>
                    <div><label class="text-xs text-gray-500">Emergency contact</label><input type="text" name="emergency_name" value="{{ $v('emergency_name', $employee->emergency_name) }}" class="{{ $field }}"></div>
                    <div><label class="text-xs text-gray-500">Relationship</label><input type="text" name="emergency_relation" value="{{ $v('emergency_relation', $employee->emergency_relation) }}" class="{{ $field }}"></div>
                    <div><label class="text-xs text-gray-500">Emergency phone</label><input type="text" name="emergency_phone" value="{{ $v('emergency_phone', $employee->emergency_phone) }}" class="{{ $field }}"></div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="text-sm font-semibold px-5 py-2.5 rounded-xl text-white bg-gradient-to-r from-[#6D28D9] to-[#A855F7] hover:brightness-110">{{ $isNew ? 'Add staff and create login' : 'Save changes' }}</button>
                <a href="{{ route('hr.staff.index') }}" class="text-sm text-gray-500 hover:underline">Cancel</a>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        function staffForm(init) {
            return {
                ...init, touched: !!init.email,
                async suggest() {
                    if (!this.name.trim()) return;
                    const params = new URLSearchParams({ name: this.name, ignore: this.userId || '' });
                    const res = await fetch(`{{ route('hr.staff.suggest-email') }}?${params}`, { headers: { Accept: 'application/json' } });
                    if (res.ok) { const d = await res.json(); if (d.email) { this.email = d.email; this.touched = false; } }
                },
                autoSuggest() { if (!this.touched) this.suggest(); },
            };
        }
    </script>
    @endpush
</x-app-layout>
