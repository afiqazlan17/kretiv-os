<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-2xl text-white leading-tight">Team Claims</h2>
    </x-slot>

    <div class="p-5 md:p-7 space-y-4 max-w-4xl">
        @if (session('success'))
            <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 text-sm px-4 py-3">{{ session('success') }}</div>
        @endif
        <p class="text-xs text-gray-400">
            @if (auth()->user()->isBod())
                Claims from departments whose Dept Head hasn't checked them yet. Approving here sends them straight to Finance for payment.
            @else
                Check that each claim is for real company work. Once you pass it on, BOD approves it in Finance.
            @endif
        </p>

        @forelse ($claims as $c)
            <div class="k-card p-5" x-data="{ reject: false }">
                <div class="flex flex-wrap items-start gap-3">
                    <div class="flex-1 min-w-[12rem]">
                        <p class="font-semibold text-gray-900">{{ $c->claimant_name }} · RM {{ number_format($c->amount, 2) }}</p>
                        <p class="text-sm text-gray-600">{{ $c->description }}</p>
                        <p class="text-xs text-gray-400">{{ $c->date->format('d M Y') }} · {{ \App\Models\Claim::CATEGORIES[$c->category] ?? $c->category }}</p>
                    </div>
                    @if ($c->receipt_path)
                        <a href="{{ route('hr.claims.receipt', $c) }}" target="_blank" class="text-xs font-semibold text-[#6D28D9] inline-flex items-center gap-1"><x-icon name="paperclip" class="w-3.5 h-3.5" /> Receipt</a>
                    @endif
                </div>
                <form method="POST" action="{{ route('hr.claims.verify', $c) }}" class="flex flex-wrap items-center gap-2 mt-3">
                    @csrf
                    <input x-show="reject" x-cloak type="text" name="reject_reason" maxlength="255" placeholder="Reason (shown to the staff member)" class="flex-1 min-w-[12rem] text-sm">
                    <button x-show="!reject" name="decision" value="ok" class="text-sm font-semibold px-4 py-2 rounded-xl text-white bg-gradient-to-r from-[#6D28D9] to-[#A855F7]">{{ auth()->user()->isBod() ? 'Approve' : 'Looks right, send to BOD' }}</button>
                    <button x-show="!reject" type="button" @click="reject = true" class="text-sm px-4 py-2 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50">Reject</button>
                    <button x-show="reject" x-cloak name="decision" value="rejected" class="text-sm font-semibold px-4 py-2 rounded-xl bg-rose-600 text-white">Confirm reject</button>
                </form>
            </div>
        @empty
            <div class="k-card px-5 py-8 text-sm text-gray-400 text-center">No claims to check.</div>
        @endforelse
    </div>
</x-app-layout>
