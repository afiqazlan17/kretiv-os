<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Kretiv.OS</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <p class="text-sm text-gray-500 mb-6">Welcome back, {{ auth()->user()->name }}. Pick a module.</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <a href="{{ route('jobs.index') }}" class="block bg-white shadow-sm sm:rounded-lg p-6 hover:shadow-md transition">
                    <h3 class="text-lg font-semibold text-gray-800">Jobs</h3>
                    <p class="text-sm text-gray-500 mt-1">Job queue, customers, vendors, leads.</p>
                </a>
                @if (auth()->user()->isBod() || auth()->user()->isDeptHead())
                    <a href="{{ route('finance.index') }}" class="block bg-white shadow-sm sm:rounded-lg p-6 hover:shadow-md transition">
                        <h3 class="text-lg font-semibold text-gray-800">Finance</h3>
                        <p class="text-sm text-gray-500 mt-1">Ledger, reports, invoices/receipts.</p>
                    </a>
                @endif
                <a href="{{ route('leaves.index') }}" class="block bg-white shadow-sm sm:rounded-lg p-6 hover:shadow-md transition">
                    <h3 class="text-lg font-semibold text-gray-800">HR</h3>
                    <p class="text-sm text-gray-500 mt-1">Staff, leave, attendance, payroll.</p>
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
