<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">My Claims</h2>
    </x-slot>

    @php
        $tone = ['submitted' => 'bg-violet-50 text-violet-700', 'pending' => 'bg-amber-50 text-amber-700', 'approved' => 'bg-blue-50 text-blue-700', 'paid' => 'bg-green-50 text-green-700', 'rejected' => 'bg-gray-100 text-gray-500'];
        $label = ['submitted' => 'With Dept Head', 'pending' => 'With BOD', 'approved' => 'Approved, to be paid', 'paid' => 'Paid', 'rejected' => 'Not approved'];
    @endphp
    <div class="p-5 md:p-7 space-y-4 max-w-4xl">
        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm px-4 py-3">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('hr.claims.store') }}" enctype="multipart/form-data" class="k-card p-5 md:p-6">
            @csrf
            <h3 class="text-base font-bold text-gray-900 mb-1">New claim</h3>
            <p class="text-xs text-gray-400 mb-4">Snap the receipt with your phone or attach the file. It goes to your Dept Head, then BOD, then it's paid to your bank.</p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div><label class="text-xs text-gray-500">Date spent</label><input type="date" name="date" value="{{ old('date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required class="block w-full text-sm"></div>
                <div><label class="text-xs text-gray-500">Category</label>
                    <select name="category" class="block w-full text-sm">@foreach (\App\Models\Claim::CATEGORIES as $key => $c)<option value="{{ $key }}" @selected(old('category') === $key)>{{ $c }}</option>@endforeach</select></div>
                <div class="col-span-2"><label class="text-xs text-gray-500">What for</label><input type="text" name="description" value="{{ old('description') }}" required maxlength="255" placeholder="e.g. Parking at client site, EMS job" class="block w-full text-sm"></div>
                <div><label class="text-xs text-gray-500">Amount (RM)</label><input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount') }}" required class="block w-full text-sm"></div>
                <div class="col-span-2 sm:col-span-3"><label class="text-xs text-gray-500">Receipt</label><input type="file" name="receipt" accept="image/*,application/pdf" required class="block w-full text-sm"></div>
            </div>
            <button class="mt-4 text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#6D28D9] to-[#A855F7] hover:brightness-110">Send claim</button>
        </form>

        <div class="k-card overflow-hidden">
            @forelse ($claims as $c)
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3 border-b border-black/5 last:border-0 text-sm">
                    <div class="flex-1 min-w-[12rem]">
                        <p class="font-semibold text-gray-900">{{ $c->description }}</p>
                        <p class="text-xs text-gray-500">{{ $c->date->format('d M Y') }} · {{ \App\Models\Claim::CATEGORIES[$c->category] ?? $c->category }}
                            @if ($c->status === 'rejected' && $c->reject_reason) · {{ $c->reject_reason }} @endif</p>
                    </div>
                    <span class="font-semibold text-gray-900">RM {{ number_format($c->amount, 2) }}</span>
                    <span class="text-xs px-2 py-0.5 rounded-full {{ $tone[$c->status] ?? '' }}">{{ $label[$c->status] ?? $c->status }}</span>
                    @if ($c->receipt_path)<a href="{{ route('hr.claims.receipt', $c) }}" target="_blank" class="p-1 text-gray-400 hover:text-[#6D28D9]" title="Receipt"><x-icon name="paperclip" class="w-4 h-4" /></a>@endif
                    @if (in_array($c->status, ['submitted', 'pending'], true))
                        <form method="POST" action="{{ route('hr.claims.destroy', $c) }}" onsubmit="return confirm('Withdraw this claim?')">@csrf @method('DELETE')
                            <button class="text-xs text-gray-400 hover:text-rose-600">Withdraw</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="px-5 py-8 text-sm text-gray-400 text-center">No claims yet.</p>
            @endforelse
        </div>
    </div>
</x-app-layout>
