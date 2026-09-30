@php $job = $a->job; $c = $job->customer; @endphp
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Approval Record</title>
<style>
    @page { margin: 36pt 42pt; }
    * { font-family: Helvetica, Arial, sans-serif; }
    body { color: #141414; font-size: 9.5pt; margin: 0; }
    table { border-collapse: collapse; width: 100%; }
    td { vertical-align: top; padding: 3pt 0; }
    .k { color: #6b6b6b; width: 130pt; }
    .box { border: 0.75pt solid #d0d0d0; border-radius: 4pt; padding: 10pt 12pt; margin-top: 12pt; }
    .ok { background: #ecfdf3; border-color: #86efac; }
    img.art { max-width: 100%; max-height: 330pt; margin-top: 8pt; border: 0.5pt solid #ddd; }
</style></head>
<body>
    <table><tr>
        <td style="width:52pt"><img src="{{ public_path(config('kretivco.brand.logo')) }}" style="width:44pt;height:44pt"></td>
        <td><div style="font-size:12pt;font-weight:bold">{{ config('kretivco.brand.name') }}</div><div style="color:#6b6b6b;font-size:8pt">{{ config('kretivco.brand.ssm') }} · {{ config('kretivco.brand.address_line_1') }}, {{ config('kretivco.brand.address_line_2') }}</div></td>
        <td style="text-align:right"><div style="font-size:18pt;font-weight:bold">APPROVAL RECORD</div><div style="color:#6b6b6b">{{ $job->job_id }} · v{{ $a->version }}</div></td>
    </tr></table>
    <div style="border-top:1.4pt solid #141414;margin:8pt 0 4pt"></div>

    <div class="box ok">
        <b>Approved by {{ $a->customer_name }}</b> on {{ $a->responded_at->format('d M Y, g:i a') }}.
        The customer confirmed that the spelling, wording, spacing and all details were checked, and took responsibility for this approval.
    </div>

    <table style="margin-top:10pt">
        <tr><td class="k">Customer</td><td>{{ $c?->company ? $c->name.' ('.$c->company.')' : $c?->name }}</td></tr>
        <tr><td class="k">Job</td><td>{{ $job->job_id }} · {{ $job->job_type }}</td></tr>
        <tr><td class="k">Item</td><td>{{ $a->item_name }} · Design {{ $a->design }} · Version {{ $a->version }}</td></tr>
        <tr><td class="k">Sent</td><td>{{ $a->created_at->format('d M Y, g:i a') }} by {{ $a->sent_by }}</td></tr>
        <tr><td class="k">Files</td><td>{{ collect($files)->pluck('name')->join(', ') }}</td></tr>
        <tr><td class="k">From</td><td>IP {{ $a->ip }}<br><span style="color:#6b6b6b;font-size:8pt">{{ $a->user_agent }}</span></td></tr>
    </table>

    @if ($a->details)
        <div class="box"><b>Details</b><div style="margin-top:4pt">{!! nl2br(e($a->details)) !!}</div></div>
    @endif

    @foreach ($files as $f)
        @if (\App\Models\Approval::isImage($f) && is_file(storage_path('app/public/'.$f['path'])))
            <img class="art" src="{{ storage_path('app/public/'.$f['path']) }}">
        @endif
    @endforeach
</body></html>
