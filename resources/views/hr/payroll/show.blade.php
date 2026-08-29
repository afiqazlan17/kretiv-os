<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Payroll — {{ $run->label() }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-md bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <div class="p-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase">Entries</h3>
                    <span class="inline-flex rounded-full px-2 py-1 text-xs {{ $run->status === 'posted' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                        {{ ucfirst($run->status) }}
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs text-gray-500 uppercase">
                                <th class="px-4 py-3">Staff</th>
                                <th class="px-4 py-3 text-right">Basic</th>
                                <th class="px-4 py-3 text-right">Allowances</th>
                                <th class="px-4 py-3 text-right">Deductions</th>
                                <th class="px-4 py-3 text-right">Net Pay</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($run->entries as $entry)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ $entry->user->name }}</td>
                                    <td class="px-4 py-3 text-right">RM {{ number_format($entry->basic_salary, 2) }}</td>
                                    <td class="px-4 py-3 text-right">RM {{ number_format($entry->allowances, 2) }}</td>
                                    <td class="px-4 py-3 text-right">RM {{ number_format($entry->deductions, 2) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold">RM {{ number_format($entry->net_pay, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No entries.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="font-semibold border-t border-gray-200">
                                <td class="px-4 py-3">Total</td>
                                <td></td><td></td><td></td>
                                <td class="px-4 py-3 text-right">RM {{ number_format($run->entries->sum('net_pay'), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            @if ($run->status !== 'posted')
                <form method="POST" action="{{ route('payroll.post', $run) }}" onsubmit="return confirm('Post this run to Finance? This locks it and posts one ledger entry per staff member.')">
                    @csrf
                    <x-primary-button type="submit">Post to Finance</x-primary-button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
