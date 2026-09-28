<div class="note-title">Note:</div>
<table class="notes-t">
    @foreach ($notes as $i => $note)
        <tr><td style="width:14pt;">{{ $i + 1 }}.</td><td>{{ $note }}</td></tr>
    @endforeach
</table>

@isset($bank)
    @if ($bank)
        {{-- Bank line on the left, DuitNow QR beside it on the right: stacking the
             QR underneath pushed the signature block onto a page of its own. --}}
        @php $qr = ['affin' => 'images/affin-duitnow-qr.png', 'mbb' => 'images/maybank-duitnow-qr.png'][$bankKey ?? ''] ?? null; @endphp
        <table class="pay-block pay-t">
            <tr>
                <td>
                    <div class="pay-title">Payment Detail:</div>
                    <div class="pay-line">{{ $bank['label'] }} | {{ $bank['name'] }} | {{ $bank['acct'] }}</div>
                </td>
                @if ($qr && file_exists(public_path($qr)))
                    <td class="pay-qr-cell">
                        <img src="{{ public_path($qr) }}" class="pay-qr">
                        <div class="pay-qr-caption">Scan to pay via DuitNow</div>
                    </td>
                @endif
            </tr>
        </table>
    @endif
@endisset

<table class="sign">
    <tr>
        <td style="width:228.5pt; position:relative;">
            <b>Issued by:</b>
            @if (file_exists(public_path('images/kretivco-stamp.png')))
                <img src="{{ public_path('images/kretivco-stamp.png') }}" class="stamp-img">
            @endif
            <div class="sign-line"></div>
        </td>
        @if ($receivedBy ?? false)
            <td>
                <b>Received by:</b>
                <div class="recv-line">Name:</div>
                <div class="recv-line">IC / Staff No:</div>
                <div class="recv-line">Date:</div>
                <div class="sign-line" style="margin-top:22pt;">Signature &amp; company stamp</div>
            </td>
        @elseif (! ($isReceipt ?? false))
            <td><b>Accepted by:</b><div class="sign-line"></div></td>
        @endif
    </tr>
</table>
