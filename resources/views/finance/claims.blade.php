<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Staff Claims</h2>
    </x-slot>

    <div class="p-5 md:p-7 space-y-4">
        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3">{{ $errors->first() }}</div>
        @endif

        {{-- Status tabs --}}
        <div class="flex flex-wrap gap-2">
            @foreach (['pending' => 'Pending', 'approved' => 'Approved, to pay', 'paid' => 'Paid', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
                <a href="{{ route('finance.claims', ['status' => $key]) }}"
                   class="inline-flex items-center gap-1.5 text-sm font-semibold px-3.5 py-1.5 rounded-full {{ $status === $key ? 'bg-[#047857] text-white' : 'bg-white border border-[#E7EFEA] text-gray-600 hover:bg-[#F0FDF4]' }}">
                    {{ $label }}
                    @if ($key !== 'all' && ($counts[$key] ?? 0) > 0)
                        <span class="text-[11px] rounded-full px-1.5 {{ $status === $key ? 'bg-white/25' : 'bg-gray-100' }}">{{ $counts[$key] }}</span>
                    @endif
                </a>
            @endforeach
        </div>

        <div class="k-card overflow-hidden">
            <div class="divide-y divide-[#F0EDE9]">
                @forelse ($claims as $claim)
                    <div class="px-5 py-4" x-data="{ act: null }">
                        <div class="flex flex-wrap items-start gap-3">
                            <div class="flex-1 min-w-[200px]">
                                <div class="font-semibold text-gray-800">{{ $claim->claimant_name }} <span class="font-normal text-gray-400">· {{ $claim->date->format('d M Y') }}</span></div>
                                <div class="text-sm text-gray-600">{{ $claim->description }}</div>
                                <div class="text-xs text-gray-400 mt-0.5">
                                    {{ \App\Models\Claim::CATEGORIES[$claim->category] ?? $claim->category }}{{ $claim->department ? ' · '.config("kretivco.departments.{$claim->department}.label") : '' }}
                                    @if ($claim->receipt_path)
                                        · <a href="{{ route('finance.claims.receipt', $claim) }}" target="_blank" class="inline-flex items-center gap-0.5 text-[#047857] hover:underline"><x-icon name="paperclip" class="w-3 h-3" /> Receipt</a>
                                    @else
                                        · <span class="text-amber-600">No receipt</span>
                                    @endif
                                </div>
                                @if ($claim->status === 'rejected')
                                    <div class="text-xs text-red-600 mt-1">Rejected by {{ $claim->decided_by }}: {{ $claim->reject_reason }}</div>
                                @elseif ($claim->decided_by)
                                    <div class="text-xs text-gray-400 mt-1">{{ $claim->status === 'paid' ? 'Paid from '.config("kretivco.banks.{$claim->paid_bank}.label").', approved' : 'Approved' }} by {{ $claim->decided_by }}</div>
                                @endif
                            </div>
                            <div class="text-right">
                                <div class="font-bold text-gray-900 whitespace-nowrap">RM {{ number_format($claim->amount, 2) }}</div>
                                @php $badge = ['pending' => 'bg-amber-50 text-amber-700', 'approved' => 'bg-blue-50 text-blue-700', 'paid' => 'bg-green-50 text-green-700', 'rejected' => 'bg-red-50 text-red-700'][$claim->status]; @endphp
                                <span class="inline-block mt-1 text-[11px] font-semibold rounded-full px-2.5 py-0.5 {{ $badge }}">{{ \App\Models\Claim::STATUSES[$claim->status] }}</span>
                            </div>
                        </div>

                        @if ($claim->status === 'pending' && ! auth()->user()->isBod())
                            <div class="text-xs text-amber-700 mt-3">Waiting for BOD approval.</div>
                        @elseif ($claim->status === 'pending')
                            <div class="flex items-center gap-2 mt-3">
                                <form method="POST" action="{{ route('finance.claims.approve', $claim) }}">@csrf
                                    <button type="submit" class="text-xs font-semibold px-3 py-1.5 rounded-lg text-white bg-[#047857] hover:brightness-110">Approve</button>
                                </form>
                                <button type="button" @click="act = act === 'reject' ? null : 'reject'" class="text-xs font-semibold px-3 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50">Reject</button>
                                <form method="POST" action="{{ route('finance.claims.destroy', $claim) }}" class="ml-auto" onsubmit="return confirm('Remove this claim? Use this only for a claim recorded by mistake.')">@csrf @method('DELETE')
                                    <button type="submit" class="text-xs text-gray-400 hover:text-red-500">Remove</button>
                                </form>
                            </div>
                            <form method="POST" action="{{ route('finance.claims.reject', $claim) }}" x-show="act === 'reject'" x-cloak class="flex flex-wrap items-end gap-2 mt-2">
                                @csrf
                                <input type="text" name="reject_reason" placeholder="Reason (shown to the staff member)" required class="flex-1 min-w-[220px] text-sm">
                                <button type="submit" class="text-xs font-semibold px-3 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">Confirm reject</button>
                            </form>
                        @elseif ($claim->status === 'approved')
                            <form method="POST" action="{{ route('finance.claims.pay', $claim) }}" class="flex flex-wrap items-end gap-2 mt-3">
                                @csrf
                                <div><label class="text-xs text-gray-500">Paid from</label>
                                    <select name="bank" class="block text-sm">
                                        @foreach (config('kretivco.banks') as $key => $bank)
                                            <option value="{{ $key }}">{{ $bank['label'] }}</option>
                                        @endforeach
                                    </select></div>
                                <div><label class="text-xs text-gray-500">Date paid</label>
                                    <input type="date" name="date" value="{{ now()->toDateString() }}" class="block text-sm"></div>
                                <button type="submit" class="text-xs font-semibold px-3.5 py-2 rounded-lg text-white bg-gradient-to-r from-[#047857] to-[#10B981] hover:brightness-110">Mark paid</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-gray-500 text-sm">No {{ $status === 'all' ? '' : strtolower(\App\Models\Claim::STATUSES[$status] ?? '').' ' }}claims.</div>
                @endforelse
            </div>
        </div>

        {{-- Until staff can submit their own from HR, BOD/Finance can record a claim for them here. --}}
        <div class="k-card p-5 md:p-6" x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }">
            <button type="button" @click="open = !open" class="inline-flex items-center gap-2 text-sm font-semibold text-[#047857]">
                <span class="w-7 h-7 rounded-lg bg-[#DCFCE7] flex items-center justify-center"><x-icon name="plus" class="w-4 h-4" /></span> Record a claim for a staff member
            </button>
            <form method="POST" action="{{ route('finance.claims.store') }}" enctype="multipart/form-data" x-show="open" x-cloak class="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
                @csrf
                <div><label class="text-xs text-gray-500">Staff *</label>
                    <select name="user_id" required class="block w-full text-sm">
                        <option value="">Select staff</option>
                        @foreach ($staff as $s)
                            <option value="{{ $s->id }}" @selected((string) old('user_id') === (string) $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select></div>
                <div><label class="text-xs text-gray-500">Date *</label>
                    <input type="date" name="date" value="{{ old('date', now()->toDateString()) }}" required class="block w-full text-sm"></div>
                <div><label class="text-xs text-gray-500">Amount (RM) *</label>
                    <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required class="block w-full text-sm"></div>
                <div><label class="text-xs text-gray-500">Category *</label>
                    <select name="category" class="block w-full text-sm">
                        @foreach (\App\Models\Claim::CATEGORIES as $key => $label)
                            <option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>
                        @endforeach
                    </select></div>
                <div class="sm:col-span-2"><label class="text-xs text-gray-500">What for *</label>
                    <input type="text" name="description" value="{{ old('description') }}" placeholder="Parking at client site, Shah Alam" required class="block w-full text-sm"></div>
                <div><label class="text-xs text-gray-500">Department</label>
                    <select name="department" class="block w-full text-sm">
                        <option value="">Company-wide</option>
                        @foreach (config('kretivco.departments') as $key => $dept)
                            <option value="{{ $key }}" @selected(old('department') === $key)>{{ $dept['label'] }}</option>
                        @endforeach
                    </select></div>
                <div class="sm:col-span-2"><label class="text-xs text-gray-500">Receipt (take a photo or attach a file)</label>
                    <input type="file" name="receipt" accept="image/*,.pdf" class="block w-full text-sm text-gray-500 file:mr-2 file:rounded-lg file:border-0 file:bg-[#DCFCE7] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-[#047857]"></div>
                <div class="sm:col-span-3"><button type="submit" class="text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#047857] to-[#10B981] hover:brightness-110">Save claim</button></div>
            </form>
        </div>
    </div>
</x-app-layout>
