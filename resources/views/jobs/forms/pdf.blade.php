<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('documents.partials.style')
    <style>
        .sec { margin-top: 14pt; font-size: 9.6pt; font-weight: bold; padding-bottom: 3pt; border-bottom: 0.9pt solid #141414; }
        table.kv { width: 511pt; border-collapse: collapse; margin-top: 4pt; }
        table.kv td { padding: 5pt 0; border-bottom: 0.5pt solid #e6e6e6; vertical-align: top; font-size: 9pt; }
        table.kv td.k { width: 150pt; color: #6b6b6b; padding-right: 10pt; }
        table.rows { width: 511pt; border-collapse: collapse; margin-top: 5pt; font-size: 8.8pt; }
        table.rows th { background: #f2f2f2; text-align: left; padding: 4.5pt 5pt; border: 0.5pt solid #cfcfcf; }
        table.rows td { padding: 4.5pt 5pt; border: 0.5pt solid #dedede; vertical-align: top; }
        .box { display: inline-block; width: 9pt; height: 9pt; border: 0.8pt solid #141414; margin-right: 5pt; text-align: center; font-size: 8pt; line-height: 8.5pt; }
        .check { padding: 2.5pt 0; font-size: 9pt; }
        .blank { color: #b0b0b0; }
        table.sig { width: 511pt; border-collapse: collapse; margin-top: 26pt; page-break-inside: avoid; }
        table.sig td { width: 50%; vertical-align: top; padding-right: 20pt; font-size: 9pt; }
        .line { border-bottom: 0.6pt solid #141414; height: 30pt; margin-bottom: 3pt; }
    </style>
</head>
<body>
    @include('documents.partials.header', ['type' => 'form', 'docTitle' => strtoupper($def['label']), 'noLabel' => 'Job', 'docNumber' => $job->job_id, 'generatedBy' => $by, 'headerExtra' => $form ? ['Updated', $form->updated_at->format('d M Y')] : null])
    @include('documents.partials.customer', ['customer' => \App\Support\DocumentData::customerBlock($job->customer)])
    <div class="title-line"><b>Project:</b> {{ $job->job_type }}</div>

    @foreach ($def['sections'] as $title => $fields)
        <div class="sec">{{ $title }}</div>
        @php $simple = collect($fields)->reject(fn ($f) => in_array($f['type'], ['checklist', 'rows'], true)); @endphp
        @if ($simple->isNotEmpty())
            <table class="kv">
                @foreach ($simple as $name => $f)
                    @php $v = $data[$name] ?? null; @endphp
                    <tr><td class="k">{{ $f['label'] }}</td><td>
                        @if ($v === null || $v === '')<span class="blank">-</span>
                        @elseif ($f['type'] === 'date'){{ \Illuminate\Support\Carbon::parse($v)->format('d M Y') }}
                        @else{!! nl2br(e($v)) !!}@endif
                    </td></tr>
                @endforeach
            </table>
        @endif
        @foreach ($fields as $name => $f)
            @if (in_array($f['type'], ['checklist', 'rows'], true) && count($fields) > 1)
                <div style="margin-top:9pt;font-size:8.6pt;color:#6b6b6b">{{ $f['label'] }}</div>
            @endif
            @if ($f['type'] === 'checklist')
                <div style="margin-top:5pt">
                    @foreach ($f['items'] as $item)
                        <div class="check"><span class="box">{{ in_array($item, (array) ($data[$name] ?? []), true) ? 'X' : '' }}</span>{{ $item }}</div>
                    @endforeach
                </div>
            @elseif ($f['type'] === 'rows')
                <table class="rows">
                    <thead><tr>@foreach ($f['columns'] as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
                    <tbody>
                        @forelse ((array) ($data[$name] ?? []) as $row)
                            <tr>@foreach ($f['columns'] as $ci => $c)<td>{{ $row[$ci] ?? '' }}</td>@endforeach</tr>
                        @empty
                            <tr><td colspan="{{ count($f['columns']) }}" class="blank">Nothing added yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endif
        @endforeach
    @endforeach

    @if ($def['signoff'])
        <table class="sig">
            <tr>
                <td><b>Prepared by</b> ({{ config('kretivco.brand.name') }})<div class="line"></div>Name / date</td>
                <td><b>{{ $def['signoff'] }}</b><div class="line"></div>Name, position, company stamp / date</td>
            </tr>
        </table>
    @endif
</body>
</html>
