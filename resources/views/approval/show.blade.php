@php
    $job = $a->job;
    $customer = $job->customer;
    $dept = config("kretivco.departments.{$job->department}.label");
    $tone = ['sent' => 'bg-amber-50 text-amber-800 border-amber-200', 'approved' => 'bg-green-50 text-green-800 border-green-200', 'changes_requested' => 'bg-blue-50 text-blue-800 border-blue-200', 'superseded' => 'bg-gray-100 text-gray-600 border-gray-200'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Artwork approval · {{ $job->job_id }}</title>
    <link rel="icon" href="{{ asset('images/kretivco-logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-[#FBF6F3] text-gray-900">
    <header class="bg-white border-b border-[#F1E6E1]">
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center gap-3">
            <img src="{{ asset('images/kretivco-logo.png') }}" alt="" class="w-9 h-9">
            <div class="min-w-0">
                <p class="font-bold leading-tight">{{ config('kretivco.brand.name') }}</p>
                <p class="text-xs text-gray-500">{{ $dept }} · Artwork approval</p>
            </div>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 py-6 space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <div class="flex-1 min-w-[14rem]">
                <p class="text-xs text-gray-500">{{ $job->job_id }} · {{ $job->job_type }}</p>
                <h1 class="text-2xl font-extrabold leading-snug">{{ $a->item_name }}</h1>
                <p class="text-sm text-gray-500">Design {{ $a->design }} · Version {{ $a->version }} · sent {{ $a->created_at->format('d M Y, g:ia') }} by {{ $a->sent_by }}</p>
            </div>
            <span class="text-sm font-semibold px-3 py-1.5 rounded-full border {{ $tone[$a->status] }}">{{ \App\Models\Approval::STATUSES[$a->status] }}</span>
        </div>

        @if ($a->status === 'superseded')
            <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm">
                This version has been replaced by a newer one.
                @if ($latest->id !== $a->id)<a href="{{ $latest->url() }}" class="font-semibold text-[#C2185B] underline">Open version {{ $latest->version }}</a>@endif
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_320px] gap-4 items-start">
            <div class="space-y-3">
                @foreach ($files as $f)
                    @if (\App\Models\Approval::isImage($f))
                        <a href="{{ route('approval.file', [$a->token, $f['id']]) }}" target="_blank" class="block bg-white rounded-2xl border border-[#F1E6E1] p-2">
                            <img src="{{ route('approval.file', [$a->token, $f['id']]) }}" alt="{{ $f['name'] }}" class="w-full h-auto rounded-xl">
                        </a>
                    @else
                        <a href="{{ route('approval.file', [$a->token, $f['id']]) }}" target="_blank" class="flex items-center gap-3 bg-white rounded-2xl border border-[#F1E6E1] px-4 py-3 text-sm font-semibold text-[#C2185B]">
                            <x-icon name="file-text" class="w-5 h-5" /> {{ $f['name'] }} <span class="ml-auto text-xs text-gray-400 font-normal">Open</span>
                        </a>
                    @endif
                @endforeach
                <div class="rounded-xl bg-[#2B2B2B] text-[#FDE68A] text-xs leading-relaxed px-4 py-3 font-semibold">
                    **PLEASE DOUBLE TRIPLE CHECK ARTWORK, SPELLING, WORDING, SPACING AND DETAIL.<br>
                    **PLEASE ENSURE ALL HIDDEN LAYERS ARE REMOVED, WE ARE NOT RESPONSIBLE IF THEY APPEAR AFTER PRINTING.
                    <p class="text-white/80 font-normal mt-2">Att: Colour will be different 10% - 15% on colour screen and material printed.<br>Warna akan berbeza dari 10% - 15% mengikut warna screen dan material cetakan.<br>If any issue after confirm artwork and printing, we are not responsible.</p>
                </div>
            </div>

            <aside class="space-y-3">
                <div class="bg-white rounded-2xl border border-[#F1E6E1] p-4 text-sm">
                    <p class="text-xs uppercase tracking-wide text-gray-400 mb-2">Details</p>
                    <p class="font-semibold">{{ $customer?->company ?: $customer?->name }}</p>
                    @if ($a->details)
                        <div class="mt-2 text-gray-700 whitespace-pre-line leading-relaxed">{{ $a->details }}</div>
                    @endif
                </div>

                @if ($a->isOpen())
                    <form method="POST" action="{{ route('approval.respond', $a->token) }}" class="bg-white rounded-2xl border border-[#F1E6E1] p-4 space-y-3" x-data="{ mode: '{{ old('decision', 'approved') }}', ok: {{ old('confirm') ? 'true' : 'false' }} }">
                        @csrf
                        @if ($errors->any())
                            <p class="text-sm text-rose-600">{{ $errors->first() }}</p>
                        @endif
                        <div><label class="text-xs text-gray-500">Your name</label>
                            <input type="text" name="customer_name" value="{{ old('customer_name', $customer?->name) }}" required maxlength="120" class="block w-full text-sm rounded-lg border-gray-300"></div>
                        <div class="grid grid-cols-2 gap-1 p-1 rounded-xl bg-gray-100 text-sm font-semibold">
                            <button type="button" @click="mode = 'approved'" :class="mode === 'approved' ? 'bg-white shadow text-gray-900' : 'text-gray-500'" class="py-2 rounded-lg">Proceed</button>
                            <button type="button" @click="mode = 'changes_requested'" :class="mode === 'changes_requested' ? 'bg-white shadow text-gray-900' : 'text-gray-500'" class="py-2 rounded-lg">Request changes</button>
                        </div>
                        <input type="hidden" name="decision" :value="mode">
                        <div x-show="mode === 'approved'">
                            <label class="flex items-start gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="confirm" value="1" x-model="ok" class="mt-0.5 rounded">
                                <span>Saya mengesahkan ejaan, wording, spacing dan semua detail telah disemak, dan saya bertanggungjawab atas approval ini.<br><span class="text-xs text-gray-400">I confirm the spelling, wording, spacing and all details have been checked, and I take responsibility for this approval.</span></span>
                            </label>
                        </div>
                        <div x-show="mode === 'changes_requested'" x-cloak>
                            <label class="text-xs text-gray-500">What should we change?</label>
                            <textarea name="comment" rows="4" class="block w-full text-sm rounded-lg border-gray-300">{{ old('comment') }}</textarea>
                        </div>
                        <button class="w-full text-sm font-bold py-2.5 rounded-xl text-white disabled:opacity-40" :disabled="mode === 'approved' && !ok"
                                :class="mode === 'approved' ? 'bg-gradient-to-r from-[#16A34A] to-[#22C55E]' : 'bg-gradient-to-r from-[#2563EB] to-[#3B82F6]'"
                                x-text="mode === 'approved' ? 'Proceed with this artwork' : 'Send my changes'"></button>
                    </form>
                @elseif (in_array($a->status, ['approved', 'changes_requested'], true))
                    <div class="bg-white rounded-2xl border border-[#F1E6E1] p-4 text-sm space-y-1">
                        <p class="font-semibold">{{ $a->status === 'approved' ? 'Approved' : 'Changes requested' }} by {{ $a->customer_name }}</p>
                        <p class="text-gray-500">{{ $a->responded_at->format('d M Y, g:ia') }}</p>
                        @if ($a->comment)<p class="text-gray-700 whitespace-pre-line pt-1">{{ $a->comment }}</p>@endif
                        @if ($a->status === 'approved')
                            <a href="{{ route('approval.record', $a->token) }}" target="_blank" class="inline-block mt-2 text-sm font-semibold text-[#C2185B] underline">Download approval record (PDF)</a>
                        @else
                            <p class="text-gray-500 pt-1">We'll send you a new version soon.</p>
                        @endif
                    </div>
                @endif
            </aside>
        </div>
        <p class="text-xs text-gray-400 text-center pt-2">{{ config('kretivco.brand.name') }} · {{ config('kretivco.brand.phone') }} · {{ config('kretivco.brand.email') }}</p>
        {{-- PDPA notice for customers, in English and Bahasa Melayu. --}}
        <details class="max-w-2xl mx-auto text-xs text-gray-500">
            <summary class="text-center cursor-pointer hover:text-gray-700">Privacy Notice / Notis Privasi</summary>
            <div class="mt-3 space-y-3 bg-white rounded-2xl border border-[#F1E6E1] p-4 leading-relaxed">
                <p>We use your name, company, phone, email, address and SSM number to prepare your quotations, invoices, deliveries and artwork approvals, and to keep our accounts. When you approve or request changes here, we record the time, your IP address and browser, as proof of your decision. We share details only with those who need them for your order (such as couriers or production partners), our accountant, and authorities such as LHDN when the law requires. We keep records for 7 years as required by law. To see, correct or ask about your data, contact <a href="mailto:{{ config('kretivco.brand.email') }}" class="underline">{{ config('kretivco.brand.email') }}</a>.</p>
                <p>Kami menggunakan nama, syarikat, telefon, emel, alamat dan no. SSM anda untuk menyediakan sebut harga, invois, penghantaran dan kelulusan artwork, serta untuk rekod akaun kami. Apabila anda meluluskan atau meminta perubahan di sini, kami merekod masa, alamat IP dan pelayar anda sebagai bukti keputusan anda. Kami berkongsi butiran hanya dengan pihak yang memerlukannya untuk pesanan anda (seperti kurier atau rakan produksi), akauntan kami, dan pihak berkuasa seperti LHDN jika dikehendaki undang-undang. Rekod disimpan selama 7 tahun seperti yang dikehendaki undang-undang. Untuk melihat, membetulkan atau bertanya tentang data anda, hubungi <a href="mailto:{{ config('kretivco.brand.email') }}" class="underline">{{ config('kretivco.brand.email') }}</a>.</p>
            </div>
        </details>
    </main>
</body>
</html>
