<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-3" x-data>
            <div>
                <h2 class="font-semibold text-xl text-white leading-tight">Settings</h2>
                <p class="text-xs text-white/60 mt-0.5">User Management</p>
            </div>
            <button type="button" @click="$store.settingsUi.showAdd = !$store.settingsUi.showAdd" class="inline-flex items-center gap-1.5 px-4 py-2 bg-white/15 hover:bg-white/25 text-white text-xs font-semibold rounded-md">
                <span class="text-sm leading-none">+</span> Add User
            </button>
        </div>
    </x-slot>

    <div class="p-6 space-y-4" x-data x-init="if ({{ $errors->any() ? 'true' : 'false' }}) $store.settingsUi.showAdd = true">

        @if (session('success'))
            <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif

        {{-- Stat cards --}}
        @php
            $cards = [
                ['label' => 'Total Active', 'value' => $stats['total_active'], 'sub' => "{$stats['total']} total", 'color' => '#E91E63'],
                ['label' => 'BOD', 'value' => $stats['bod'], 'sub' => 'Full access', 'color' => config('kretivco.roles.bod.color')],
                ['label' => 'Dept Head', 'value' => $stats['dept_head'], 'sub' => 'Dept access', 'color' => config('kretivco.roles.dept_head.color')],
                ['label' => 'Staff', 'value' => $stats['staff'], 'sub' => 'Limited', 'color' => config('kretivco.roles.staff.color')],
                ['label' => 'Intern', 'value' => $stats['intern'], 'sub' => 'Limited', 'color' => config('kretivco.roles.intern.color')],
            ];
        @endphp
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            @foreach ($cards as $card)
                <div class="bg-white rounded-xl shadow-sm p-4 border-l-4" style="border-color: {{ $card['color'] }}">
                    <div class="text-xs font-medium text-gray-400 uppercase tracking-wide">{{ $card['label'] }}</div>
                    <div class="text-2xl font-bold mt-1">{{ $card['value'] }}</div>
                    <div class="text-xs text-gray-500 mt-0.5">{{ $card['sub'] }}</div>
                </div>
            @endforeach
        </div>

        {{-- Add User panel --}}
        @can('create', \App\Models\User::class)
        <div class="bg-white shadow-sm sm:rounded-lg p-6" x-show="$store.settingsUi.showAdd" x-cloak>
            <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Add User</h3>
            <form method="POST" action="{{ route('settings.users.store') }}"
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
                        @foreach (config('kretivco.roles') as $key => $roleOpt)
                            <label class="flex items-center gap-2 rounded-md border px-3 py-2 text-sm cursor-pointer"
                                   :class="role === '{{ $key }}' ? 'border-2' : 'border-gray-200'"
                                   :style="role === '{{ $key }}' ? 'border-color: {{ $roleOpt['color'] }}' : ''">
                                <input type="radio" name="role" value="{{ $key }}" x-model="role" class="text-xs">
                                {{ $roleOpt['label'] }}
                            </label>
                        @endforeach
                    </div>
                </div>
                <div class="sm:col-span-2" x-show="role !== 'bod'" x-cloak>
                    <x-input-label value="Department *" />
                    <div class="mt-1 flex flex-wrap gap-2">
                        @foreach (config('kretivco.departments') as $key => $dept)
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
                        @foreach (config('kretivco.departments') as $key => $dept)
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
                    <x-primary-button type="submit">Add User</x-primary-button>
                </div>
            </form>
        </div>
        @endcan

        {{-- Filters --}}
        <form method="GET" action="{{ route('settings.index') }}" class="bg-white rounded-xl shadow-sm p-4 flex flex-wrap gap-3 items-center">
            <div class="relative flex-1 min-w-[220px]">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </span>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by name or email..." class="w-full pl-9 rounded-md border-gray-300 shadow-sm text-sm">
            </div>
            <select name="role" class="rounded-md border-gray-300 shadow-sm text-sm">
                <option value="">All Roles</option>
                @foreach (config('kretivco.roles') as $key => $roleOpt)
                    <option value="{{ $key }}" {{ $role === $key ? 'selected' : '' }}>{{ $roleOpt['label'] }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                <input type="checkbox" name="show_inactive" value="1" {{ $showInactive ? 'checked' : '' }} onchange="this.form.submit()">
                Show inactive
            </label>
            <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-md bg-gray-800 text-white hover:bg-gray-900">Filter</button>
            @if ($search || $role || $showInactive)
                <a href="{{ route('settings.index') }}" class="text-xs text-gray-500 hover:underline">Reset</a>
            @endif
        </form>

        {{-- User table --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden" x-data="{ editingId: null }">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-left text-xs text-gray-500 uppercase">
                            <th class="px-4 py-3 whitespace-nowrap">Staff ID</th>
                            <th class="px-4 py-3 whitespace-nowrap">Role</th>
                            <th class="px-4 py-3 whitespace-nowrap">Name</th>
                            <th class="px-4 py-3 whitespace-nowrap">Email</th>
                            <th class="px-4 py-3 whitespace-nowrap">Department</th>
                            <th class="px-4 py-3 whitespace-nowrap">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($users as $user)
                            @php
                                $roleMeta = config('kretivco.roles')[$user->role] ?? null;
                                $avatarColors = ['#E91E63', '#7209B7', '#3A86FF', '#E85D04', '#10B981', '#F59E0B', '#6366F1'];
                                $avatarColor = $avatarColors[ord($user->name[0] ?? 'A') % count($avatarColors)];
                            @endphp
                            <tr :class="editingId === {{ $user->id }} ? 'bg-gray-50' : ''" class="{{ $user->active ? '' : 'opacity-50' }}">
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0" style="background: {{ $avatarColor }}18; color: {{ $avatarColor }}">
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        </div>
                                        <span class="font-mono text-xs text-gray-500">{{ $user->staff_id ?? '—' }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                          style="background: {{ $roleMeta['color'] ?? '#eee' }}22; color: {{ $roleMeta['color'] ?? '#666' }}">
                                        {{ $roleMeta['label'] ?? $user->role }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-gray-800 font-semibold">{{ $user->name }}</div>
                                    <div class="text-xs text-gray-400">Since {{ $user->created_at?->format('d M Y') ?? '—' }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $user->email }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if (empty($user->visible_departments) || count($user->visible_departments) === count(config('kretivco.departments')))
                                        <span class="text-gray-500 text-xs">{{ $user->department ? config('kretivco.departments')[$user->department]['label'] ?? $user->department : 'All' }}</span>
                                    @else
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($user->visible_departments as $d)
                                                <span class="text-xs rounded px-1.5 py-0.5 bg-gray-100 text-gray-600">{{ config('kretivco.departments')[$d]['label'] ?? $d }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex items-center text-xs" style="color: {{ $user->active ? '#10B981' : '#EF4444' }}">
                                        <span class="inline-block w-2 h-2 rounded-full mr-1.5" style="background: {{ $user->active ? '#10B981' : '#EF4444' }}"></span>
                                        {{ $user->active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button type="button" @click="editingId = editingId === {{ $user->id }} ? null : {{ $user->id }}" class="text-xs font-semibold px-3 py-1.5 rounded-md border border-gray-200 hover:bg-gray-50">
                                        Edit
                                    </button>
                                </td>
                            </tr>
                            <tr x-show="editingId === {{ $user->id }}" x-cloak>
                                <td colspan="7" class="px-4 py-4 bg-gray-50">
                                    <form method="POST" action="{{ route('settings.users.update', $user) }}"
                                          x-data="{ role: '{{ $user->role }}' }"
                                          class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                                        @csrf
                                        @method('PUT')
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
                                                @foreach (config('kretivco.roles') as $key => $r)
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
                                                @foreach (config('kretivco.departments') as $key => $dept)
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
                                                @foreach (config('kretivco.departments') as $key => $dept)
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
                                        <div class="sm:col-span-2 flex items-center gap-3">
                                            <x-primary-button type="submit">Save</x-primary-button>
                                            <button type="button" @click="editingId = null" class="text-xs text-gray-500 hover:underline">Cancel</button>
                                        </div>
                                    </form>
                                    <div class="mt-3 flex items-center gap-2">
                                        <form method="POST" action="{{ route('settings.users.toggle-active', $user) }}">
                                            @csrf
                                            <button type="submit" class="text-xs font-semibold px-3 py-1.5 rounded-md border {{ $user->active ? 'border-red-200 text-red-600 hover:bg-red-50' : 'border-green-200 text-green-600 hover:bg-green-50' }}">
                                                {{ $user->active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('settings.users.reset-password', $user) }}"
                                              onsubmit="return confirm('Reset password untuk {{ $user->name }}? Password baru akan dipaparkan sekali sahaja selepas ini.');">
                                            @csrf
                                            <button type="submit" class="text-xs font-semibold px-3 py-1.5 rounded-md border border-amber-200 text-amber-600 hover:bg-amber-50">
                                                Reset Password
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">No users.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="text-xs text-gray-400 px-1">{{ $users->count() }} user</div>

        {{-- Access Reference --}}
        @php
            $roleKeys = array_keys(config('kretivco.roles'));
            $caps = [
                ['View all departments', ['bod']],
                ['View own department(s)', $roleKeys],
                ['Create job', $roleKeys],
                ['Edit job', $roleKeys],
                ['Change status', $roleKeys],
                ['Archive/Close Ticket', $roleKeys],
                ['Manage customers/vendors', ['bod', 'dept_head']],
                ['Reports & Finance', ['bod', 'dept_head']],
                ['Export', ['bod', 'dept_head']],
                ['User Management', ['bod']],
                ['Settings', ['bod']],
            ];
        @endphp
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h3 class="text-sm font-bold text-gray-800 mb-4">Access Reference</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-xs text-gray-500 uppercase">
                            <th class="text-left px-3 py-2.5 border-b border-gray-100">Capability</th>
                            @foreach (config('kretivco.roles') as $key => $roleOpt)
                                <th class="px-3 py-2.5 border-b border-gray-100 text-center font-semibold" style="color: {{ $roleOpt['color'] }}">{{ $roleOpt['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($caps as [$label, $allowed])
                            <tr>
                                <td class="px-3 py-2.5 border-b border-gray-50">{{ $label }}</td>
                                @foreach ($roleKeys as $key)
                                    <td class="px-3 py-2.5 border-b border-gray-50 text-center">
                                        @if (in_array($key, $allowed, true))
                                            <span class="text-green-500">&check;</span>
                                        @else
                                            <span class="text-gray-200">&mdash;</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @can('create', \App\Models\User::class)
        {{-- Dev tools: destructive resets, BOD-only --}}
        <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-amber-400" x-data="{ open: false, input: '' }">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <div class="text-sm font-bold text-amber-500">Reset Jobs</div>
                    <p class="text-sm text-gray-500 mt-1">Deletes all jobs & activity logs. Customers, users & other data remain unchanged. Dev phase only.</p>
                </div>
                <button type="button" @click="open = !open" class="text-xs font-semibold px-4 py-2 rounded-md bg-amber-50 text-amber-600 border border-amber-200 hover:bg-amber-100">Reset Jobs</button>
            </div>
            <form method="POST" action="{{ route('settings.reset-jobs') }}" x-show="open" x-cloak class="mt-4 flex flex-wrap items-end gap-2 p-3 rounded-md bg-amber-50/50 border border-amber-100">
                @csrf
                <div>
                    <label class="text-xs text-gray-500">Type "RESET" to confirm</label>
                    <input type="text" name="confirm" x-model="input" placeholder="RESET" class="block rounded-md border-gray-300 shadow-sm text-sm">
                </div>
                <button type="submit" :disabled="input !== 'RESET'" :class="input === 'RESET' ? 'bg-amber-500 text-white hover:bg-amber-600' : 'bg-gray-200 text-gray-400 cursor-not-allowed'" class="text-xs font-semibold px-3 py-2 rounded-md">Confirm Reset Jobs</button>
                <button type="button" @click="open = false; input = ''" class="text-xs text-gray-500 hover:underline">Cancel</button>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm p-6 border-l-4 border-red-500" x-data="{ open: false, input: '' }">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <div class="text-sm font-bold text-red-500">Reset All Data</div>
                    <p class="text-sm text-gray-500 mt-1">Deletes all jobs, customers & activity logs. Users remain unchanged. Dev phase only.</p>
                </div>
                <button type="button" @click="open = !open" class="text-xs font-semibold px-4 py-2 rounded-md bg-red-50 text-red-600 border border-red-200 hover:bg-red-100">Reset Data</button>
            </div>
            <form method="POST" action="{{ route('settings.reset-all-data') }}" x-show="open" x-cloak class="mt-4 flex flex-wrap items-end gap-2 p-3 rounded-md bg-red-50/50 border border-red-100">
                @csrf
                <div>
                    <label class="text-xs text-gray-500">Type "RESET" to confirm</label>
                    <input type="text" name="confirm" x-model="input" placeholder="RESET" class="block rounded-md border-gray-300 shadow-sm text-sm">
                </div>
                <button type="submit" :disabled="input !== 'RESET'" :class="input === 'RESET' ? 'bg-red-600 text-white hover:bg-red-700' : 'bg-gray-200 text-gray-400 cursor-not-allowed'" class="text-xs font-semibold px-3 py-2 rounded-md">Confirm Reset All Data</button>
                <button type="button" @click="open = false; input = ''" class="text-xs text-gray-500 hover:underline">Cancel</button>
            </form>
        </div>
        @endcan
    </div>
</x-app-layout>
