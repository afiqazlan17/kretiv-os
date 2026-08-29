<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Staff</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Add Staff</h3>
                <form method="POST" action="{{ route('staff.store') }}"
                      x-data="{ role: '{{ old('role', 'staff') }}' }"
                      class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                    @csrf
                    <div>
                        <x-input-label for="name" value="Full Name *" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required />
                    </div>
                    <div>
                        <x-input-label for="email" value="Email *" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" required />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label value="Role *" />
                        <div class="mt-1 flex flex-wrap gap-2">
                            @foreach (config('kretivos.roles') as $key => $role)
                                <label class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm cursor-pointer"
                                       :class="role === '{{ $key }}' ? 'border-2' : 'border-gray-200'"
                                       :style="role === '{{ $key }}' ? 'border-color: {{ $role['color'] }}' : ''">
                                    <input type="radio" name="role" value="{{ $key }}" x-model="role" class="text-xs">
                                    {{ $role['label'] }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="sm:col-span-2" x-show="role !== 'bod'" x-cloak>
                        <x-input-label value="Department *" />
                        <div class="mt-1 flex flex-wrap gap-2">
                            @foreach (config('kretivos.departments') as $key => $dept)
                                <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm cursor-pointer">
                                    <input type="radio" name="department" value="{{ $key }}" required>
                                    {{ $dept['label'] }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div class="sm:col-span-2" x-show="role !== 'bod'" x-cloak>
                        <x-input-label value="Visible Departments" />
                        <p class="text-xs text-gray-400 mb-1">Leave empty for their own department only.</p>
                        <div class="mt-1 flex flex-wrap gap-2">
                            @foreach (config('kretivos.departments') as $key => $dept)
                                <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm cursor-pointer">
                                    <input type="checkbox" name="visible_departments[]" value="{{ $key }}">
                                    {{ $dept['label'] }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <x-input-label for="title" value="Title (optional)" />
                        <x-text-input id="title" name="title" type="text" class="mt-1 block w-full" :value="old('title')" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-error :messages="$errors->all()" class="mt-1" />
                        <x-primary-button type="submit">Add Staff</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden" x-data="{ editingId: null }">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="px-4 py-3 whitespace-nowrap">Staff ID</th>
                                <th class="px-4 py-3 whitespace-nowrap">Name</th>
                                <th class="px-4 py-3 whitespace-nowrap">Role</th>
                                <th class="px-4 py-3 whitespace-nowrap">Department</th>
                                <th class="px-4 py-3 whitespace-nowrap">Employment</th>
                                <th class="px-4 py-3 whitespace-nowrap">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($users as $user)
                                @php $role = config('kretivos.roles')[$user->role] ?? null; $profile = $profiles[$user->id] ?? null; @endphp
                                <tr>
                                    <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">{{ $user->staff_id ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-800 whitespace-nowrap">{{ $user->name }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                              style="background: {{ $role['color'] ?? '#eee' }}22; color: {{ $role['color'] ?? '#666' }}">
                                            {{ $role['label'] ?? $user->role }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-500 text-xs">{{ $user->department ? config('kretivos.departments')[$user->department]['label'] ?? $user->department : 'All' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-500 text-xs">{{ $profile ? config('hr.employment_types')[$profile->employment_type] ?? $profile->employment_type : '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs {{ $user->active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                            {{ $user->active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <button type="button" @click="editingId = editingId === {{ $user->id }} ? null : {{ $user->id }}" class="text-indigo-600 hover:underline text-xs">
                                            Edit
                                        </button>
                                    </td>
                                </tr>
                                <tr x-show="editingId === {{ $user->id }}" x-cloak>
                                    <td colspan="7" class="px-4 py-4 bg-gray-50 space-y-4">
                                        <form method="POST" action="{{ route('staff.update', $user) }}"
                                              x-data="{ role: '{{ $user->role }}' }"
                                              class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                                            @csrf
                                            @method('PUT')
                                            <div class="sm:col-span-2 text-xs font-semibold text-gray-400 uppercase">Account</div>
                                            <div>
                                                <x-input-label value="Full Name *" />
                                                <x-text-input name="name" type="text" class="mt-1 block w-full" :value="$user->name" required />
                                            </div>
                                            <div>
                                                <x-input-label value="Email *" />
                                                <x-text-input name="email" type="email" class="mt-1 block w-full" :value="$user->email" required />
                                            </div>
                                            <div class="sm:col-span-2">
                                                <x-input-label value="Role *" />
                                                <div class="mt-1 flex flex-wrap gap-2">
                                                    @foreach (config('kretivos.roles') as $key => $r)
                                                        <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm cursor-pointer">
                                                            <input type="radio" name="role" value="{{ $key }}" x-model="role" {{ $user->role === $key ? 'checked' : '' }}>
                                                            {{ $r['label'] }}
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                            <div class="sm:col-span-2" x-show="role !== 'bod'" x-cloak>
                                                <x-input-label value="Department *" />
                                                <div class="mt-1 flex flex-wrap gap-2">
                                                    @foreach (config('kretivos.departments') as $key => $dept)
                                                        <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm cursor-pointer">
                                                            <input type="radio" name="department" value="{{ $key }}" {{ $user->department === $key ? 'checked' : '' }}>
                                                            {{ $dept['label'] }}
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                            <div class="sm:col-span-2" x-show="role !== 'bod'" x-cloak>
                                                <x-input-label value="Visible Departments" />
                                                <div class="mt-1 flex flex-wrap gap-2">
                                                    @foreach (config('kretivos.departments') as $key => $dept)
                                                        <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm cursor-pointer">
                                                            <input type="checkbox" name="visible_departments[]" value="{{ $key }}"
                                                                   {{ in_array($key, $user->visible_departments ?? []) ? 'checked' : '' }}>
                                                            {{ $dept['label'] }}
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                            <div>
                                                <x-input-label value="Title" />
                                                <x-text-input name="title" type="text" class="mt-1 block w-full" :value="$user->title" />
                                            </div>
                                            <div class="sm:col-span-2">
                                                <x-primary-button type="submit">Save Account</x-primary-button>
                                            </div>
                                        </form>

                                        <form method="POST" action="{{ route('staff.toggle-active', $user) }}">
                                            @csrf
                                            <button type="submit" class="text-xs {{ $user->active ? 'text-red-600' : 'text-green-600' }} hover:underline">
                                                {{ $user->active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('staff.profile.update', $user) }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-start border-t border-gray-200 pt-4">
                                            @csrf
                                            @method('PUT')
                                            <div class="sm:col-span-3 text-xs font-semibold text-gray-400 uppercase">Employment Profile</div>
                                            <div>
                                                <x-input-label value="IC Number" />
                                                <x-text-input name="ic_number" type="text" class="mt-1 block w-full" :value="$profile?->ic_number" />
                                            </div>
                                            <div>
                                                <x-input-label value="Join Date" />
                                                <x-text-input name="join_date" type="date" class="mt-1 block w-full" :value="$profile?->join_date?->toDateString()" />
                                            </div>
                                            <div>
                                                <x-input-label value="Employment Type *" />
                                                <select name="employment_type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                                    @foreach (config('hr.employment_types') as $key => $label)
                                                        <option value="{{ $key }}" {{ $profile?->employment_type === $key ? 'selected' : '' }}>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <x-input-label value="Emergency Contact Name" />
                                                <x-text-input name="emergency_contact_name" type="text" class="mt-1 block w-full" :value="$profile?->emergency_contact_name" />
                                            </div>
                                            <div>
                                                <x-input-label value="Emergency Contact Phone" />
                                                <x-text-input name="emergency_contact_phone" type="text" class="mt-1 block w-full" :value="$profile?->emergency_contact_phone" />
                                            </div>
                                            <div>
                                                <x-input-label value="Status *" />
                                                <select name="status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                                    <option value="active" {{ ($profile?->status ?? 'active') === 'active' ? 'selected' : '' }}>Active</option>
                                                    <option value="inactive" {{ $profile?->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                                                </select>
                                            </div>
                                            <div>
                                                <x-input-label value="Bank" />
                                                <select name="bank" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                                    <option value="">—</option>
                                                    @foreach (config('jobs.banks') as $key => $bank)
                                                        <option value="{{ $key }}" {{ $profile?->bank === $key ? 'selected' : '' }}>{{ $bank['label'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <x-input-label value="Bank Account Number" />
                                                <x-text-input name="bank_account_number" type="text" class="mt-1 block w-full" :value="$profile?->bank_account_number" />
                                            </div>
                                            <div>
                                                <x-input-label value="Basic Salary (RM)" />
                                                <x-text-input name="basic_salary" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="$profile?->basic_salary" />
                                            </div>
                                            <div class="sm:col-span-3">
                                                <x-primary-button type="submit">Save Employment Profile</x-primary-button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
