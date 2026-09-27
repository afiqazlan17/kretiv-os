<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-bold text-2xl text-white leading-tight">Finance</h2>
        </div>
    </x-slot>
    <div class="p-6 space-y-4">
        <div class="k-card p-5 md:p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">{{ $title }}</h3>
            <form method="GET" class="flex items-end gap-3 mb-4"><div><label class="block text-xs text-gray-500 mb-1">Year</label>
                <select name="year" onchange="this.form.submit()" class="rounded-md border-gray-300 shadow-sm text-sm">@foreach ($years as $y)<option value="{{ $y }}" {{ $y === $year ? 'selected' : '' }}>{{ $y }}</option>@endforeach</select></div></form>
            <div class="rounded-lg border border-gray-100 p-4 mb-5 max-w-xs"><div class="text-[11px] font-semibold text-gray-400 uppercase">Total Sales ({{ $year }})</div><div class="text-2xl font-bold text-green-600 mt-1">RM {{ number_format($sales['total'], 2) }}</div></div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div><div class="text-xs font-bold text-gray-400 uppercase mb-2">By Department</div>
                    <table class="min-w-full divide-y divide-[#F0EDE9] text-sm"><thead><tr class="text-left"><th class="px-4 py-3 text-xs text-gray-500 uppercase">Department</th><th class="px-4 py-3 text-xs text-gray-500 uppercase text-right">No. of Invoices</th><th class="px-4 py-3 text-xs text-gray-500 uppercase text-right">Total</th></tr></thead><tbody class="divide-y divide-[#F0EDE9]">
                        @forelse ($sales['byDepartment'] as $dept => $row)<tr><td class="px-4 py-3">{{ \App\Http\Controllers\JobController::DEPT_CODES[$dept] ?? strtoupper($dept) }} {{ config("kretivco.departments.$dept.label") }}</td><td class="px-4 py-3 text-right">{{ $row['count'] }}</td><td class="px-4 py-3 text-right">RM {{ number_format($row['total'], 2) }}</td></tr>@empty<tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">No invoices.</td></tr>@endforelse
                    </tbody></table></div>
                <div><div class="text-xs font-bold text-gray-400 uppercase mb-2">By Customer</div>
                    <table class="min-w-full divide-y divide-[#F0EDE9] text-sm"><thead><tr class="text-left"><th class="px-4 py-3 text-xs text-gray-500 uppercase">Customer</th><th class="px-4 py-3 text-xs text-gray-500 uppercase text-right">No. of Invoices</th><th class="px-4 py-3 text-xs text-gray-500 uppercase text-right">Total</th></tr></thead><tbody class="divide-y divide-[#F0EDE9]">
                        @forelse ($sales['byCustomer'] as $name => $row)<tr><td class="px-4 py-3">{{ $name }}</td><td class="px-4 py-3 text-right">{{ $row['count'] }}</td><td class="px-4 py-3 text-right">RM {{ number_format($row['total'], 2) }}</td></tr>@empty<tr><td colspan="3" class="px-4 py-6 text-center text-gray-400">No invoices.</td></tr>@endforelse
                    </tbody></table></div>
            </div>
        </div>
    </div>
</x-app-layout>
