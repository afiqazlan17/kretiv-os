<div class="note-title">Note:</div>
<table class="notes-t">
    @foreach ($notes as $i => $note)
        <tr><td style="width:14pt;">{{ $i + 1 }}.</td><td>{{ $note }}</td></tr>
    @endforeach
</table>

@isset($bank)
    @if ($bank)
        <div class="pay-title">Payment Detail:</div>
        <div class="pay-line">{{ $bank['label'] }} | {{ $bank['name'] }} | {{ $bank['acct'] }}</div>
        @if (($bankKey ?? null) === 'affin')
            <img src="{{ public_path('images/affin-duitnow-qr.png') }}" class="pay-qr">
            <div class="pay-qr-caption">Scan to pay via DuitNow</div>
        @endif
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
        <td><b>Accepted by:</b><div class="sign-line"></div></td>
    </tr>
</table>
