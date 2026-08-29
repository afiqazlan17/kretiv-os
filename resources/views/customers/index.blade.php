<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Customers</h2>
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
                    <span x-show="!open">+ New Customer</span>
                    <span x-show="open" x-cloak>− Close Form</span>
                </button>
                <form method="POST" action="{{ route('customers.store') }}" x-show="open" x-cloak
                      x-data="{ customerType: '{{ old('customer_type', 'individual') }}' }"
                      class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                    @csrf
                    <div>
                        <x-input-label for="name" value="Name *" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required />
                    </div>
                    <div>
                        <x-input-label for="phone" value="Phone" />
                        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone')" />
                    </div>
                    <div>
                        <x-input-label for="email" value="Email" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email')" />
                    </div>
                    <div>
                        <x-input-label value="Customer Type *" />
                        <div class="mt-1 flex gap-2">
                            @foreach (config('jobs.customer_types') as $key => $label)
                                <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm cursor-pointer">
                                    <input type="radio" name="customer_type" value="{{ $key }}" x-model="customerType" {{ old('customer_type', 'individual') === $key ? 'checked' : '' }}>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div x-show="customerType === 'company'" x-cloak>
                        <x-input-label for="company" value="Company Name" />
                        <x-text-input id="company" name="company" type="text" class="mt-1 block w-full" :value="old('company')" />
                    </div>
                    <div x-show="customerType === 'company'" x-cloak>
                        <x-input-label for="ssm_number" value="SSM Number" />
                        <x-text-input id="ssm_number" name="ssm_number" type="text" class="mt-1 block w-full" :value="old('ssm_number')" />
                    </div>
                    <div>
                        <x-input-label value="Source *" />
                        <select name="source" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            @foreach (config('jobs.sources') as $key => $label)
                                <option value="{{ $key }}" {{ old('source') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="address_line_1" value="Address" />
                        <x-text-input id="address_line_1" name="address_line_1" type="text" class="mt-1 block w-full" :value="old('address_line_1')" placeholder="Line 1" />
                        <x-text-input name="address_line_2" type="text" class="mt-1 block w-full" :value="old('address_line_2')" placeholder="Line 2" />
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <x-text-input name="postcode" type="text" class="mt-1 block w-full" :value="old('postcode')" placeholder="Postcode" />
                        <x-text-input name="city" type="text" class="mt-1 block w-full" :value="old('city')" placeholder="City" />
                        <x-text-input name="state" type="text" class="mt-1 block w-full" :value="old('state')" placeholder="State" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="notes" value="Notes" />
                        <textarea name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">{{ old('notes') }}</textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-error :messages="$errors->all()" class="mt-1" />
                        <x-primary-button type="submit">Save Customer</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <form method="GET" action="{{ route('customers.index') }}">
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
                                <th class="px-4 py-3 whitespace-nowrap">Phone</th>
                                <th class="px-4 py-3 whitespace-nowrap">Email</th>
                                <th class="px-4 py-3 whitespace-nowrap">Type</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($customers as $customer)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">{{ $customer->customer_id }}</td>
                                    <td class="px-4 py-3 text-gray-800 whitespace-nowrap">{{ $customer->customer_type === 'company' ? ($customer->company ?: $customer->name) : $customer->name }}</td>
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $customer->phone ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $customer->email ?? '—' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs bg-gray-100 text-gray-600">{{ config('jobs.customer_types')[$customer->customer_type] ?? $customer->customer_type }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        @can('update', $customer)
                                            <button type="button" @click="editingId = editingId === {{ $customer->id }} ? null : {{ $customer->id }}" class="text-indigo-600 hover:underline text-xs">Edit</button>
                                        @endcan
                                    </td>
                                </tr>
                                @can('update', $customer)
                                <tr x-show="editingId === {{ $customer->id }}" x-cloak>
                                    <td colspan="6" class="px-4 py-4 bg-gray-50">
                                        <form method="POST" action="{{ route('customers.update', $customer) }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-start">
                                            @csrf
                                            @method('PUT')
                                            <x-text-input name="name" type="text" class="block w-full" :value="$customer->name" required placeholder="Name" />
                                            <x-text-input name="phone" type="text" class="block w-full" :value="$customer->phone" placeholder="Phone" />
                                            <x-text-input name="email" type="email" class="block w-full" :value="$customer->email" placeholder="Email" />
                                            <input type="hidden" name="customer_type" value="{{ $customer->customer_type }}">
                                            <input type="hidden" name="source" value="{{ $customer->source }}">
                                            <div class="sm:col-span-3">
                                                <x-primary-button type="submit">Save</x-primary-button>
                                                <button type="button" @click="editingId = null" class="ml-2 text-xs text-gray-500 hover:underline">Cancel</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                                @endcan
                            @empty
                                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No customers.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
