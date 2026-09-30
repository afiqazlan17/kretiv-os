<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">New Job</h2>
    </x-slot>

    <div class="p-5 md:p-7"
         x-data="jobCreateForm(
             {{ $customers->map(fn ($c) => ['id' => $c->id, 'customer_id' => $c->customer_id, 'label' => $c->displayName(), 'slow' => $slowPayers[$c->id] ?? null])->values()->toJson() }},
             {{ json_encode(array_keys($departments)) }},
             {{ json_encode(config('kretivco.package_catalog')) }}
         )">
        <div>
            {{-- Mobile-only Form/Preview switch — on phones the two panels used to stack
                 with the preview's 80vh iframe below, so editing meant fighting a huge
                 frame; this lets you fully hide one side instead. --}}
            <div class="lg:hidden flex gap-2 mb-3">
                <button type="button" @click="mobileTab = 'form'" class="flex-1 text-sm font-semibold px-3 py-2 rounded-xl border"
                        :class="mobileTab === 'form' ? 'bg-[#C2185B] text-white border-[#C2185B]' : 'border-[#EFE3DE] text-gray-600 bg-white'">Form</button>
                <button type="button" @click="mobileTab = 'preview'; $nextTick(() => pvPager.render($refs.pvCanvas))" class="flex-1 text-sm font-semibold px-3 py-2 rounded-xl border"
                        :class="mobileTab === 'preview' ? 'bg-[#C2185B] text-white border-[#C2185B]' : 'border-[#EFE3DE] text-gray-600 bg-white'">Quotation Preview</button>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,700px)_minmax(0,1fr)] gap-4 items-start">
            <div class="k-card p-5 md:p-6" :class="mobileTab === 'preview' ? 'hidden lg:block' : ''">
                <form method="POST" action="{{ route('jobs.store') }}" @invalid.capture="formError = 'Fill in the highlighted field before saving. It might be in a department section above.'" @submit="formError = null">
                    @csrf

                    {{-- Customer picker + inline create --}}
                    <div class="mb-5">
                        <x-input-label value="Customer *" />
                        <div class="relative">
                            <input type="text" x-model="customerQuery" @focus="customerOpen = true" @click.outside="customerOpen = false"
                                   x-bind:placeholder="selectedCustomer ? selectedCustomer.customer_id + ' · ' + selectedCustomer.label : 'Search or click to browse customers...'"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <input type="hidden" name="customer_id" x-model="customerId" required>
                            <div x-show="customerOpen" x-cloak class="absolute z-20 mt-1 w-full max-h-56 overflow-y-auto bg-white border border-[#F5E7E1] rounded-xl shadow-lg">
                                <template x-for="c in filteredCustomers" :key="c.id">
                                    <div @click="selectCustomer(c)" class="px-3 py-2 text-sm cursor-pointer hover:bg-[#FFF5F1] border-b border-[#FBF3EF]">
                                        <span class="font-semibold" x-text="c.customer_id + ' · ' + c.label"></span>
                                    </div>
                                </template>
                                <div x-show="filteredCustomers.length === 0" class="px-3 py-2 text-sm text-gray-400">
                                    No customers found.
                                    <button type="button" @click="inlineCustomer.name = customerQuery; showInlineCustomer = true; customerOpen = false" class="ml-1 font-semibold text-[#C2185B] hover:underline" x-show="customerQuery.trim()">Add "<span x-text="customerQuery"></span>" as a new customer</button>
                                </div>
                            </div>
                        </div>
                        <p x-show="selectedCustomer && selectedCustomer.slow" x-cloak class="mt-2 flex items-start gap-1.5 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3" /><path d="M12 9v4" /><path d="M12 17h.01" /></svg>
                            <span>This customer usually pays late (<span x-text="selectedCustomer?.slow"></span>). Ask for a deposit before starting the work.</span>
                        </p>
                        <button type="button" @click="showInlineCustomer = !showInlineCustomer" class="mt-1.5 inline-flex items-center gap-1 text-xs font-semibold text-[#C2185B] hover:underline" x-show="!showInlineCustomer"><x-icon name="plus" class="w-3.5 h-3.5" /> New Customer</button>

                        <div x-show="showInlineCustomer" x-cloak class="mt-2 p-3.5 bg-[#FFF9F6] rounded-xl border border-[#F5ECE8]">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-semibold text-gray-600">New Customer</span>
                                <button type="button" @click="showInlineCustomer = false" class="text-gray-400 hover:text-gray-700" aria-label="Close"><x-icon name="x" class="w-4 h-4" /></button>
                            </div>
                            <div class="grid grid-cols-2 gap-2 mb-2">
                                <input type="text" x-model="inlineCustomer.name" placeholder="Name *" class="rounded-md border-gray-300 shadow-sm text-xs h-9">
                                <input type="text" x-model="inlineCustomer.company" placeholder="Company" class="rounded-md border-gray-300 shadow-sm text-xs h-9">
                                <input type="text" x-model="inlineCustomer.phone" placeholder="Phone" class="rounded-md border-gray-300 shadow-sm text-xs h-9">
                                <input type="email" x-model="inlineCustomer.email" placeholder="Email" class="rounded-md border-gray-300 shadow-sm text-xs h-9">
                                <input type="text" x-model="inlineCustomer.address_line_1" placeholder="Address line 1" class="col-span-2 rounded-md border-gray-300 shadow-sm text-xs h-9">
                                <input type="text" x-model="inlineCustomer.address_line_2" placeholder="Address line 2" class="col-span-2 rounded-md border-gray-300 shadow-sm text-xs h-9">
                                <input type="text" x-model="inlineCustomer.postcode" placeholder="Postcode" class="rounded-md border-gray-300 shadow-sm text-xs h-9">
                                <input type="text" x-model="inlineCustomer.city" placeholder="City" class="rounded-md border-gray-300 shadow-sm text-xs h-9">
                                <input type="text" x-model="inlineCustomer.state" placeholder="State" class="col-span-2 rounded-md border-gray-300 shadow-sm text-xs h-9">
                                <details class="col-span-2 text-xs"><summary class="cursor-pointer font-semibold text-[#C2185B]">Paste full address</summary>
                                    <textarea x-model="inlinePaste" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs" placeholder="No. 105, Jalan SP 7/14, Bandar Saujana Putra, 42610 Jenjarom, Selangor"></textarea>
                                    <button type="button" @click="fillInlineAddress($event)" class="mt-1 font-semibold px-2.5 py-1 rounded-md text-white bg-[#C2185B]">Fill in</button>
                                </details>
                            </div>
                            <select x-model="inlineCustomer.source" class="w-full rounded-md border-gray-300 shadow-sm text-xs h-9 mb-2">
                                @foreach (config('kretivco.sources') as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <div x-show="inlineError" x-cloak class="text-xs text-red-600 mb-2" x-text="inlineError"></div>
                            <div x-show="inlineExisting" x-cloak class="flex flex-wrap gap-2 mb-2">
                                <button type="button" @click="selectCustomer(inlineExisting); showInlineCustomer = false; inlineExisting = null; inlineError = null" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-[#C2185B] text-[#C2185B] hover:bg-[#FFF0F5]" x-text="inlineExisting ? `Use ${inlineExisting.customer_id} ${inlineExisting.label}` : ''"></button>
                            </div>
                            <button type="button" @click="saveInlineCustomer()" :disabled="inlineSaving || !inlineCustomer.name.trim()"
                                    class="text-xs font-semibold px-3.5 py-1.5 rounded-lg text-white bg-gradient-to-r from-[#E91E63] to-[#F46A3A] hover:brightness-110 disabled:opacity-40">
                                <span x-text="inlineSaving ? 'Saving...' : 'Save Customer'"></span>
                            </button>
                        </div>
                    </div>

                    {{-- Department multi-select --}}
                    <div class="mb-5">
                        <x-input-label value="Department * (you can select more than one)" />
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-1">
                            @foreach ($departments as $key => $dept)
                                <label class="flex items-center justify-center gap-2 rounded-xl border px-3 py-2.5 text-xs font-semibold cursor-pointer transition-colors"
                                       :class="depts.includes('{{ $key }}') ? 'border-2' : 'border-[#EFE3DE] text-gray-600 hover:bg-[#FFF7F3]'"
                                       :style="depts.includes('{{ $key }}') ? 'border-color: {{ $dept['color'] }}; color: {{ $dept['color'] }}; background: {{ $dept['color'] }}10' : ''">
                                    <input type="checkbox" name="departments[]" value="{{ $key }}" x-model="depts" class="hidden">
                                    {{ $dept['label'] }}
                                </label>
                            @endforeach
                        </div>
                        <div x-show="depts.length > 1" x-cloak class="mt-2 px-3 py-2 rounded-xl bg-[#FFF0F5] border border-dashed border-[#F48FB1] text-xs text-gray-700">
                            <span x-text="depts.length"></span> departments selected. One Project ID will be generated to group these jobs together, and each department still gets its own Job ID and status.
                        </div>
                    </div>

                    {{-- Per-department fields --}}
                    @foreach ($departments as $key => $dept)
                        <div x-show="depts.includes('{{ $key }}')" x-cloak class="mb-4 p-4 rounded-2xl border" style="border-color: {{ $dept['color'] }}30; background: {{ $dept['color'] }}08">
                            <div class="flex items-center gap-2 mb-3">
                                <span class="text-[10px] font-bold px-2 py-1 rounded" style="color: {{ $dept['color'] }}; background: {{ $dept['color'] }}18">{{ \App\Http\Controllers\JobController::DEPT_CODES[$key] ?? strtoupper($key) }}</span>
                                <span class="text-sm font-bold">{{ $dept['label'] }}</span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                                <div>
                                    <x-input-label value="Job Type *" />
                                    <select name="per_dept[{{ $key }}][job_type_category]" x-model="perDept.{{ $key }}.jobTypeCategory" :disabled="!depts.includes('{{ $key }}')" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                                        @foreach (config('kretivco.job_types') as $tKey => $t)
                                            <option value="{{ $tKey }}">{{ $t['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <x-input-label value="Bank *" />
                                    <select name="per_dept[{{ $key }}][bank]" x-model="perDept.{{ $key }}.bank" required :disabled="!depts.includes('{{ $key }}')" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                                        <option value="" disabled>Select a bank</option>
                                        @foreach (config('kretivco.banks') as $bKey => $b)
                                            <option value="{{ $bKey }}">{{ $b['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            @if (! empty(config('kretivco.package_catalog.'.$key)))
                                <div x-show="perDept.{{ $key }}.jobTypeCategory === 'product_sale'" x-cloak class="mb-3 p-3 rounded-xl bg-white border border-dashed border-[#E8D5CD]">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <div>
                                            <x-input-label value="Product" />
                                            <select name="per_dept[{{ $key }}][product_line]" x-model="perDept.{{ $key }}.productLine" @change="perDept.{{ $key }}.segment = ''; perDept.{{ $key }}.pkg = ''"
                                                    :disabled="!depts.includes('{{ $key }}') || perDept.{{ $key }}.jobTypeCategory !== 'product_sale'" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                                                <option value="">Custom job (not a package)</option>
                                                <template x-for="line in productLinesFor('{{ $key }}')" :key="line.key">
                                                    <option :value="line.key" x-text="line.label"></option>
                                                </template>
                                            </select>
                                        </div>
                                        <div x-show="perDept.{{ $key }}.productLine">
                                            <x-input-label value="Customer Type" />
                                            <select name="per_dept[{{ $key }}][segment]" x-model="perDept.{{ $key }}.segment" @change="perDept.{{ $key }}.pkg = ''"
                                                    :disabled="!depts.includes('{{ $key }}') || perDept.{{ $key }}.jobTypeCategory !== 'product_sale'" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                                                <option value="">Select a customer type</option>
                                                <template x-for="seg in segmentsFor('{{ $key }}', perDept.{{ $key }}.productLine)" :key="seg.key">
                                                    <option :value="seg.key" x-text="seg.label"></option>
                                                </template>
                                            </select>
                                        </div>
                                    </div>
                                    <div x-show="perDept.{{ $key }}.segment" class="mt-3">
                                        <x-input-label value="Package" />
                                        <select name="per_dept[{{ $key }}][package_value]" x-model="perDept.{{ $key }}.pkg" @change="onPackageChange('{{ $key }}')"
                                                :disabled="!depts.includes('{{ $key }}') || perDept.{{ $key }}.jobTypeCategory !== 'product_sale'" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                                            <option value="">Select a package</option>
                                            <template x-for="opt in packageTierOptions('{{ $key }}', perDept.{{ $key }}.productLine, perDept.{{ $key }}.segment)" :key="opt.value">
                                                <option :value="opt.value" x-text="opt.label"></option>
                                            </template>
                                        </select>
                                        <template x-if="findPackageTier('{{ $key }}', perDept.{{ $key }}.productLine, perDept.{{ $key }}.segment, perDept.{{ $key }}.pkg)">
                                            <div class="mt-2 p-2.5 rounded-xl bg-[#FFF9F6] text-xs text-gray-600 leading-relaxed">
                                                <template x-for="line in packageItemLines('{{ $key }}', perDept.{{ $key }}.productLine, perDept.{{ $key }}.segment, perDept.{{ $key }}.pkg)" :key="line">
                                                    <div x-text="'• ' + line"></div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            @endif

                            <div class="mb-3">
                                <x-input-label value="Job Name *" />
                                <input type="text" name="per_dept[{{ $key }}][job_type]" x-model="perDept.{{ $key }}.jobType" :disabled="!depts.includes('{{ $key }}')" placeholder="e.g. Business Card for Ariff's Wedding" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                                <div>
                                    <x-input-label value="Start Date" />
                                    <input type="date" name="per_dept[{{ $key }}][start_date]" value="{{ old('per_dept.'.$key.'.start_date', now()->toDateString()) }}" :disabled="!depts.includes('{{ $key }}')" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                                </div>
                                <div>
                                    <x-input-label value="Deadline" />
                                    <input type="date" name="per_dept[{{ $key }}][deadline]" value="{{ old('per_dept.'.$key.'.deadline', now()->addDays(14)->toDateString()) }}" :disabled="!depts.includes('{{ $key }}')" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                                </div>
                            </div>

                            {{-- Line items, optional breakdown shown on the quotation PDF instead of a single collapsed row --}}
                            <div class="mb-3">
                                <x-input-label value="Line Items (optional, shown on the quotation PDF)" />
                                <div class="mt-1 space-y-2">
                                    <template x-for="(row, idx) in perDept.{{ $key }}.lineItems" :key="idx">
                                        <div class="rounded-xl border border-[#EFE3DE] bg-white p-2.5 space-y-1.5">
                                            <div class="flex items-start gap-1.5">
                                                <div class="flex-1 relative" x-data="itemCombo('{{ route('items.search') }}', '{{ $key }}', 'create')" @click.outside="open = false">
                                                    <label class="text-[10px] text-gray-400">Item name</label>
                                                    <input type="text" :name="`per_dept[{{ $key }}][line_items][${idx}][item]`" x-model="row.item" :disabled="!depts.includes('{{ $key }}')" placeholder="Search the library or type an item" autocomplete="off"
                                                           @focus="search(row.item)" @input="search(row.item)" @keydown.escape="open = false" class="block w-full rounded-md border-gray-300 shadow-sm text-xs">
                                                    <x-item-dropdown />
                                                </div>
                                                <button type="button" @click="perDept.{{ $key }}.lineItems.splice(idx, 1)" class="mt-5 text-gray-400 hover:text-red-500" title="Remove item" aria-label="Remove item"><x-icon name="trash-2" class="w-4 h-4" /></button>
                                            </div>
                                            <div>
                                                <label class="text-[10px] text-gray-400">Description (optional)</label>
                                                <textarea rows="2" :name="`per_dept[{{ $key }}][line_items][${idx}][desc]`" x-model="row.desc" :disabled="!depts.includes('{{ $key }}')" placeholder="Size, spec or extra detail for this item" class="block w-full rounded-md border-gray-300 shadow-sm text-xs"></textarea>
                                            </div>
                                            <div class="grid grid-cols-2 gap-1.5">
                                                <div>
                                                    <label class="text-[10px] text-gray-400">Quantity</label>
                                                    <input type="number" step="1" min="0" :name="`per_dept[{{ $key }}][line_items][${idx}][qty]`" x-model="row.qty" :disabled="!depts.includes('{{ $key }}')" class="block w-full rounded-md border-gray-300 shadow-sm text-xs">
                                                </div>
                                                <div>
                                                    <label class="text-[10px] text-gray-400">Price (RM)</label>
                                                    <input type="number" step="0.01" min="0" :name="`per_dept[{{ $key }}][line_items][${idx}][price]`" x-model="row.price" :disabled="!depts.includes('{{ $key }}')" class="block w-full rounded-md border-gray-300 shadow-sm text-xs">
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                    <button type="button" @click="perDept.{{ $key }}.lineItems.push({ item: '', desc: '', qty: 1, price: 0 })" class="inline-flex items-center gap-1 text-xs font-semibold text-[#C2185B] hover:underline"><x-icon name="plus" class="w-3.5 h-3.5" /> Add Line Item</button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                                <div>
                                    <x-input-label value="Delivery (RM)" />
                                    <input type="number" step="0.01" min="0" name="per_dept[{{ $key }}][delivery_amount]" x-model="perDept.{{ $key }}.delivery" :disabled="!depts.includes('{{ $key }}')" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                                </div>
                                <div>
                                    <x-input-label value="Discount (RM)" />
                                    <input type="number" step="0.01" min="0" name="per_dept[{{ $key }}][discount_amount]" x-model="perDept.{{ $key }}.discount" :disabled="!depts.includes('{{ $key }}')" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                                </div>
                            </div>

                            <div class="mb-3">
                                <x-input-label value="Quotation valid for (days)" />
                                <input type="number" min="1" max="365" name="per_dept[{{ $key }}][valid_days]" x-model="perDept.{{ $key }}.validDays" :disabled="!depts.includes('{{ $key }}')" class="mt-1 block w-40 rounded-md border-gray-300 shadow-sm text-xs">
                                <p class="mt-1 text-[11px] text-gray-400">Standard is 14 days. Tenders may ask for longer, e.g. 90.</p>
                            </div>

                            {{-- Lets staff tweak the quotation's wording before the job is saved. Edited notes are stored on the job (document_notes.quotation) and are what the Quotation modal opens with later. --}}
                            <div class="mb-3 p-3 rounded-xl bg-white border border-dashed border-[#E8D5CD]">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-semibold text-gray-700">Quotation notes</span>
                                    <button type="button" @click="toggleQuotationNotes('{{ $key }}')" :disabled="!depts.includes('{{ $key }}')"
                                            class="text-xs font-semibold px-2.5 py-1 rounded-lg border border-[#F8D7E3] text-[#C2185B] hover:bg-[#FFF0F5] disabled:opacity-40"
                                            x-text="perDept.{{ $key }}.editNotes ? 'Use default notes' : 'Edit notes shown on quotation'"></button>
                                </div>
                                <p x-show="!perDept.{{ $key }}.editNotes" class="mt-1 text-xs text-gray-400">Uses the standard notes. Edit them here only if this quotation needs different wording; your version is kept for this job.</p>
                                {{-- Submitted only while editing, so the job keeps these as its own quotation notes. --}}
                                <input type="hidden" name="per_dept[{{ $key }}][quotation_notes]" :value="perDept.{{ $key }}.notesLines.join('\n')" :disabled="!depts.includes('{{ $key }}') || !perDept.{{ $key }}.editNotes">
                                <div x-show="perDept.{{ $key }}.editNotes" x-cloak class="mt-2 space-y-1.5">
                                    <template x-for="(line, i) in perDept.{{ $key }}.notesLines" :key="i">
                                        <div class="flex items-start gap-1.5">
                                            <span class="mt-1.5 text-xs text-gray-400 w-4 text-right" x-text="(i + 1) + '.'"></span>
                                            <input type="text" x-model="perDept.{{ $key }}.notesLines[i]" class="flex-1 rounded-md border-gray-300 shadow-sm text-xs">
                                            <button type="button" @click="perDept.{{ $key }}.notesLines.splice(i, 1)" class="mt-2 text-gray-400 hover:text-red-500" title="Remove line" aria-label="Remove line"><x-icon name="x" class="w-4 h-4" /></button>
                                        </div>
                                    </template>
                                    <button type="button" @click="perDept.{{ $key }}.notesLines.push('')" class="inline-flex items-center gap-1 text-xs font-semibold text-[#C2185B] hover:underline"><x-icon name="plus" class="w-3.5 h-3.5" /> Add line</button>
                                </div>
                            </div>

                            <div>
                                <x-input-label value="Special Remarks" />
                                <textarea name="per_dept[{{ $key }}][notes]" :disabled="!depts.includes('{{ $key }}')" rows="2" placeholder="Anything the team should know about this job" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs"></textarea>
                            </div>
                        </div>
                    @endforeach

                    <x-input-error :messages="$errors->all()" class="mt-1" />
                    <p x-show="formError" x-cloak x-text="formError" class="mt-2 text-sm font-semibold text-red-600"></p>
                    <label class="mt-3 flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="take_in" value="1" @checked(old('take_in', true)) class="mt-0.5 rounded">
                        <span>I'll handle this job <span class="block text-xs text-gray-400">It goes straight to Quotation with you as the person responsible, so you can send the quotation right away. Untick to leave it in New Jobs for someone else.</span></span>
                    </label>
                    <div class="mt-2">
                        <x-primary-button type="submit">Save Job</x-primary-button>
                        <a href="{{ route('jobs.index') }}" class="ml-2 text-xs text-gray-500 hover:underline">Cancel</a>
                    </div>
                </form>
            </div>

            {{-- Live quotation preview — same PDF the job's Quotation button produces --}}
            <div class="k-card overflow-hidden flex-col lg:sticky lg:top-4 h-[80vh] lg:h-[88vh]"
                 :class="mobileTab === 'preview' ? 'flex' : 'hidden lg:flex'">
                <div class="flex items-center justify-between gap-2 px-4 py-3 border-b border-[#F5ECE8]">
                    <h3 class="text-sm font-bold text-gray-900">Quotation preview</h3>
                    <div class="flex gap-1" x-show="depts.length > 1" x-cloak>
                        <template x-for="d in depts" :key="d">
                            <button type="button" @click="previewDept = d" class="text-[11px] font-semibold px-2 py-1 rounded-lg border"
                                    :class="activeDept === d ? 'bg-[#C2185B] text-white border-[#C2185B]' : 'border-[#EFE3DE] text-gray-600'" x-text="d.toUpperCase()"></button>
                        </template>
                    </div>
                </div>
                {{-- Desktop: native iframe viewer. Mobile can't reliably page/scroll a PDF
                     embedded in an iframe, so it gets a canvas render + Prev/Next instead. --}}
                <div class="relative flex-1 bg-[#F7F1EE] min-h-0 hidden lg:block">
                    <p x-show="!depts.length" class="absolute inset-0 flex items-center justify-center text-sm text-gray-400 px-6 text-center">Select a department to see the quotation fill in as you type.</p>
                    <template x-for="i in [0, 1]" :key="i">
                        <iframe class="absolute inset-0 w-full h-full border-0 bg-white" :class="pvActive === i ? 'z-10' : 'z-0'" x-show="depts.length && pvSrc[pvActive]"
                                :src="pvSrc[i] || 'about:blank'" @load="pvLoaded(i)"></iframe>
                    </template>
                    <div x-show="pvBusy" x-cloak class="absolute z-20 top-2 right-3 text-xs text-gray-500 bg-white/90 rounded px-2 py-1 shadow">Updating preview…</div>
                    <div x-show="pvError" x-cloak class="absolute z-20 bottom-2 left-3 right-3 text-xs text-red-600 bg-white rounded px-2 py-1 shadow" x-text="pvError"></div>
                </div>
                <div class="relative flex-1 bg-[#F7F1EE] min-h-0 flex flex-col lg:hidden">
                    <p x-show="!depts.length" class="absolute inset-0 flex items-center justify-center text-sm text-gray-400 px-6 text-center">Select a department to see the quotation fill in as you type.</p>
                    <div class="flex-1 overflow-auto flex items-start justify-center p-2">
                        <p x-show="pvPager.error" x-text="pvPager.error" class="text-xs text-red-600 text-center p-4"></p>
                        <canvas x-show="depts.length && !pvPager.error" x-ref="pvCanvas" class="shadow bg-white"></canvas>
                    </div>
                    <div x-show="depts.length" class="flex items-center justify-center gap-3 px-3 py-2 border-t border-[#F5ECE8] bg-white text-sm">
                        <button type="button" @click="pvPager.prev($refs.pvCanvas)" :disabled="pvPager.pageNum <= 1"
                                class="inline-flex items-center gap-1 px-3 py-1 rounded-lg border border-[#EFE3DE] disabled:opacity-30"><x-icon name="chevron-right" class="w-4 h-4 rotate-180" /> Prev</button>
                        <span class="text-xs text-gray-500" x-text="`Page ${pvPager.pageNum} / ${pvPager.numPages}`"></span>
                        <button type="button" @click="pvPager.next($refs.pvCanvas)" :disabled="pvPager.pageNum >= pvPager.numPages"
                                class="inline-flex items-center gap-1 px-3 py-1 rounded-lg border border-[#EFE3DE] disabled:opacity-30">Next <x-icon name="chevron-right" class="w-4 h-4" /></button>
                    </div>
                    <div x-show="pvBusy || pvPager.canvasBusy" x-cloak class="absolute z-20 top-2 right-3 text-xs text-gray-500 bg-white/90 rounded px-2 py-1 shadow">Updating preview…</div>
                    <div x-show="pvError" x-cloak class="absolute z-20 bottom-2 left-3 right-3 text-xs text-red-600 bg-white rounded px-2 py-1 shadow" x-text="pvError"></div>
                </div>
            </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function jobCreateForm(customers, departmentKeys, packageCatalog) {
            return {
                customers,
                packageCatalog,
                depts: {{ old('departments') ? json_encode(old('departments')) : '[]' }},
                perDept: Object.fromEntries(departmentKeys.map(k => [k, {
                    jobTypeCategory: 'client_project', productLine: '', segment: '', pkg: '', jobType: '', lineItems: [], bank: '', delivery: '', discount: '',
                    editNotes: false, notesLines: [], validDays: 14,
                }])),
                mobileTab: 'form',
                pvPager: window.createPdfPager(),
                previewDept: null, pvSrc: ['', ''], pvActive: 0, pvPending: null, pvBusy: false, pvError: '', pvTimer: null, pvSeq: 0,
                customerId: '{{ old('customer_id', request('customer_id')) }}',
                customerQuery: '',
                customerOpen: false,
                showInlineCustomer: false,
                inlineCustomer: { name: '', company: '', phone: '', email: '', address_line_1: '', address_line_2: '', postcode: '', city: '', state: '', source: 'referral' },
                inlinePaste: '',
                inlineSaving: false,
                inlineError: null,
                inlineExisting: null,
                formError: null,
                init() {
                    ['depts', 'perDept', 'customerId', 'previewDept'].forEach(k => this.$watch(k, () => this.schedulePreview()));
                    this.schedulePreview();
                },
                get activeDept() {
                    return this.depts.includes(this.previewDept) ? this.previewDept : (this.depts[0] || null);
                },
                previewPayload() {
                    const d = this.activeDept, pd = this.perDept[d];
                    const tier = pd.jobTypeCategory === 'product_sale' ? this.findPackageTier(d, pd.productLine, pd.segment, pd.pkg) : null;
                    const items = tier
                        ? [{ item: `${tier.pkg.label} (${tier.tier.pcs}pcs)`, desc: this.packageItemLines(d, pd.productLine, pd.segment, pd.pkg).join('\n'), qty: 1, price: tier.tier.price }]
                        : pd.lineItems.filter(r => (r.item || '').trim() !== '').map(r => ({ item: r.item, desc: r.desc || '', qty: r.qty === '' ? 0 : r.qty, price: r.price === '' ? 0 : r.price }));
                    const payload = {
                        customer_id: this.customerId || null, department: d, bank: pd.bank || null, title: pd.jobType || '',
                        estimation_value: tier ? tier.tier.price : null,
                        delivery: pd.delivery === '' ? 0 : pd.delivery, discount: pd.discount === '' ? 0 : pd.discount, items, valid_days: parseInt(pd.validDays) || 14,
                    };
                    const notes = pd.editNotes ? pd.notesLines.map(l => l.trim()).filter(l => l !== '').join('\n') : '';
                    if (notes !== '') payload.notes = notes;
                    return payload;
                },
                async toggleQuotationNotes(dept) {
                    const pd = this.perDept[dept];
                    if (pd.editNotes) { pd.editNotes = false; return; }
                    if (!pd.notesLines.length) {
                        try {
                            const res = await fetch(`{{ route('jobs.quotation-notes') }}?bank=${encodeURIComponent(pd.bank || '')}&department=${encodeURIComponent(dept)}`, { headers: { Accept: 'application/json' } });
                            if (res.ok) pd.notesLines = (await res.json()).notes;
                        } catch (e) { /* leave blank, staff can still type their own */ }
                    }
                    pd.editNotes = true;
                },
                schedulePreview() {
                    clearTimeout(this.pvTimer);
                    if (!this.activeDept) return;
                    this.pvTimer = setTimeout(() => this.refreshPreview(), 400);
                },
                async refreshPreview() {
                    const mine = ++this.pvSeq; this.pvBusy = true; this.pvError = '';
                    let res;
                    try {
                        res = await fetch('{{ route('jobs.quotation-preview') }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                            body: JSON.stringify(this.previewPayload()),
                        });
                    } catch (e) { if (mine === this.pvSeq) { this.pvBusy = false; this.pvError = 'Preview unavailable.'; } return; }
                    if (mine !== this.pvSeq) return;
                    this.pvBusy = false;
                    if (!res.ok) { try { const j = await res.json(); this.pvError = j.message || 'Preview unavailable.'; } catch (e) { this.pvError = 'Preview unavailable.'; } return; }
                    const blobUrl = URL.createObjectURL(await res.blob());
                    const src = blobUrl + '#toolbar=0&navpanes=0&view=FitH';
                    const t = this.pvPending ?? (1 - this.pvActive);
                    if (this.pvSrc[t]) URL.revokeObjectURL(this.pvSrc[t].split('#')[0]);
                    this.pvPending = t; this.pvSrc[t] = src;
                    this.pvPager.load(blobUrl, this.$refs.pvCanvas);
                },
                pvLoaded(i) {
                    if (this.pvPending !== i) return;
                    const old = this.pvSrc[this.pvActive];
                    this.pvActive = i; this.pvPending = null;
                    if (old && old !== this.pvSrc[i]) URL.revokeObjectURL(old.split('#')[0]);
                    this.pvSrc[1 - i] = '';
                },
                productLinesFor(dept) {
                    return this.packageCatalog[dept] || [];
                },
                segmentsFor(dept, lineKey) {
                    return this.productLinesFor(dept).find(l => l.key === lineKey)?.segments || [];
                },
                packageTierOptions(dept, lineKey, segmentKey) {
                    const seg = this.segmentsFor(dept, lineKey).find(s => s.key === segmentKey);
                    if (!seg) return [];
                    return seg.packages.flatMap(pkg => pkg.tiers.map(tier => ({
                        value: `${pkg.key}:${tier.pcs}`,
                        label: `${pkg.label}, ${tier.pcs}pcs (RM ${Number(tier.price).toFixed(2)})`,
                        pkg, tier,
                    })));
                },
                findPackageTier(dept, lineKey, segmentKey, value) {
                    if (!value) return null;
                    return this.packageTierOptions(dept, lineKey, segmentKey).find(o => o.value === value) || null;
                },
                packageItemLines(dept, lineKey, segmentKey, value) {
                    const found = this.findPackageTier(dept, lineKey, segmentKey, value);
                    if (!found) return [];
                    return found.pkg.items.map(i => i.replace('{pcs}', found.tier.pcs));
                },
                onPackageChange(dept) {
                    const pd = this.perDept[dept];
                    const tier = this.findPackageTier(dept, pd.productLine, pd.segment, pd.pkg);
                    if (tier) pd.jobType = `${tier.pkg.label} (${tier.tier.pcs}pcs)`;
                },
                get selectedCustomer() {
                    return this.customers.find(c => String(c.id) === String(this.customerId)) || null;
                },
                get filteredCustomers() {
                    const q = this.customerQuery.trim().toLowerCase();
                    if (!q) return this.customers;
                    return this.customers.filter(c =>
                        (c.customer_id || '').toLowerCase().includes(q) || (c.label || '').toLowerCase().includes(q)
                    );
                },
                selectCustomer(c) {
                    this.customerId = c.id;
                    this.customerQuery = '';
                    this.customerOpen = false;
                },
                async fillInlineAddress(event) {
                    const a = window.parseMyAddress(this.inlinePaste);
                    Object.keys(a).forEach((k) => { if (a[k]) this.inlineCustomer[k] = a[k]; });
                    if (a.postcode && (!a.city || !a.state)) {
                        try {
                            const res = await fetch(`/postcode-lookup/${a.postcode}`, { headers: { Accept: 'application/json' } });
                            if (res.ok) { const d = await res.json(); this.inlineCustomer.city ||= d.city; this.inlineCustomer.state ||= d.state; }
                        } catch (e) { /* keep what was parsed */ }
                    }
                    this.inlinePaste = '';
                    event.target.closest('details')?.removeAttribute('open');
                },
                async saveInlineCustomer() {
                    if (!this.inlineCustomer.name.trim()) return;
                    this.inlineSaving = true;
                    this.inlineError = null;
                    try {
                        const res = await fetch('{{ route('customers.store') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: JSON.stringify({ ...this.inlineCustomer, confirm_duplicate: !!this.inlineExisting }),
                        });
                        if (res.status === 409) {
                            const d = await res.json();
                            this.inlineExisting = d.existing;
                            throw new Error(d.message);
                        }
                        if (!res.ok) throw new Error('Failed to save customer.');
                        this.inlineExisting = null;
                        const customer = await res.json();
                        this.customers.push({
                            id: customer.id,
                            customer_id: customer.customer_id,
                            label: customer.company ? `${customer.name} (${customer.company})` : customer.name,
                        });
                        this.selectCustomer({ id: customer.id });
                        this.showInlineCustomer = false;
                        this.inlineCustomer = { name: '', company: '', phone: '', email: '', address_line_1: '', address_line_2: '', postcode: '', city: '', state: '', source: 'referral' };
                    } catch (e) {
                        this.inlineError = e.message;
                    }
                    this.inlineSaving = false;
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
