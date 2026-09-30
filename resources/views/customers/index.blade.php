<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Customers</h2>
    </x-slot>

    <div class="p-5 md:p-7">
        <div class="space-y-4">

            @if (session('success'))
                <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">
                    {{ session('success') }}
                </div>
            @endif

            <div class="flex flex-col sm:flex-row gap-4">
            <div class="k-card p-5 flex items-center gap-4 sm:w-64 shrink-0">
                <span class="w-11 h-11 rounded-2xl flex items-center justify-center text-white shrink-0" style="background:linear-gradient(135deg,#E91E63,#FF7A9C);box-shadow:0 8px 18px -8px #E91E63"><x-icon name="users" class="w-5 h-5" /></span>
                <div>
                    <div class="text-2xl font-extrabold text-gray-900 leading-none">{{ $customers->count() }}</div>
                    <div class="text-sm font-semibold text-gray-600 mt-1">Total customers</div>
                </div>
            </div>

            <div class="k-card p-4 flex-1 flex items-center">
                <form method="GET" action="{{ route('customers.index') }}" class="flex flex-col sm:flex-row gap-3 w-full">
                    <div class="relative flex-1">
                        <x-icon name="search" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-gray-400" />
                        <input type="text" name="q" value="{{ $search }}" placeholder="Search customers..." class="w-full pl-10 text-sm">
                    </div>
                    <select name="source" onchange="this.form.submit()" class="text-sm">
                        <option value="">All Sources</option>
                        @foreach (config('kretivco.sources') as $key => $label)
                            <option value="{{ $key }}" {{ $source === $key ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            </div>

            <div class="k-card p-5" x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }">
                <button type="button" @click="open = !open" class="inline-flex items-center gap-2 text-sm font-semibold text-[#C2185B] hover:text-[#AD1457]">
                    <span class="w-7 h-7 rounded-lg bg-[#FFF1EC] flex items-center justify-center">
                        <x-icon name="plus" class="w-4 h-4" x-show="!open" />
                        <x-icon name="x" class="w-4 h-4" x-show="open" x-cloak />
                    </span>
                    <span x-show="!open">New customer</span>
                    <span x-show="open" x-cloak>Close form</span>
                </button>
                <form method="POST" action="{{ route('customers.store') }}" x-show="open" x-cloak
                      x-data="{ customerType: '{{ old('customer_type', 'individual') }}' }"
                      class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                    @csrf
                    <div class="sm:col-span-2">
                        <x-input-label value="Customer Type *" />
                        <div class="mt-1 flex gap-2">
                            @foreach (config('kretivco.customer_types') as $key => $label)
                                <label class="flex items-center gap-2 rounded-xl border border-[#EFE3DE] px-3 py-2 text-sm cursor-pointer">
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
                        <x-address-paste />
                        <div>
                            <x-input-label for="address_line_1" value="Address" />
                            <x-text-input id="address_line_1" name="address_line_1" type="text" class="mt-1 block w-full" :value="old('address_line_1')" placeholder="Line 1" />
                        </div>
                        <x-text-input name="address_line_2" type="text" class="block w-full" :value="old('address_line_2')" placeholder="Line 2" />
                        <div class="grid grid-cols-3 gap-2">
                            <x-text-input name="postcode" type="text" class="block w-full" :value="old('postcode')" placeholder="Postcode" onblur="lookupPostcode(this)" />
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
                        @if (session('confirm_duplicate'))
                            <input type="hidden" name="confirm_duplicate" value="1">
                        @endif
                        <x-primary-button type="submit">{{ session('confirm_duplicate') ? 'Save as a new customer anyway' : 'Save customer' }}</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="k-card overflow-hidden" x-data="{ editingId: null, openId: {{ (int) request('open', 0) ?: 'null' }} }">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide border-b border-[#F5ECE8]">
                                <th class="px-4 py-3">Customer</th>
                                <th class="px-4 py-3 whitespace-nowrap hidden sm:table-cell">Jobs</th>
                                <th class="px-4 py-3 whitespace-nowrap text-right">Value</th>
                                <th class="px-4 py-3 whitespace-nowrap hidden md:table-cell">Source</th>
                                <th class="px-2 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#F5ECE8]">
                            @forelse ($customers as $customer)
                                @php
                                    $st = $customer->stats;
                                    $waPhone = preg_replace('/\D/', '', (string) $customer->phone);
                                    $waPhone = str_starts_with($waPhone, '0') ? '6'.$waPhone : $waPhone;
                                @endphp
                                <tr class="cursor-pointer hover:bg-[#FFF7F3] transition-colors" @click="openId = openId === {{ $customer->id }} ? null : {{ $customer->id }}">
                                    <td class="px-4 py-3.5 max-w-0 w-full">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-xl bg-[#FFF0F5] text-[#C2185B] text-xs font-bold shrink-0">{{ strtoupper(substr($customer->name, 0, 1)) }}</span>
                                            <div class="min-w-0">
                                                <div class="font-semibold text-gray-800 truncate">{{ $customer->name }}</div>
                                                @if ($slow = $slowPayers[$customer->id] ?? null)
                                                    <span class="inline-block mt-0.5 text-[10px] font-semibold rounded-full px-2 py-0.5 bg-amber-100 text-amber-800" title="{{ $slow['text'] }}">Slow payer</span>
                                                @endif
                                                <div class="text-xs text-gray-400 truncate"><span class="font-mono">{{ $customer->customer_id }}</span>{{ $customer->company ? ' · '.$customer->company : '' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3.5 font-semibold text-gray-700 whitespace-nowrap hidden sm:table-cell">{{ $st['jobs'] }}</td>
                                    <td class="px-4 py-3.5 font-bold text-gray-900 whitespace-nowrap text-right">RM {{ number_format($st['value'], 2) }}</td>
                                    <td class="px-4 py-3.5 whitespace-nowrap hidden md:table-cell">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold bg-green-50 text-green-700">{{ config('kretivco.sources')[$customer->source] ?? $customer->source }}</span>
                                    </td>
                                    <td class="px-2 py-3.5 text-gray-300">
                                        <x-icon name="chevron-right" class="w-4 h-4 transition-transform" x-bind:class="openId === {{ $customer->id }} && 'rotate-90 text-[#C2185B]'" />
                                    </td>
                                </tr>
                                <tr x-show="openId === {{ $customer->id }} && editingId !== {{ $customer->id }}" x-cloak>
                                    <td colspan="5" class="px-5 md:px-6 py-5 bg-[#FFF9F6]">
                                        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
                                            <div>
                                                <div class="text-base font-bold text-gray-900">{{ $customer->name }} <span class="ml-1 font-mono text-xs text-gray-400">{{ $customer->customer_id }}</span></div>
                                                <div class="text-xs text-gray-500 mt-0.5">{{ config('kretivco.customer_types')[$customer->customer_type] ?? $customer->customer_type }} · {{ config('kretivco.sources')[$customer->source] ?? $customer->source }}</div>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <a href="{{ route('jobs.create', ['customer_id' => $customer->id]) }}" class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1.5 rounded-lg text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110"><x-icon name="plus" class="w-3.5 h-3.5" /> New job</a>
                                                @can('update', $customer)
                                                    <button type="button" @click.stop="editingId = editingId === {{ $customer->id }} ? null : {{ $customer->id }}; $nextTick(() => document.getElementById('edit-{{ $customer->id }}')?.scrollIntoView({ behavior: 'smooth', block: 'center' }))" class="inline-flex items-center gap-1 text-xs font-semibold px-3 py-1.5 rounded-lg border border-[#EFE3DE] text-gray-700 bg-white hover:bg-[#FFF7F3]"><x-icon name="pencil" class="w-3.5 h-3.5" /> Edit</button>
                                                @endcan
                                                <button type="button" @click="openId = null" class="text-gray-400 hover:text-gray-700" aria-label="Close"><x-icon name="x" class="w-4 h-4" /></button>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3 text-sm mb-5">
                                            <div><div class="text-[11px] font-semibold text-gray-400 uppercase">Company</div>{{ $customer->company ?? 'Not set' }}</div>
                                            <div><div class="text-[11px] font-semibold text-gray-400 uppercase">SSM No.</div>{{ $customer->ssm_number ?? 'Not set' }}</div>
                                            <div><div class="text-[11px] font-semibold text-gray-400 uppercase">Phone</div>
                                                {{ $customer->phone ?? 'Not set' }}
                                                @if ($waPhone) · <a href="https://wa.me/{{ $waPhone }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-green-600 hover:underline"><x-icon name="message-circle" class="w-3.5 h-3.5" /> WhatsApp</a> @endif
                                            </div>
                                            <div><div class="text-[11px] font-semibold text-gray-400 uppercase">Email</div>{{ $customer->email ?? 'Not set' }}</div>
                                            <div class="md:col-span-2"><div class="text-[11px] font-semibold text-gray-400 uppercase">Address</div>
                                                {{ collect([$customer->address_line_1, $customer->address_line_2, trim($customer->postcode.' '.$customer->city), $customer->state])->filter()->implode(', ') ?: 'Not set' }}
                                            </div>
                                        </div>
                                        @if (auth()->user()->canManageFinance() || auth()->user()->isBod())
                                            {{-- Statement of Account: everything invoiced, paid and still owed. --}}
                                            @php $soa = \App\Http\Controllers\StatementController::build($customer); @endphp
                                            @if (count($soa['rows']))
                                                <div class="mb-5 rounded-xl border border-[#F1E3DD] bg-white p-3.5 flex flex-wrap items-center gap-3">
                                                    <div class="flex-1 min-w-[12rem]">
                                                        <p class="text-[11px] font-semibold text-gray-400 uppercase">Statement of Account</p>
                                                        <p class="text-sm font-bold {{ $soa['balance'] > 0.005 ? 'text-[#C2185B]' : 'text-green-600' }}">{{ $soa['balance'] > 0.005 ? 'RM '.number_format($soa['balance'], 2).' outstanding' : 'Fully settled' }}</p>
                                                    </div>
                                                    <a href="{{ route('customers.statement', $customer) }}" target="_blank" class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg border border-[#EFE3DE] text-gray-700 hover:bg-[#FFF7F3]"><x-icon name="file-text" class="w-3.5 h-3.5" /> View PDF</a>
                                                    @if ($customer->phone)
                                                        <a href="{{ \App\Http\Controllers\StatementController::whatsappUrl($customer, $soa['balance']) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg bg-[#25D366] text-white hover:brightness-105"><x-icon name="message-circle" class="w-3.5 h-3.5" /> Send on WhatsApp</a>
                                                    @endif
                                                </div>
                                            @endif
                                        @endif

                                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
                                            <div class="rounded-xl bg-white border border-[#F5E7E1] p-3"><div class="text-[11px] font-semibold text-gray-400 uppercase">Total Value</div><div class="text-lg font-bold">RM {{ number_format($st['value'], 2) }}</div><div class="text-xs text-gray-400">{{ $st['jobs'] }} {{ \Illuminate\Support\Str::plural('job', $st['jobs']) }}</div></div>
                                            <div class="rounded-xl bg-white border border-[#F5E7E1] p-3"><div class="text-[11px] font-semibold text-gray-400 uppercase">Revenue</div><div class="text-lg font-bold text-green-600">RM {{ number_format($st['revenue'], 2) }}</div><div class="text-xs text-gray-400">{{ $st['completed'] }} completed</div></div>
                                            <div class="rounded-xl bg-white border border-[#F5E7E1] p-3"><div class="text-[11px] font-semibold text-gray-400 uppercase">Pipeline</div><div class="text-lg font-bold text-indigo-600">RM {{ number_format($st['pipeline'], 2) }}</div><div class="text-xs text-gray-400">{{ $st['active'] }} active</div></div>
                                        </div>

                                        @if ($st['by_department']->isNotEmpty())
                                            <div class="mb-5">
                                                <div class="text-[11px] font-semibold text-gray-400 uppercase mb-2">Department Breakdown</div>
                                                <div class="flex flex-wrap gap-2">
                                                    @foreach ($st['by_department'] as $deptKey => $row)
                                                        @php $dept = config('kretivco.departments.'.$deptKey); @endphp
                                                        <div class="rounded-xl bg-white border border-[#F5E7E1] px-3 py-2 text-xs">
                                                            <span class="font-bold" style="color: {{ $dept['color'] ?? '#6B7280' }}">{{ \App\Http\Controllers\JobController::DEPT_CODES[$deptKey] ?? strtoupper($deptKey) }}</span>
                                                            <span class="text-gray-500 ml-1">{{ $row['jobs'] }} {{ \Illuminate\Support\Str::plural('job', $row['jobs']) }}</span>
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
                                                    <a href="{{ route('jobs.show', $job) }}" class="flex items-center gap-3 rounded-xl bg-white border border-[#F5E7E1] px-3 py-2 text-sm hover:bg-[#FFF7F3]">
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

                                        <div class="mt-4 text-xs text-gray-400">Customer since {{ $customer->created_at?->format('j F Y') ?? 'unknown' }}</div>
                                    </td>
                                </tr>
                                @can('update', $customer)
                                <tr id="edit-{{ $customer->id }}" x-show="editingId === {{ $customer->id }}" x-cloak>
                                    <td colspan="5" class="px-5 md:px-6 py-4 bg-white border-t border-[#F5ECE8]">
                                        <form method="POST" action="{{ route('customers.update', $customer) }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-start">
                                            @csrf
                                            @method('PUT')
                                            <x-text-input name="name" type="text" class="block w-full" :value="$customer->name" required placeholder="Name" />
                                            <x-text-input name="company" type="text" class="block w-full" :value="$customer->company" placeholder="Company" />
                                            <x-text-input name="ssm_number" type="text" class="block w-full" :value="$customer->ssm_number" placeholder="SSM No." />
                                            <x-text-input name="phone" type="text" class="block w-full" :value="$customer->phone" placeholder="Phone" />
                                            <x-text-input name="email" type="email" class="block w-full" :value="$customer->email" placeholder="Email" />
                                            <select name="source" class="block w-full text-sm">
                                                @foreach (config('kretivco.sources') as $key => $label)
                                                    <option value="{{ $key }}" {{ $customer->source === $key ? 'selected' : '' }}>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <x-address-paste />
                                            <x-text-input name="address_line_1" type="text" class="block w-full" :value="$customer->address_line_1" placeholder="Address line 1" />
                                            <x-text-input name="address_line_2" type="text" class="block w-full" :value="$customer->address_line_2" placeholder="Address line 2" />
                                            <div class="grid grid-cols-3 gap-2">
                                                <x-text-input name="postcode" type="text" class="block w-full" :value="$customer->postcode" placeholder="Postcode" onblur="lookupPostcode(this)" />
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
                                <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">No customers found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
