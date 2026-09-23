<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-white leading-tight">Customers</h2>
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
                    <div class="sm:col-span-2">
                        <x-input-label value="Customer Type *" />
                        <div class="mt-1 flex gap-2">
                            @foreach (config('kretivco.customer_types') as $key => $label)
                                <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm cursor-pointer">
                                    <input type="radio" name="customer_type" value="{{ $key }}" x-model="customerType" {{ old('customer_type', 'individual') === $key ? 'checked' : '' }}>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </div>
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
                            @foreach (config('kretivco.sources') as $key => $label)
                                <option value="{{ $key }}" {{ old('source') === $key ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2 space-y-1.5">
                        <div>
                            <x-input-label for="address_line_1" value="Address" />
                            <x-text-input id="address_line_1" name="address_line_1" type="text" class="mt-1 block w-full" :value="old('address_line_1')" placeholder="Line 1" />
                        </div>
                        <x-text-input name="address_line_2" type="text" class="block w-full" :value="old('address_line_2')" placeholder="Line 2" />
                        <div class="grid grid-cols-3 gap-2">
                            <x-text-input name="postcode" type="text" class="block w-full" :value="old('postcode')" placeholder="Postcode" />
                            <x-text-input name="city" type="text" class="block w-full" :value="old('city')" placeholder="City" />
                            <x-text-input name="state" type="text" class="block w-full" :value="old('state')" placeholder="State" />
                        </div>
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

            <div class="bg-white shadow-sm sm:rounded-lg p-5 border-l-4 border-pink-500 max-w-xs">
                <div class="text-[11px] font-semibold text-gray-400 uppercase">Total Customers</div>
                <div class="text-2xl font-bold text-gray-900 mt-1">{{ $customers->count() }}</div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-4">
                <form method="GET" action="{{ route('customers.index') }}" class="flex flex-col sm:flex-row gap-3">
                    <input type="text" name="q" value="{{ $search }}" placeholder="Search customers..." class="flex-1 rounded-md border-gray-300 shadow-sm text-sm">
                    <select name="source" onchange="this.form.submit()" class="rounded-md border-gray-300 shadow-sm text-sm">
                        <option value="">All Sources</option>
                        @foreach (config('kretivco.sources') as $key => $label)
                            <option value="{{ $key }}" {{ $source === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden" x-data="{ editingId: null, openId: {{ (int) request('open', 0) ?: 'null' }} }">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="px-4 py-3"></th>
                                <th class="px-4 py-3 whitespace-nowrap">ID</th>
                                <th class="px-4 py-3 whitespace-nowrap">PIC Name</th>
                                <th class="px-4 py-3 whitespace-nowrap">Company</th>
                                <th class="px-4 py-3 whitespace-nowrap">Jobs</th>
                                <th class="px-4 py-3 whitespace-nowrap">Value</th>
                                <th class="px-4 py-3 whitespace-nowrap">Source</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($customers as $customer)
                                @php
                                    $st = $customer->stats;
                                    $waPhone = preg_replace('/\D/', '', (string) $customer->phone);
                                    $waPhone = str_starts_with($waPhone, '0') ? '6'.$waPhone : $waPhone;
                                @endphp
                                <tr class="cursor-pointer hover:bg-gray-50" @click="openId = openId === {{ $customer->id }} ? null : {{ $customer->id }}">
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-pink-50 text-pink-600 text-xs font-bold">{{ strtoupper(substr($customer->name, 0, 1)) }}</span>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-gray-500 whitespace-nowrap">{{ $customer->customer_id }}</td>
                                    <td class="px-4 py-3 font-semibold text-gray-800 whitespace-nowrap">{{ $customer->name }}</td>
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $customer->company ?? '—' }}</td>
                                    <td class="px-4 py-3 font-semibold whitespace-nowrap">{{ $st['jobs'] }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">RM {{ number_format($st['value'], 2) }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs bg-green-50 text-green-600">{{ config('kretivco.sources')[$customer->source] ?? $customer->source }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-gray-400" x-text="openId === {{ $customer->id }} ? '⌄' : '›'"></td>
                                </tr>
                                <tr x-show="openId === {{ $customer->id }}" x-cloak>
                                    <td colspan="8" class="px-6 py-5 bg-gray-50">
                                        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                                            <div>
                                                <div class="text-base font-bold text-gray-900">{{ $customer->name }} <span class="ml-1 font-mono text-xs text-gray-400">{{ $customer->customer_id }}</span></div>
                                                <div class="text-xs text-gray-500 mt-0.5">{{ config('kretivco.customer_types')[$customer->customer_type] ?? $customer->customer_type }} · {{ config('kretivco.sources')[$customer->source] ?? $customer->source }}</div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <a href="{{ route('jobs.create', ['customer_id' => $customer->id]) }}" class="text-xs font-semibold px-3 py-1.5 rounded-md bg-pink-600 text-white hover:bg-pink-700">+ New Job</a>
                                                @can('update', $customer)
                                                    <button type="button" @click.stop="editingId = editingId === {{ $customer->id }} ? null : {{ $customer->id }}" class="text-xs font-semibold px-3 py-1.5 rounded-md border border-gray-200 text-gray-700 hover:bg-white">Edit</button>
                                                @endcan
                                                <button type="button" @click="openId = null" class="text-gray-400 hover:text-gray-700 text-lg leading-none">×</button>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3 text-sm mb-5">
                                            <div><div class="text-[11px] font-semibold text-gray-400 uppercase">Company</div>{{ $customer->company ?? '—' }}</div>
                                            <div><div class="text-[11px] font-semibold text-gray-400 uppercase">SSM No.</div>{{ $customer->ssm_number ?? '—' }}</div>
                                            <div><div class="text-[11px] font-semibold text-gray-400 uppercase">Phone</div>
                                                {{ $customer->phone ?? '—' }}
                                                @if ($waPhone) · <a href="https://wa.me/{{ $waPhone }}" target="_blank" rel="noopener" class="text-green-600 hover:underline">WhatsApp ↗</a> @endif
                                            </div>
                                            <div><div class="text-[11px] font-semibold text-gray-400 uppercase">Email</div>{{ $customer->email ?? '—' }}</div>
                                            <div class="md:col-span-2"><div class="text-[11px] font-semibold text-gray-400 uppercase">Address</div>
                                                {{ collect([$customer->address_line_1, $customer->address_line_2, trim($customer->postcode.' '.$customer->city), $customer->state])->filter()->implode(', ') ?: '—' }}
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
                                            <div class="rounded-lg bg-white border border-gray-100 p-3"><div class="text-[11px] font-semibold text-gray-400 uppercase">Total Value</div><div class="text-lg font-bold">RM {{ number_format($st['value'], 2) }}</div><div class="text-xs text-gray-400">{{ $st['jobs'] }} jobs</div></div>
                                            <div class="rounded-lg bg-white border border-gray-100 p-3"><div class="text-[11px] font-semibold text-gray-400 uppercase">Revenue</div><div class="text-lg font-bold text-green-600">RM {{ number_format($st['revenue'], 2) }}</div><div class="text-xs text-gray-400">{{ $st['completed'] }} completed</div></div>
                                            <div class="rounded-lg bg-white border border-gray-100 p-3"><div class="text-[11px] font-semibold text-gray-400 uppercase">Pipeline</div><div class="text-lg font-bold text-indigo-600">RM {{ number_format($st['pipeline'], 2) }}</div><div class="text-xs text-gray-400">{{ $st['active'] }} active</div></div>
                                        </div>

                                        @if ($st['by_department']->isNotEmpty())
                                            <div class="mb-5">
                                                <div class="text-[11px] font-semibold text-gray-400 uppercase mb-2">Department Breakdown</div>
                                                <div class="flex flex-wrap gap-2">
                                                    @foreach ($st['by_department'] as $deptKey => $row)
                                                        @php $dept = config('kretivco.departments.'.$deptKey); @endphp
                                                        <div class="rounded-lg bg-white border border-gray-100 px-3 py-2 text-xs">
                                                            <span class="font-bold" style="color: {{ $dept['color'] ?? '#6B7280' }}">{{ \App\Http\Controllers\JobController::DEPT_CODES[$deptKey] ?? strtoupper($deptKey) }}</span>
                                                            <span class="text-gray-500 ml-1">{{ $row['jobs'] }} jobs</span>
                                                            <span class="font-semibold ml-1">RM {{ number_format($row['value'], 2) }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        <div>
                                            <div class="text-[11px] font-semibold text-gray-400 uppercase mb-2">Job History ({{ $customer->job_history->count() }})</div>
                                            <div class="space-y-1.5">
                                                @forelse ($customer->job_history as $job)
                                                    @php $js = config('kretivco.job_statuses.'.$job->status); $jd = config('kretivco.departments.'.$job->department); @endphp
                                                    <a href="{{ route('jobs.show', $job) }}" class="flex items-center gap-3 rounded-lg bg-white border border-gray-100 px-3 py-2 text-sm hover:bg-gray-50">
                                                        <span class="font-mono text-xs font-semibold">{{ $job->job_id }}</span>
                                                        <span class="text-[11px] font-bold rounded px-1.5 py-0.5" style="color: {{ $jd['color'] ?? '#6B7280' }}; background: {{ $jd['color'] ?? '#6B7280' }}15">{{ \App\Http\Controllers\JobController::DEPT_CODES[$job->department] ?? strtoupper($job->department) }}</span>
                                                        <span class="text-gray-700 flex-1">{{ $job->job_type }}</span>
                                                        @if ($js)<span class="text-xs font-semibold rounded-full px-2.5 py-0.5" style="color: {{ $js['color'] }}; background: {{ $js['color'] }}15">{{ $js['label'] }}</span>@endif
                                                        <span class="font-semibold text-xs">RM {{ number_format($job->estimation_value ?? 0, 2) }}</span>
                                                    </a>
                                                @empty
                                                    <p class="text-sm text-gray-400 italic">No jobs yet.</p>
                                                @endforelse
                                            </div>
                                        </div>

                                        <div class="mt-4 text-xs text-gray-400">Customer since {{ $customer->created_at?->format('j F Y') ?? '—' }}</div>
                                    </td>
                                </tr>
                                @can('update', $customer)
                                <tr x-show="editingId === {{ $customer->id }}" x-cloak>
                                    <td colspan="8" class="px-6 py-4 bg-white border-t border-gray-100">
                                        <form method="POST" action="{{ route('customers.update', $customer) }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-start">
                                            @csrf
                                            @method('PUT')
                                            <x-text-input name="name" type="text" class="block w-full" :value="$customer->name" required placeholder="Name" />
                                            <x-text-input name="company" type="text" class="block w-full" :value="$customer->company" placeholder="Company" />
                                            <x-text-input name="ssm_number" type="text" class="block w-full" :value="$customer->ssm_number" placeholder="SSM No." />
                                            <x-text-input name="phone" type="text" class="block w-full" :value="$customer->phone" placeholder="Phone" />
                                            <x-text-input name="email" type="email" class="block w-full" :value="$customer->email" placeholder="Email" />
                                            <select name="source" class="block w-full rounded-md border-gray-300 shadow-sm text-sm">
                                                @foreach (config('kretivco.sources') as $key => $label)
                                                    <option value="{{ $key }}" {{ $customer->source === $key ? 'selected' : '' }}>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <x-text-input name="address_line_1" type="text" class="block w-full" :value="$customer->address_line_1" placeholder="Address line 1" />
                                            <x-text-input name="address_line_2" type="text" class="block w-full" :value="$customer->address_line_2" placeholder="Address line 2" />
                                            <div class="grid grid-cols-3 gap-2">
                                                <x-text-input name="postcode" type="text" class="block w-full" :value="$customer->postcode" placeholder="Postcode" />
                                                <x-text-input name="city" type="text" class="block w-full" :value="$customer->city" placeholder="City" />
                                                <x-text-input name="state" type="text" class="block w-full" :value="$customer->state" placeholder="State" />
                                            </div>
                                            <input type="hidden" name="customer_type" value="{{ $customer->customer_type }}">
                                            <div class="sm:col-span-3">
                                                <x-primary-button type="submit">Save</x-primary-button>
                                                <button type="button" @click="editingId = null" class="ml-2 text-xs text-gray-500 hover:underline">Cancel</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                                @endcan
                            @empty
                                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">No customers.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
