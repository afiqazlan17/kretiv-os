<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Vendors</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6" x-data="{ open: false }">
                <button type="button" @click="open = !open" class="text-sm font-semibold text-indigo-600 hover:underline">
                    <span x-show="!open">+ New Vendor</span>
                    <span x-show="open" x-cloak>− Close Form</span>
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
                        <select name="category" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            @foreach (config('jobs.vendor_categories') as $key => $label)
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
                        <textarea name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">{{ old('notes') }}</textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-error :messages="$errors->all()" class="mt-1" />
                        <x-primary-button type="submit">Save Vendor</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <form method="GET" action="{{ route('vendors.index') }}">
                    <input type="text" name="q" value="{{ $search }}" placeholder="Search by ID, name, or company..." class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden" x-data="{ editingId: null }">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="px-4 py-3 whitespace-nowrap">ID</th>
                                <th class="px-4 py-3 whitespace-nowrap">Name</th>
                                <th class="px-4 py-3 whitespace-nowrap">Category</th>
                                <th class="px-4 py-3 whitespace-nowrap">Phone</th>
                                <th class="px-4 py-3 whitespace-nowrap">Email</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($vendors as $vendor)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">{{ $vendor->vendor_id }}</td>
                                    <td class="px-4 py-3 text-gray-800 whitespace-nowrap">{{ $vendor->name }}{{ $vendor->company ? " ({$vendor->company})" : '' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs bg-gray-100 text-gray-600">{{ config('jobs.vendor_categories')[$vendor->category] ?? $vendor->category }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $vendor->phone ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $vendor->email ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        @can('update', $vendor)
                                            <button type="button" @click="editingId = editingId === {{ $vendor->id }} ? null : {{ $vendor->id }}" class="text-indigo-600 hover:underline text-xs">Edit</button>
                                        @endcan
                                    </td>
                                </tr>
                                @can('update', $vendor)
                                <tr x-show="editingId === {{ $vendor->id }}" x-cloak>
                                    <td colspan="6" class="px-4 py-4 bg-gray-50">
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
                                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No vendors.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
