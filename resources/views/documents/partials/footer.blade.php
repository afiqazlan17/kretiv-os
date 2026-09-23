<div class="note-title">Note:</div>
<table class="notes-t">
    @foreach ($notes as $i => $note)
        <tr><td style="width:14pt;">{{ $i + 1 }}.</td><td>{{ $note }}</td></tr>
    @endforeach
</table>
<div class="thanks">Thank you for your business!</div>
<table class="sign">
    <tr>
        <td style="width:228.5pt;"><b>Issued by:</b><div class="signed-name">{{ config('kretivco.brand.name', 'Kretivco Mediaworks') }}</div><div class="sign-rule"></div></td>
        <td><b>Accepted by:</b><div class="sign-line"></div></td>
    </tr>
</table>
