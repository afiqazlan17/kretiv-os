<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Payroll</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">
                    {{ session('success') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="rounded-md bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="text-sm font-semibold text-gray-500 uppercase mb-4">Draft a Payroll Run</h3>
                <form method="POST" action="{{ route('payroll.store') }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div>
                        <x-input-label value="Year" />
                        <input type="number" name="period_year" value="{{ now()->year }}" min="2020" required class="mt-1 rounded-md border-gray-300 shadow-sm text-sm w-24">
                    </div>
                    <div>
                        <x-input-label value="Month" />
                        <select name="period_month" required class="mt-1 rounded-md border-gray-300 shadow-sm text-sm">
                            @foreach (range(1, 12) as $m)
                                <option value="{{ $m }}" {{ $m === now()->month ? 'selected' : '' }}>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <x-primary-button type="submit">Draft Run</x-primary-button>
                </form>
                <p class="text-xs text-gray-400 mt-2">Drafts one entry per active staff member from their basic salary. Adjust allowances/deductions before posting.</p>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="p-4 border-b border-gray-100"><h3 class="text-sm font-semibold text-gray-500 uppercase">Runs</h3></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="px-4 py-3">Period</th>
                                <th class="px-4 py-3 text-center">Staff</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($runs as $run)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $run->label() }}</td>
                                    <td class="px-4 py-3 text-center">{{ $run->entries_count }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs {{ $run->status === 'posted' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                            {{ ucfirst($run->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <a href="{{ route('payroll.show', $run) }}" class="text-indigo-600 hover:underline text-xs">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">No payroll runs.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
