<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">New Lead</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('leads.store') }}"
                      x-data="{ existing: {{ $customers->isNotEmpty() ? 'true' : 'false' }} }"
                      class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                    @csrf

                    <div class="sm:col-span-2 flex gap-4 text-sm">
                        <label class="flex items-center gap-2"><input type="radio" x-model="existing" :value="true"> Existing customer</label>
                        <label class="flex items-center gap-2"><input type="radio" x-model="existing" :value="false"> New contact</label>
                    </div>

                    <div class="sm:col-span-2" x-show="existing" x-cloak>
                        <x-input-label value="Customer" />
                        <select name="customer_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">— Select customer —</option>
                            @foreach ($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->customer_id }} · {{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="!existing" x-cloak>
                        <x-input-label value="Name *" />
                        <x-text-input name="new_customer_name" type="text" class="mt-1 block w-full" :value="old('new_customer_name')" />
                    </div>
                    <div x-show="!existing" x-cloak>
                        <x-input-label value="Phone" />
                        <x-text-input name="new_customer_phone" type="text" class="mt-1 block w-full" :value="old('new_customer_phone')" />
                    </div>
                    <div class="sm:col-span-2" x-show="!existing" x-cloak>
                        <x-input-label value="Email" />
                        <x-text-input name="new_customer_email" type="email" class="mt-1 block w-full" :value="old('new_customer_email')" />
                        <p class="text-xs text-gray-400 mt-1">If phone/email matches an existing customer, that record is reused instead of duplicating.</p>
                    </div>

                    <div>
                        <x-input-label value="Department *" />
                        <select name="department" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">— Select —</option>
                            @foreach ($departments as $key => $dept)
                                <option value="{{ $key }}">{{ $dept['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="quotation_value" value="Quotation Value (RM)" />
                        <x-text-input id="quotation_value" name="quotation_value" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('quotation_value')" />
                    </div>
                    <div>
                        <x-input-label for="follow_up_date" value="Follow-up Date" />
                        <x-text-input id="follow_up_date" name="follow_up_date" type="date" class="mt-1 block w-full" :value="old('follow_up_date')" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="enquiry_notes" value="Enquiry Notes" />
                        <textarea id="enquiry_notes" name="enquiry_notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">{{ old('enquiry_notes') }}</textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-error :messages="$errors->all()" class="mt-1" />
                        <x-primary-button type="submit">Save Lead</x-primary-button>
                        <a href="{{ route('leads.index') }}" class="ml-2 text-xs text-gray-500 hover:underline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
