<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">New Job</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('jobs.store') }}" class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                    @csrf
                    <div class="sm:col-span-2">
                        <x-input-label for="customer_id" value="Customer *" />
                        <select id="customer_id" name="customer_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">— Select customer —</option>
                            @foreach ($customers as $c)
                                <option value="{{ $c->id }}" {{ old('customer_id') == $c->id ? 'selected' : '' }}>{{ $c->customer_id }} · {{ $c->customer_type === 'company' ? ($c->company ?: $c->name) : $c->name }}</option>
                            @endforeach
                        </select>
                        @if ($customers->isEmpty())
                            <p class="text-xs text-amber-600 mt-1">No customers yet — <a href="{{ route('customers.index') }}" class="underline">add one first</a>.</p>
                        @endif
                    </div>
                    <div>
                        <x-input-label value="Department *" />
                        <select name="department" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">— Select —</option>
                            @foreach ($departments as $key => $dept)
                                <option value="{{ $key }}" {{ old('department') === $key ? 'selected' : '' }}>{{ $dept['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label value="Job Type Category *" />
                        <select name="job_type_category" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            @foreach (config('jobs.job_types') as $key => $t)
                                <option value="{{ $key }}" {{ old('job_type_category') === $key ? 'selected' : '' }}>{{ $t['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="job_type" value="Job Name *" />
                        <x-text-input id="job_type" name="job_type" type="text" class="mt-1 block w-full" :value="old('job_type')" placeholder="e.g: Design & Print Roti Bakar" required />
                    </div>
                    <div>
                        <x-input-label value="Bank" />
                        <select name="bank" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">
                            <option value="">—</option>
                            @foreach (config('jobs.banks') as $key => $b)
                                <option value="{{ $key }}" {{ old('bank') === $key ? 'selected' : '' }}>{{ $b['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="estimation_value" value="Estimation Value (RM)" />
                        <x-text-input id="estimation_value" name="estimation_value" type="number" step="0.01" min="0" class="mt-1 block w-full" :value="old('estimation_value')" />
                    </div>
                    <div>
                        <x-input-label for="start_date" value="Start Date" />
                        <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full" :value="old('start_date')" />
                    </div>
                    <div>
                        <x-input-label for="deadline" value="Deadline" />
                        <x-text-input id="deadline" name="deadline" type="date" class="mt-1 block w-full" :value="old('deadline')" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="pic" value="PIC — optional, leave blank for department staff to self-assign" />
                        <x-text-input id="pic" name="pic" type="text" class="mt-1 block w-full" :value="old('pic')" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="notes" value="Notes" />
                        <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-sm">{{ old('notes') }}</textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-error :messages="$errors->all()" class="mt-1" />
                        <x-primary-button type="submit">Save Job</x-primary-button>
                        <a href="{{ route('jobs.index') }}" class="ml-2 text-xs text-gray-500 hover:underline">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
