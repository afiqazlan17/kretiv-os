@php
    $user = auth()->user();
    $navItems = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'layout-dashboard'],
        ['key' => 'jobs', 'label' => 'Job', 'route' => 'jobs.index', 'pattern' => 'jobs.*', 'icon' => 'clipboard-list'],
        ['key' => 'customers', 'label' => 'Customers', 'route' => 'customers.index', 'icon' => 'users'],
        ['key' => 'vendors', 'label' => 'Vendors', 'route' => 'vendors.index', 'icon' => 'factory'],
        ['key' => 'items', 'label' => 'Items', 'route' => 'items.index', 'pattern' => 'items.*', 'icon' => 'package'],
        ['key' => 'finance', 'label' => 'Finance', 'route' => 'finance.index', 'pattern' => 'finance.*', 'icon' => 'wallet', 'roles' => ['bod', 'dept_head', 'finance']],
        ['key' => 'reports', 'label' => 'Reports', 'route' => 'reports.index', 'icon' => 'chart-line', 'roles' => ['bod', 'dept_head']],
        ['key' => 'departments', 'label' => 'Departments', 'route' => 'departments.index', 'icon' => 'building-2'],
        ['key' => 'settings', 'label' => 'Settings', 'route' => 'settings.index', 'icon' => 'settings', 'roles' => ['bod']],
    ];
@endphp

{{-- Logo --}}
<div class="flex justify-between items-center px-5 pt-6 pb-4">
    <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#E91E63] to-[#FCB03C] flex items-center justify-center text-white shadow-[0_6px_16px_-6px_rgba(233,30,99,0.6)]"><x-icon name="briefcase-business" class="w-5 h-5" /></div>
        <div class="text-2xl font-extrabold tracking-tight bg-gradient-to-r from-[#E91E63] to-[#F57C00] bg-clip-text text-transparent">Jobs</div>
    </div>
    <button @click="mobileOpen = false" class="md:hidden text-gray-400 text-2xl leading-none">×</button>
</div>

{{-- Kretiv OS module switcher --}}
<div class="flex flex-wrap gap-1.5 px-4 pb-4 text-[11px]">
    <a href="{{ route('os.home') }}" class="px-2.5 py-1 rounded-full bg-[#FFF1EC] text-[#C2185B] font-medium hover:bg-[#FFE3DA]">← Kretiv OS</a>
    @if ($user->canAccess('finance'))
        <a href="{{ route('finance.index') }}" class="px-2.5 py-1 rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-800">Finance</a>
    @endif
</div>

{{-- Navigation --}}
<nav class="flex-1 px-3 py-1 overflow-y-auto">
    @php
        $jobSubmenu = [
            ['key' => 'queue', 'label' => 'Job Queue', 'icon' => 'list-todo'],
            ['key' => 'aging', 'label' => 'Aging Job', 'icon' => 'hourglass'],
            ['key' => 'mine', 'label' => 'My Jobs', 'icon' => 'user-check'],
        ];
        $activeJobView = request()->routeIs('jobs.index') ? (request()->query('view', 'queue')) : null;
        $financeSubmenu = ['finance.index' => ['Overview', '']] + collect(\App\Http\Controllers\FinanceReportController::REPORTS)->mapWithKeys(fn ($r, $k) => [$k => $r])->all();
        $activeFinanceReport = request()->routeIs('finance.reports') ? request()->route('report') : (request()->routeIs('finance.index') ? 'finance.index' : null);
    @endphp
    @foreach ($navItems as $item)
        @continue(isset($item['roles']) && ! in_array($user->role, $item['roles'], true))
        @php $active = request()->routeIs($item['pattern'] ?? $item['route']); @endphp
        <a href="{{ $item['key'] === 'jobs' ? route('jobs.index') : route($item['route']) }}"
           class="flex items-center gap-3 h-10 px-3 mb-0.5 rounded-xl text-[13px] whitespace-nowrap transition-colors {{ $active ? 'bg-gradient-to-r from-[#FFE4EC] to-[#FFF1E6] text-[#C2185B] font-semibold' : 'text-gray-600 font-medium hover:bg-[#FFF5F1] hover:text-gray-900' }}">
            <x-icon :name="$item['icon']" class="w-[18px] h-[18px] shrink-0 {{ $active ? '' : 'text-gray-400' }}" />
            <span>{{ $item['label'] }}</span>
        </a>
        @if ($item['key'] === 'jobs' && $active)
            <div class="py-0.5 pb-1.5">
                @foreach ($jobSubmenu as $sub)
                    <a href="{{ route('jobs.index', ['view' => $sub['key']]) }}"
                       class="flex items-start gap-2 min-h-[34px] py-[7px] pl-11 pr-2.5 text-xs leading-tight rounded-lg {{ $activeJobView === $sub['key'] ? 'text-[#C2185B] font-semibold' : 'text-gray-500 font-normal hover:text-gray-800' }}">
                        <x-icon :name="$sub['icon']" class="w-3.5 h-3.5 shrink-0 mt-px" />
                        <span>{{ $sub['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif
        @if ($item['key'] === 'finance' && $active)
            <div class="py-0.5 pb-1.5">
                @php
                    // Line icons per report; the emoji in FinanceReportController::REPORTS are ignored here.
                    $financeIcons = ['finance.index' => 'layout-dashboard', 'general-ledger' => 'book-open', 'trial-balance' => 'scale', 'balance-sheet' => 'receipt', 'cash-book' => 'banknote', 'aging' => 'hourglass', 'bank-reconciliation' => 'landmark', 'sales' => 'chart-line', 'installments' => 'calendar'];
                @endphp
                @foreach ($financeSubmenu as $key => [$label, $icon])
                    <a href="{{ $key === 'finance.index' ? route('finance.index') : route('finance.reports', $key) }}"
                       class="flex items-start gap-2 min-h-[34px] py-[7px] pl-11 pr-2.5 text-xs leading-tight rounded-lg {{ $activeFinanceReport === $key ? 'text-[#C2185B] font-semibold' : 'text-gray-500 font-normal hover:text-gray-800' }}">
                        <x-icon :name="$financeIcons[$key] ?? 'file-text'" class="w-3.5 h-3.5 shrink-0 mt-px" />
                        <span>{{ $label }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    @endforeach
</nav>

{{-- User profile --}}
@if ($user)
    <div class="m-3 p-3 rounded-2xl bg-[#FFF7F3] border border-[#F6E6DF]">
        <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-[#E91E63] to-[#FCB03C] text-white flex items-center justify-center text-[13px] font-bold shrink-0">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-xs font-semibold text-gray-800 truncate">{{ $user->name }}</div>
                <div class="text-[10px] font-medium mt-0.5" style="color: {{ config('kretivco.roles.'.$user->role.'.color', '#3A86FF') }}">
                    {{ config('kretivco.roles.'.$user->role.'.label', $user->role) }}{{ $user->title ? ' | '.$user->title : '' }}
                </div>
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="mt-2.5 w-full py-1.5 text-[11px] font-medium text-gray-500 bg-white border border-[#F0DDD5] rounded-lg hover:text-[#C2185B] hover:border-[#F4B6C8]">
                Log Out
            </button>
        </form>
    </div>
@endif
