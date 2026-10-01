@php $T = fn ($s) => \App\Support\DocLang::t($s, $lang ?? null); @endphp
<div class="note-title">{{ $T('Note:') }}</div>
<table class="notes-t">
    @php $n = 0; @endphp
    @foreach ($notes as $note)
        @if (\App\Support\DocumentData::isNoteHeading($note))
            <tr><td colspan="2" class="note-head">{{ $note }}</td></tr>
        @else
            <tr><td style="width:{{ count($notes) > 9 ? 18 : 14 }}pt;">{{ ++$n }}.</td><td>{{ $note }}</td></tr>
        @endif
    @endforeach
</table>

{{-- Notes may run on to the next page; the payment line and the signatures stay together. --}}
<div class="doc-end">
@isset($bank)
    @if ($bank)
        {{-- Bank line on the left, DuitNow QR beside it on the right: stacking the
             QR underneath pushed the signature block onto a page of its own. --}}
        @php $qr = config('kretivco.bank_qr')[$bankKey ?? ''] ?? null; @endphp
        <table class="pay-block pay-t">
            <tr>
                <td>
                    <div class="pay-title">{{ $T('Payment Detail:') }}</div>
                    <div class="pay-line">{{ $bank['label'] }} | {{ $bank['name'] }} | {{ $bank['acct'] }}</div>
                </td>
                @if ($qr && file_exists(public_path($qr)))
                    <td class="pay-qr-cell">
                        <img src="{{ public_path($qr) }}" class="pay-qr">
                        <div class="pay-qr-caption">{{ $T('Scan to pay via DuitNow') }}</div>
                    </td>
                @endif
            </tr>
        </table>
    @endif
@endisset

<table class="sign">
    <tr>
        <td style="width:228.5pt; position:relative;">
            <b>{{ $T('Issued by:') }}</b>
            @if (($stamp = config('kretivco.brand.stamp')) && file_exists(public_path($stamp)))
                <img src="{{ public_path($stamp) }}" class="stamp-img">
            @endif
            <div class="sign-line"></div>
        </td>
        @if ($receivedBy ?? false)
            <td>
                <b>{{ $T('Received by:') }}</b>
                <div class="recv-line">{{ $T('Name:') }}</div>
                <div class="recv-line">{{ $T('IC / Staff No:') }}</div>
                <div class="recv-line">{{ $T('Date') }}:</div>
                <div class="sign-line" style="margin-top:22pt;">{{ $T('Signature & company stamp') }}</div>
            </td>
        @elseif (! ($isReceipt ?? false))
            <td><b>{{ $T('Accepted by:') }}</b><div class="sign-line"></div></td>
        @endif
    </tr>
</table>
</div>
