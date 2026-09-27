<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Vendors</h2>
    </x-slot>

    <div class="p-5 md:p-7">
        <div class="space-y-4">

            @if (session('success'))
                <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">
                    {{ session('success') }}
                </div>
            @endif

            <div class="k-card p-4">
                <form method="GET" action="{{ route('vendors.index') }}" class="relative">
                    <x-icon name="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                    <input type="text" name="q" value="{{ $search }}" placeholder="Search by ID, name, or company..." class="w-full pl-10 text-sm">
                </form>
            </div>

            <div class="k-card p-5" x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }">
                <button type="button" @click="open = !open" class="inline-flex items-center gap-2 text-sm font-semibold text-[#C2185B] hover:text-[#AD1457]">
                    <span class="w-7 h-7 rounded-lg bg-[#FFF1EC] flex items-center justify-center">
                        <x-icon name="plus" class="w-4 h-4" x-show="!open" />
                        <x-icon name="x" class="w-4 h-4" x-show="open" x-cloak />
                    </span>
                    <span x-show="!open">New vendor</span>
                    <span x-show="open" x-cloak>Close form</span>
                </button>
                <form method="POST" action="{{ route('vendors.store') }}" x-show="open" x-cloak class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                    @csrf
                    <div>
                        <x-input-label for="name" value="Name *" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required />
                    </div>
                    <div>
                        <x-input-label for="company" value="Company" />
                        <x-text-input id="company" name="company" type="text" class="mt-1 block w-full" :value="old('company')" />
                    </div>
                    <div>
                        <x-input-label value="Category *" />
                        <select name="category" class="mt-1 block w-full text-sm">
                            @foreach (config('kretivco.vendor_categories') as $key => $label)
                                <option value="{{ $key }}" {{ old('category') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="phone" value="Phone" />
                        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone')" />
                    </div>
                    <div>
                        <x-input-label for="email" value="Email" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" />
                    </div>
                    <div></div>
                    <div>
                        <x-input-label for="bank_name" value="Bank Name" />
                        <x-text-input id="bank_name" name="bank_name" type="text" class="mt-1 block w-full" :value="old('bank_name')" />
                    </div>
                    <div>
                        <x-input-label for="bank_account" value="Bank Account" />
                        <x-text-input id="bank_account" name="bank_account" type="text" class="mt-1 block w-full" :value="old('bank_account')" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="address" value="Address" />
                        <x-text-input id="address" name="address" type="text" class="mt-1 block w-full" :value="old('address')" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="notes" value="Notes" />
                        <textarea name="notes" rows="2" class="mt-1 block w-full text-sm">{{ old('notes') }}</textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-error :messages="$errors->all()" class="mt-1" />
                        <x-primary-button type="submit">Save vendor</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="k-card overflow-hidden" x-data="{ editingId: null }">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-[#F5ECE8]">
                                <th class="px-4 py-3">Vendor</th>
                                <th class="px-4 py-3 whitespace-nowrap hidden sm:table-cell">Category</th>
                                <th class="px-4 py-3 whitespace-nowrap hidden md:table-cell">Contact</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#F5ECE8]">
                            @forelse ($vendors as $vendor)
                                @php $category = config('kretivco.vendor_categories')[$vendor->category] ?? $vendor->category; @endphp
                                <tr class="hover:bg-[#FFF7F3] transition-colors">
                                    <td class="px-4 py-3.5 max-w-0 w-full">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-[#FFF4E5] text-[#E85D04] shrink-0"><x-icon name="truck" class="w-4 h-4" /></span>
                                            <div class="min-w-0">
                                                <div class="font-semibold text-gray-800 truncate">{{ $vendor->name }}</div>
                                                <div class="text-xs text-gray-400 truncate"><span class="font-mono">{{ $vendor->vendor_id }}</span>{{ $vendor->company ? ' · '.$vendor->company : '' }}<span class="sm:hidden"> · {{ $category }}</span></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap hidden sm:table-cell">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold bg-[#FFF4E5] text-[#B45309]">{{ $category }}</span>
                                    </td>
                                    <td class="px-4 py-3.5 whitespace-nowrap hidden md:table-cell">
                                        <div class="text-gray-700">{{ $vendor->phone ?: 'No phone' }}</div>
                                        @if ($vendor->email)
                                            <div class="text-xs text-gray-400">{{ $vendor->email }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                        @can('update', $vendor)
                                            <button type="button" @click="editingId = editingId === {{ $vendor->id }} ? null : {{ $vendor->id }}" class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1.5 rounded-lg border border-[#EFE3DE] text-gray-700 bg-white hover:bg-[#FFF7F3]"><x-icon name="pencil" class="w-3.5 h-3.5" /> Edit</button>
                                        @endcan
                                    </td>
                                </tr>
                                @can('update', $vendor)
                                <tr x-show="editingId === {{ $vendor->id }}" x-cloak>
                                    <td colspan="4" class="px-4 py-4 bg-[#FFF9F6]">
                                        <form method="POST" action="{{ route('vendors.update', $vendor) }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-start">
                                            @csrf
                                            @method('PUT')
                                            <x-text-input name="name" type="text" class="block w-full" :value="$vendor->name" required placeholder="Name" />
                                            <x-text-input name="phone" type="text" class="block w-full" :value="$vendor->phone" placeholder="Phone" />
                                            <x-text-input name="email" type="email" class="block w-full" :value="$vendor->email" placeholder="Email" />
                                            <input type="hidden" name="category" value="{{ $vendor->category }}">
                                            <div class="sm:col-span-3">
                                                <x-primary-button type="submit">Save</x-primary-button>
                                                <button type="button" @click="editingId = null" class="ml-2 text-xs text-gray-500 hover:underline">Cancel</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                                @endcan
                            @empty
                                <tr><td colspan="4" class="px-4 py-10 text-center text-gray-400">No vendors found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
