<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between flex-wrap gap-3" x-data>
            <h2 class="font-bold text-2xl text-white leading-tight">Users &amp; Access</h2>
            {{-- New staff are added once, in HR (login, profile and package together). --}}
            <a href="{{ route('hr.staff.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-white text-[#C2185B] hover:bg-[#FFF1EC] text-sm font-semibold rounded-xl shadow-sm">
                <x-icon name="plus" class="w-4 h-4" /> Add staff in HR
            </a>
        </div>
    </x-slot>

    <div class="p-5 md:p-7 space-y-4" x-data x-init="if ({{ $errors->any() ? 'true' : 'false' }}) $store.settingsUi.showAdd = true">

        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
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
                <div class="k-card p-4 flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0" style="background: {{ $card['color'] }}18; color: {{ $card['color'] }}"><x-icon name="users" class="w-5 h-5" /></span>
                    <div class="min-w-0">
                        <div class="text-xl font-extrabold text-gray-900 leading-none">{{ $card['value'] }}</div>
                        <div class="text-xs font-semibold text-gray-600 mt-1 truncate">{{ $card['label'] }}</div>
                        <div class="text-[11px] text-gray-400 truncate">{{ $card['sub'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>


        {{-- Filters --}}
        <form method="GET" action="{{ route('settings.index') }}" class="k-card p-4 flex flex-wrap gap-3 items-center">
            <div class="relative flex-1 min-w-[220px]">
                <x-icon name="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                <input type="text" name="search" value="{{ $search }}" placeholder="Search by name or email..." class="w-full pl-10 text-sm">
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
            <button type="submit" class="text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110">Filter</button>
            @if ($search || $role || $showInactive)
                <a href="{{ route('settings.index') }}" class="text-xs text-gray-500 hover:underline">Reset</a>
            @endif
        </form>

        {{-- User table --}}
        <div class="k-card overflow-hidden" x-data="{ editingId: null }">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-[#F5ECE8]">
                            <th class="px-4 py-3">Staff</th>
                            <th class="px-4 py-3 whitespace-nowrap">Role</th>
                            <th class="px-4 py-3 whitespace-nowrap hidden md:table-cell">Department</th>
                            <th class="px-4 py-3 whitespace-nowrap hidden sm:table-cell">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#F5ECE8]">
                        @forelse ($users as $user)
                            @php
                                $roleMeta = config('kretivco.roles')[$user->role] ?? null;
                                $avatarColors = ['#E91E63', '#7209B7', '#3A86FF', '#E85D04', '#10B981', '#F59E0B', '#6366F1'];
                                $avatarColor = $avatarColors[ord($user->name[0] ?? 'A') % count($avatarColors)];
                            @endphp
                            <tr :class="editingId === {{ $user->id }} ? 'bg-[#FFF9F6]' : ''" class="hover:bg-[#FFF7F3] transition-colors {{ $user->active ? '' : 'opacity-50' }}">
                                <td class="px-4 py-3 max-w-0 w-full">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-9 h-9 rounded-xl flex items-center justify-center text-xs font-bold shrink-0" style="background: {{ $avatarColor }}18; color: {{ $avatarColor }}">
                                            {{ strtoupper(substr($user->name, 0, 1)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-gray-800 font-semibold truncate">{{ $user->name }}</div>
                                            <div class="text-xs text-gray-400 truncate">{{ $user->email }}@if ($user->staff_id) · <span class="font-mono">{{ $user->staff_id }}</span>@endif</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                                          style="background: {{ $roleMeta['color'] ?? '#eee' }}22; color: {{ $roleMeta['color'] ?? '#666' }}">
                                        {{ $roleMeta['label'] ?? $user->role }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap hidden md:table-cell">
                                    @if (empty($user->visible_departments) || count($user->visible_departments) === count(config('kretivco.departments')))
                                        <span class="text-gray-500 text-xs">{{ $user->department ? config('kretivco.departments')[$user->department]['label'] ?? $user->department : 'All' }}</span>
                                    @else
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($user->visible_departments as $d)
                                                <span class="text-xs rounded-full px-2 py-0.5 bg-gray-100 text-gray-600">{{ config('kretivco.departments')[$d]['label'] ?? $d }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap hidden sm:table-cell">
                                    <span class="inline-flex items-center text-xs" style="color: {{ $user->active ? '#10B981' : '#EF4444' }}">
                                        <span class="inline-block w-2 h-2 rounded-full mr-1.5" style="background: {{ $user->active ? '#10B981' : '#EF4444' }}"></span>
                                        {{ $user->active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button type="button" @click="editingId = editingId === {{ $user->id }} ? null : {{ $user->id }}" class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1.5 rounded-lg border border-[#EFE3DE] text-gray-700 bg-white hover:bg-[#FFF7F3]">
                                        <x-icon name="pencil" class="w-3.5 h-3.5" /> Edit
                                    </button>
                                </td>
                            </tr>
                            <tr x-show="editingId === {{ $user->id }}" x-cloak>
                                <td colspan="5" class="px-4 py-4 bg-[#FFF9F6]">
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
                                                    <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 text-sm cursor-pointer">
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
                                                    <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 text-sm cursor-pointer">
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
                                                    <label class="flex items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 text-sm cursor-pointer">
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
                                        <div class="sm:col-span-2" x-show="role !== 'bod'" x-cloak>
                                            <x-input-label value="Modules" />
                                            <input type="hidden" name="modules_present" value="1">
                                            <div class="mt-1 flex flex-wrap gap-2">
                                                @foreach (\App\Models\User::MODULES as $module)
                                                    <label class="flex items-center gap-2 rounded-xl border border-[#EFE3DE] px-3 py-2 text-sm cursor-pointer">
                                                        <input type="checkbox" name="modules[]" value="{{ $module }}" @checked($user->canAccess($module))>
                                                        {{ ['jobs' => 'Jobs', 'finance' => 'Finance', 'hr' => 'HR'][$module] ?? ucfirst($module) }}
                                                    </label>
                                                @endforeach
                                            </div>
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
                                              onsubmit="return confirm('Reset the password for {{ addslashes($user->name) }}? The new temporary password is shown only once.');">
                                            @csrf
                                            <button type="submit" class="text-xs font-semibold px-3 py-1.5 rounded-md border border-amber-200 text-amber-600 hover:bg-amber-50">
                                                Reset Password
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">No users found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="text-xs text-gray-500 px-1">{{ $users->count() }} {{ \Illuminate\Support\Str::plural('user', $users->count()) }}</div>

        {{-- Access Reference: generated from the real permission checks (App\Support\AccessMatrix). --}}
        @php $roleKeys = array_keys(config('kretivco.roles')); @endphp
        <div class="k-card p-5 md:p-6">
            <h3 class="text-base font-bold text-gray-900 mb-4">Access Reference</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">
                            <th class="text-left px-3 py-2.5 border-b border-[#F5ECE8]">Capability</th>
                            @foreach (config('kretivco.roles') as $key => $roleOpt)
                                <th class="px-3 py-2.5 border-b border-[#F5ECE8] text-center font-semibold" style="color: {{ $roleOpt['color'] }}">{{ $roleOpt['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (\App\Support\AccessMatrix::rows() as [$label, $allowed])
                            <tr>
                                <td class="px-3 py-2.5 border-b border-[#FBF3EF]">{{ $label }}</td>
                                @foreach ($roleKeys as $key)
                                    <td class="px-3 py-2.5 border-b border-[#FBF3EF] text-center">
                                        @if ($allowed[$key] ?? false)
                                            <x-icon name="check" class="w-4 h-4 text-green-500 inline" :stroke="3" />
                                        @else
                                            <x-icon name="x" class="w-4 h-4 text-gray-200 inline" />
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
        <div class="k-card p-5 md:p-6 !border-amber-200" x-data="{ open: false, input: '' }">
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

        <div class="k-card p-5 md:p-6 !border-red-200" x-data="{ open: false, input: '' }">
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
