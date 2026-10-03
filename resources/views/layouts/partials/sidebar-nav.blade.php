@php
    $user = auth()->user();
    // The same shell serves Jobs and Finance (finance.kretiv.co); Finance gets
    // its own name, green accent and menu.
    $inFinance = request()->routeIs('finance.*');
    $inHr = request()->routeIs('hr.*');
    $activeCls = $inHr ? 'bg-gradient-to-r from-[#EDE9FE] to-[#F5F3FF] text-[#6D28D9] font-semibold' : ($inFinance ? 'bg-gradient-to-r from-[#DCFCE7] to-[#ECFDF5] text-[#047857] font-semibold' : 'bg-gradient-to-r from-[#FFE4EC] to-[#FFF1E6] text-[#C2185B] font-semibold');
    $navItems = [
        ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'layout-dashboard'],
        ['key' => 'jobs', 'label' => 'Job', 'route' => 'jobs.index', 'pattern' => 'jobs.*', 'icon' => 'clipboard-list'],
        ['key' => 'customers', 'label' => 'Customers', 'route' => 'customers.index', 'icon' => 'users'],
        ['key' => 'deliveries', 'label' => 'Delivery', 'route' => 'deliveries.index', 'pattern' => 'deliveries.*', 'icon' => 'truck'],
        ['key' => 'vendors', 'label' => 'Vendors', 'route' => 'vendors.index', 'icon' => 'factory'],
        ['key' => 'items', 'label' => 'Items', 'route' => 'items.index', 'pattern' => 'items.*', 'icon' => 'package'],
        ['key' => 'portfolio', 'label' => 'Portfolio', 'route' => 'portfolio.index', 'pattern' => 'portfolio.*', 'icon' => 'image'],
        ['key' => 'reports', 'label' => 'Reports', 'route' => 'reports.index', 'icon' => 'chart-line', 'roles' => ['bod', 'dept_head']],
    ];
@endphp

{{-- Logo --}}
<div class="flex justify-between items-center px-5 pt-6 pb-4">
    <div class="flex items-center gap-2.5">
        @if ($inHr)
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#7C3AED] to-[#C084FC] flex items-center justify-center text-white shadow-[0_6px_16px_-6px_rgba(124,58,237,0.6)]"><x-icon name="users" class="w-5 h-5" /></div>
            <div class="text-2xl font-extrabold tracking-tight bg-gradient-to-r from-[#6D28D9] to-[#A855F7] bg-clip-text text-transparent">HR</div>
        @elseif ($inFinance)
            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#059669] to-[#34D399] flex items-center justify-center text-white shadow-[0_6px_16px_-6px_rgba(5,150,105,0.6)]"><x-icon name="wallet" class="w-5 h-5" /></div>
            <div class="text-2xl font-extrabold tracking-tight bg-gradient-to-r from-[#047857] to-[#10B981] bg-clip-text text-transparent">Finance</div>
        @else
        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#E91E63] to-[#FCB03C] flex items-center justify-center text-white shadow-[0_6px_16px_-6px_rgba(233,30,99,0.6)]"><x-icon name="briefcase-business" class="w-5 h-5" /></div>
        <div class="text-2xl font-extrabold tracking-tight bg-gradient-to-r from-[#E91E63] to-[#F57C00] bg-clip-text text-transparent">Jobs</div>
        @endif
    </div>
    <div class="flex items-center gap-1">
        {{-- Fixed panel: the sidebar clips anything that overflows it. --}}
        <x-notification-bell panel="fixed top-4 left-4 md:left-[15.5rem]" />
        <button @click="mobileOpen = false" class="md:hidden text-gray-400 text-2xl leading-none">×</button>
    </div>
</div>

{{-- Kretiv OS module switcher --}}
<div class="flex flex-wrap gap-1.5 px-4 pb-4 text-[11px]">
    <a href="{{ route('os.home') }}" class="px-2.5 py-1 rounded-full bg-[#FFF1EC] text-[#C2185B] font-medium hover:bg-[#FFE3DA]">← KretivOS</a>
    {{-- The other modules this person can open (the current one is left out). --}}
    @php
        $inJobs = ! $inFinance && ! $inHr;
        $switch = array_filter([
            'Jobs' => ! $inJobs && $user->canAccess('jobs') ? route('dashboard') : null,
            'Finance' => ! $inFinance && $user->canAccess('finance') && $user->canManageFinance() ? route('finance.index') : null,
            'HR' => ! $inHr && $user->canAccess('hr') ? route('hr.home') : null,
        ]);
    @endphp
    @foreach ($switch as $label => $url)
        <a href="{{ $url }}" class="px-2.5 py-1 rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-800">{{ $label }}</a>
    @endforeach
</div>

{{-- Navigation --}}
<nav class="flex-1 px-3 py-1 overflow-y-auto">
    @php
        $newJobCount = $user->canAccess('jobs') ? \App\Models\Job::where('status', \App\Models\Job::STATUS_NEW)->where('archived', false)
            ->when(! $user->isBod(), fn ($q) => $q->whereIn('department', $user->visibleDepartments()))->count() : 0;
        $jobSubmenu = [
            ['key' => 'mine', 'label' => 'My Jobs', 'icon' => 'user-check'],
            ['key' => 'queue', 'label' => 'New Jobs', 'icon' => 'list-todo', 'badge' => $newJobCount],
            ['key' => 'all', 'label' => 'All Jobs', 'icon' => 'clipboard-list'],
            ['key' => 'aging', 'label' => 'Untouched Jobs', 'icon' => 'hourglass'],
        ];
        $activeJobView = request()->routeIs('jobs.index') ? (request()->query('view', 'mine')) : null;
        $financeSubmenu = ['finance.index' => ['Overview', '']] + collect(\App\Http\Controllers\FinanceReportController::REPORTS)
            ->reject(fn ($r, $k) => in_array($k, \App\Http\Controllers\FinanceReportController::COMPANY_REPORTS, true) && ! $user->seesCompanyFinance())
            ->mapWithKeys(fn ($r, $k) => [$k => $r])->all();
        $activeFinanceReport = request()->routeIs('finance.reports') ? request()->route('report') : (request()->routeIs('finance.index') ? 'finance.index' : null);
    @endphp
    @if ($inHr)
        @php
            $hrNav = [
                ['section' => 'Me'],
                ['label' => 'My Profile', 'url' => route('hr.home'), 'icon' => 'user-check', 'active' => request()->routeIs('hr.home')],
                ['label' => 'My Attendance', 'url' => route('hr.attendance.mine'), 'icon' => 'clock', 'active' => request()->routeIs('hr.attendance.mine')],
                ['label' => 'My Leave', 'url' => route('hr.leave'), 'icon' => 'calendar', 'active' => request()->routeIs('hr.leave')],
                ['label' => 'My Claims', 'url' => route('hr.claims'), 'icon' => 'receipt', 'active' => request()->routeIs('hr.claims')],
                ['label' => 'My Payslips', 'url' => route('hr.payslips'), 'icon' => 'receipt', 'active' => request()->routeIs('hr.payslips')],
                ['section' => 'Company'],
                ['label' => 'Memos & Announcements', 'url' => route('hr.announcements'), 'icon' => 'megaphone', 'active' => request()->routeIs('hr.announcements*'), 'badge' => \App\Http\Controllers\Hr\AnnouncementController::unreadFor($user, 99)->count()],
                ['label' => 'Organisation Chart', 'url' => route('hr.org-chart'), 'icon' => 'network', 'active' => request()->routeIs('hr.org-chart')],
                ['label' => 'Departments', 'url' => route('hr.departments'), 'icon' => 'building-2', 'active' => request()->routeIs('hr.departments')],
                ['label' => 'Public Holidays', 'url' => route('hr.holidays'), 'icon' => 'calendar', 'active' => request()->routeIs('hr.holidays')],
            ];
            if (\App\Services\AttendanceService::canViewTeam($user)) {
                $pendingOt = \App\Models\Attendance::with('user')->where('ot_status', 'pending')->get()
                    ->filter(fn ($a) => \App\Services\AttendanceService::canApproveOt($user, $a->user))->count();
                $hrNav[] = ['section' => 'Team'];
                $hrNav[] = ['label' => 'Team Attendance', 'url' => route('hr.attendance.team'), 'icon' => 'users', 'active' => request()->routeIs('hr.attendance.team')];
                $hrNav[] = ['label' => 'Overtime', 'url' => route('hr.overtime'), 'icon' => 'timer', 'active' => request()->routeIs('hr.overtime'), 'badge' => $pendingOt];
                $pendingLeave = \App\Models\LeaveRequest::with('user')->where('status', 'pending')->get()
                    ->filter(fn ($l) => \App\Services\AttendanceService::isApproverFor($user, $l->user))->count();
                $hrNav[] = ['label' => 'Leave', 'url' => route('hr.leave.team'), 'icon' => 'calendar', 'active' => request()->routeIs('hr.leave.team'), 'badge' => $pendingLeave];
                $hrNav[] = ['label' => 'Leave Calendar', 'url' => route('hr.leave.calendar'), 'icon' => 'calendar', 'active' => request()->routeIs('hr.leave.calendar')];
                if ($user->isDeptHead() || $user->isBod()) {
                    $claimsToCheck = \App\Models\Claim::with('user')->where('status', 'submitted')->get()
                        ->filter(fn ($c) => $c->user && \App\Services\AttendanceService::isApproverFor($user, $c->user))->count();
                    $hrNav[] = ['label' => 'Claims', 'url' => route('hr.claims.team'), 'icon' => 'receipt', 'active' => request()->routeIs('hr.claims.team'), 'badge' => $claimsToCheck];
                }
            }
            if ($user->canManageHr()) {
                $hrNav[] = ['section' => 'Manage'];
                $hrNav[] = ['label' => 'Staff', 'url' => route('hr.staff.index'), 'icon' => 'users', 'active' => request()->routeIs('hr.staff.index', 'hr.staff.show')];
                $hrNav[] = ['label' => 'Payroll', 'url' => route('hr.payroll'), 'icon' => 'banknote', 'active' => request()->routeIs('hr.payroll*')];
                $hrNav[] = ['label' => 'EA Forms', 'url' => route('hr.ea'), 'icon' => 'file-text', 'active' => request()->routeIs('hr.ea*')];
                $hrNav[] = ['label' => 'Audit Log', 'url' => route('hr.audit'), 'icon' => 'history', 'active' => request()->routeIs('hr.audit')];
                $hrNav[] = ['label' => 'New Joiner', 'url' => route('hr.staff.create'), 'icon' => 'user-plus', 'active' => request()->routeIs('hr.staff.create')];
                $hrNav[] = ['label' => 'Profile Requests', 'url' => route('hr.requests'), 'icon' => 'inbox', 'active' => request()->routeIs('hr.requests'), 'badge' => \App\Models\ProfileChangeRequest::where('status', 'pending')->where('user_id', '!=', $user->id)->count()];
            }
        @endphp
        @foreach ($hrNav as $item)
            @if (isset($item['section']))
                <div class="px-3 pt-3 pb-1.5 text-[10px] font-semibold uppercase tracking-wider text-gray-400">{{ $item['section'] }}</div>
            @else
                <a href="{{ $item['url'] }}" class="flex items-center gap-3 h-10 px-3 mb-0.5 rounded-xl text-[13px] whitespace-nowrap transition-colors {{ $item['active'] ? $activeCls : 'text-gray-600 font-medium hover:bg-[#F5F3FF] hover:text-gray-900' }}">
                    <x-icon :name="$item['icon']" class="w-[18px] h-[18px] shrink-0 {{ $item['active'] ? '' : 'text-gray-400' }}" />
                    <span class="truncate">{{ $item['label'] }}</span>
                    @if (! empty($item['badge']))<span class="ml-auto text-[10px] font-bold px-1.5 py-0.5 rounded-full bg-[#7C3AED] text-white">{{ $item['badge'] }}</span>@endif
                </a>
            @endif
        @endforeach
    @elseif ($inFinance)
        @php
            // Line icon per report.
            $financeIcons = ['general-ledger' => 'book-open', 'trial-balance' => 'scale', 'balance-sheet' => 'receipt', 'cash-book' => 'banknote', 'aging' => 'hourglass', 'bank-reconciliation' => 'landmark', 'sales' => 'chart-line', 'installments' => 'calendar'];
            $financeNav = [
                ['label' => 'Dashboard', 'url' => route('finance.index'), 'icon' => 'layout-dashboard', 'active' => request()->routeIs('finance.index')],
                ['label' => 'Collections', 'url' => route('finance.collections'), 'icon' => 'hourglass', 'active' => request()->routeIs('finance.collections')],
            ];
            if ($user->seesCompanyFinance()) {
                $dueRecurring = \App\Models\RecurringExpense::all()->filter->isDue()->count();
                // BOD acts on pending (approve) and approved (pay); Finance only pays.
                $pendingClaims = \App\Models\Claim::whereIn('status', $user->isBod() ? ['pending', 'approved'] : ['approved'])->count();
                $financeNav[] = ['label' => 'Recurring', 'url' => route('finance.recurring'), 'icon' => 'repeat', 'active' => request()->routeIs('finance.recurring'), 'badge' => $dueRecurring];
                $financeNav[] = ['label' => 'Claims', 'url' => route('finance.claims'), 'icon' => 'receipt', 'active' => request()->routeIs('finance.claims'), 'badge' => $pendingClaims];
                $financeNav[] = ['label' => 'Assets', 'url' => route('finance.assets'), 'icon' => 'package', 'active' => request()->routeIs('finance.assets')];
                $financeNav[] = ['label' => 'Tax Summary', 'url' => route('finance.tax'), 'icon' => 'scale', 'active' => request()->routeIs('finance.tax')];
                $financeNav[] = ['label' => 'Bank Import', 'url' => route('finance.bank-import'), 'icon' => 'landmark', 'active' => request()->routeIs('finance.bank-import')];
                $financeNav[] = ['label' => 'Accountant Pack', 'url' => route('finance.accountant'), 'icon' => 'download', 'active' => request()->routeIs('finance.accountant')];
                $financeNav[] = ['label' => 'Audit Log', 'url' => route('finance.audit'), 'icon' => 'history', 'active' => request()->routeIs('finance.audit')];
            }
            $reportsStart = count($financeNav);
            foreach ($financeSubmenu as $key => [$label]) {
                if ($key === 'finance.index') { continue; }
                $financeNav[] = ['label' => $label, 'url' => route('finance.reports', $key), 'icon' => $financeIcons[$key] ?? 'file-text', 'active' => $activeFinanceReport === $key];
            }
        @endphp
        @foreach ($financeNav as $i => $item)
            @if ($i === $reportsStart)
                <div class="px-3 pt-4 pb-1.5 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Reports</div>
            @endif
            <a href="{{ $item['url'] }}" class="flex items-center gap-3 h-10 px-3 mb-0.5 rounded-xl text-[13px] whitespace-nowrap transition-colors {{ $item['active'] ? $activeCls : 'text-gray-600 font-medium hover:bg-[#F0FDF4] hover:text-gray-900' }}">
                <x-icon :name="$item['icon']" class="w-[18px] h-[18px] shrink-0 {{ $item['active'] ? '' : 'text-gray-400' }}" />
                <span class="truncate">{{ $item['label'] }}</span>
                @if (! empty($item['badge']))
                    <span class="ml-auto text-[11px] font-bold rounded-full px-2 py-0.5 bg-amber-100 text-amber-800">{{ $item['badge'] }}</span>
                @endif
            </a>
        @endforeach
    @else
    @foreach ($navItems as $item)
        @continue(isset($item['roles']) && ! in_array($user->role, $item['roles'], true))
        @php $active = request()->routeIs($item['pattern'] ?? $item['route']); @endphp
        <a href="{{ $item['key'] === 'jobs' ? route('jobs.index') : route($item['route']) }}"
           class="flex items-center gap-3 h-10 px-3 mb-0.5 rounded-xl text-[13px] whitespace-nowrap transition-colors {{ $active ? 'bg-gradient-to-r from-[#FFE4EC] to-[#FFF1E6] text-[#C2185B] font-semibold' : 'text-gray-600 font-medium hover:bg-[#FFF5F1] hover:text-gray-900' }}">
            <x-icon :name="$item['icon']" class="w-[18px] h-[18px] shrink-0 {{ $active ? '' : 'text-gray-400' }}" />
            <span>{{ $item['label'] }}</span>
            @if (! empty($item['badge']))
                <span class="ml-auto text-[11px] font-bold rounded-full px-2 py-0.5 bg-amber-100 text-amber-800">{{ $item['badge'] }}</span>
            @endif
        </a>
        @if ($item['key'] === 'jobs' && $active)
            <div class="py-0.5 pb-1.5">
                @foreach ($jobSubmenu as $sub)
                    <a href="{{ route('jobs.index', ['view' => $sub['key']]) }}"
                       class="flex items-start gap-2 min-h-[34px] py-[7px] pl-11 pr-2.5 text-xs leading-tight rounded-lg {{ $activeJobView === $sub['key'] ? 'text-[#C2185B] font-semibold' : 'text-gray-500 font-normal hover:text-gray-800' }}">
                        <x-icon :name="$sub['icon']" class="w-3.5 h-3.5 shrink-0 mt-px" />
                        <span>{{ $sub['label'] }}</span>
                        @if (! empty($sub['badge']))<span class="ml-auto text-[10px] font-bold px-1.5 rounded-full bg-[#E91E63] text-white">{{ $sub['badge'] }}</span>@endif
                    </a>
                @endforeach
            </div>
        @endif
    @endforeach
    @endif
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
                    {{ config('kretivco.roles.'.$user->role.'.full', config('kretivco.roles.'.$user->role.'.label', $user->role)) }}
                </div>
                @if ($user->title)
                    <div class="text-[10px] text-gray-500 leading-snug">{{ $user->title }}</div>
                @endif
            </div>
        </div>
        <a href="{{ route('password.change') }}" class="mt-2.5 flex items-center justify-center gap-1.5 w-full py-1.5 text-[11px] font-medium text-gray-500 hover:text-[#C2185B]"><x-icon name="key-round" class="w-3.5 h-3.5" /> Change password</a>
        <a href="{{ route('privacy.staff') }}" class="flex items-center justify-center gap-1.5 w-full py-1 text-[11px] font-medium text-gray-500 hover:text-[#C2185B]"><x-icon name="shield-check" class="w-3.5 h-3.5" /> Privacy Notice</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="mt-1 w-full py-1.5 text-[11px] font-medium text-gray-500 bg-white border border-[#F0DDD5] rounded-lg hover:text-[#C2185B] hover:border-[#F4B6C8]">
                Log Out
            </button>
        </form>
    </div>
@endif
