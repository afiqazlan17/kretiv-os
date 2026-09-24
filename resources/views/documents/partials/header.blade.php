<div class="hdr">
    <img src="{{ public_path('images/kretivco-logo.png') }}" style="position:absolute; left:5pt; top:0; width:72pt; height:72pt;">
    <div class="abs brand" style="left:90.5pt; top:{{ $t['brand'] ?? 9.4 }}pt;">{{ config('kretivco.brand.name') }}</div>
    <div class="abs sub" style="left:90.5pt; top:{{ $t['sub'] ?? 24.8 }}pt;">{{ config('kretivco.brand.ssm') }}</div>
    <div class="abs sub" style="left:90.5pt; top:{{ ($t['sub'] ?? 24.8) + 11.8 }}pt;">{{ config('kretivco.brand.address_line_1') }}</div>
    <div class="abs sub" style="left:90.5pt; top:{{ ($t['sub'] ?? 24.8) + 23.6 }}pt;">{{ config('kretivco.brand.address_line_2') }}</div>
    <div class="contact-line" style="top:{{ ($t['sub'] ?? 24.8) + 35.4 }}pt;">Tel/WA: {{ config('kretivco.brand.phone') }} / {{ config('kretivco.brand.phone2') }} | {{ config('kretivco.brand.email') }}</div>
    <div class="abs doc-title r" style="right:0; top:{{ $t['title'] ?? 1.2 }}pt;">{{ $type === 'proforma' ? 'PROFORMA INVOICE' : strtoupper($type) }}</div>
    <div class="abs r" style="right:-1.9pt; top:{{ $t['meta'] ?? 25.4 }}pt;"><b>{{ $noLabel }}:</b> {{ $docNumber }}</div>
    <div class="abs r" style="right:-0.5pt; top:{{ ($t['meta'] ?? 25.4) + 15 }}pt;"><b>Date:</b> {{ now()->format('d/m/y') }}</div>
    <div class="abs r" style="right:-0.4pt; top:{{ ($t['meta'] ?? 25.4) + 30 }}pt;"><b>By:</b> {{ $generatedBy }}</div>
</div>
