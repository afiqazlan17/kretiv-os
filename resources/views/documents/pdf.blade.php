<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #1A1025; }
        .header { display: flex; justify-content: space-between; margin-bottom: 24px; }
        .brand { font-size: 16px; font-weight: bold; }
        .muted { color: #6B6080; }
        .title { font-size: 20px; font-weight: bold; letter-spacing: 1px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { padding: 8px; border-bottom: 1px solid #E8E4ED; text-align: left; }
        th { background: #F5F3F7; font-size: 10px; text-transform: uppercase; }
        .text-right { text-align: right; }
        .totals { margin-top: 12px; width: 260px; float: right; }
        .totals td { border: none; padding: 4px 8px; }
        .notes { margin-top: 60px; font-size: 10px; color: #6B6080; }
        .notes li { margin-bottom: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="brand">{{ config('finance.brand.name') }} {{ config('finance.brand.ssm') }}</div>
            <div class="muted">{{ config('finance.brand.address_line_1') }}</div>
            <div class="muted">{{ config('finance.brand.address_line_2') }}</div>
            <div class="muted">{{ config('finance.brand.email') }} · {{ config('finance.brand.phone') }}</div>
        </div>
        <div style="text-align: right">
            <div class="title">{{ strtoupper($type) }}</div>
            <div>{{ $type === 'invoice' ? 'Invoice No#' : 'Receipt No#' }} {{ $docNumber }}</div>
            <div class="muted">{{ now()->format('d M Y') }}</div>
        </div>
    </div>

    <div>
        <strong>Bill To:</strong> {{ $job->customer?->name }}<br>
        @if ($job->customer?->company) {{ $job->customer->company }}<br> @endif
        @if ($job->customer?->phone) {{ $job->customer->phone }} @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>Job ID</th>
                <th>Description</th>
                <th class="text-right">Amount (RM)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $job->job_id }}</td>
                <td>{{ $job->job_type }}</td>
                <td class="text-right">{{ number_format($entry->amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr><td><strong>Total</strong></td><td class="text-right"><strong>RM {{ number_format($entry->amount, 2) }}</strong></td></tr>
    </table>

    <div style="clear: both"></div>

    @if ($type === 'invoice' && $job->bank)
        @php $bank = config("finance.bank_details.{$job->bank}"); @endphp
        <div class="notes">
            <ul>
                <li>Please make payment to {{ $bank['label'] }} {{ $bank['acct'] }} {{ $bank['name'] }}.</li>
                <li>Please indicate invoice number when making payment to us.</li>
                <li>Email us at {{ config('finance.brand.email') }}</li>
            </ul>
        </div>
    @else
        <div class="notes">
            <ul>
                <li>This receipt confirms payment received for the above job/invoice.</li>
                <li>Email us at {{ config('finance.brand.email') }}</li>
            </ul>
        </div>
    @endif
</body>
</html>
