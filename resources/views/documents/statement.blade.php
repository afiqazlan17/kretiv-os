<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('documents.partials.style')
    <style>
        table.soa { width: 511pt; border-collapse: collapse; margin-top: 9.6pt; font-size: 8.6pt; }
        table.soa th { background: #f2f2f2; font-weight: bold; text-align: left; padding: 5pt 5pt; border-bottom: 0.75pt solid #000; }
        table.soa td { padding: 4.5pt 5pt; border-bottom: 0.5pt solid #e3e3e3; vertical-align: top; }
        table.soa .n { text-align: right; white-space: nowrap; }
        table.soa tr.total td { border-top: 0.9pt solid #000; border-bottom: none; font-weight: bold; }
        table.age { width: 511pt; border-collapse: collapse; margin-top: 14pt; }
        table.age td { width: 20%; text-align: center; padding: 6pt 4pt; border: 0.5pt solid #d6d6d6; }
        table.age .k { font-size: 7.4pt; color: #6b6b6b; text-transform: uppercase; letter-spacing: 0.5pt; }
        table.age .v { font-size: 10.5pt; font-weight: bold; margin-top: 2pt; }
        table.age td.due { background: #141414; color: #fff; border-color: #141414; }
        table.age td.due .k { color: #bdbdbd; }
    </style>
</head>
<body>
    @include('documents.partials.header', ['type' => 'statement', 'docTitle' => 'STATEMENT OF ACCOUNT', 'noLabel' => 'Customer', 'docNumber' => $customer->customer_id, 'generatedBy' => $by ?? config('kretivco.brand.name'), 'headerExtra' => null])
    @include('documents.partials.customer', ['customer' => \App\Support\DocumentData::customerBlock($customer)])

    <table class="age">
        <tr>
            @foreach (['0-30' => 'Current (0-30 days)', '31-60' => '31-60 days', '61-90' => '61-90 days', '90+' => 'Over 90 days'] as $k => $label)
                <td><div class="k">{{ $label }}</div><div class="v">RM {{ number_format($aging[$k], 2) }}</div></td>
            @endforeach
            <td class="due"><div class="k">Total due</div><div class="v">RM {{ number_format(max(0, $balance), 2) }}</div></td>
        </tr>
    </table>

    <table class="soa">
        <thead><tr><th style="width:52pt">Date</th><th style="width:78pt">Reference</th><th>Details</th><th class="n" style="width:62pt">Charged</th><th class="n" style="width:62pt">Paid</th><th class="n" style="width:66pt">Balance</th></tr></thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td>{{ $r['date']->format('d/m/Y') }}</td>
                    <td>{{ $r['ref'] }}</td>
                    <td>{{ $r['what'] }}<br><span style="color:#8a8a8a;font-size:7.4pt">{{ $r['job'] }}</span></td>
                    <td class="n">{{ $r['charge'] ? number_format($r['charge'], 2) : '' }}</td>
                    <td class="n">{{ $r['paid'] ? number_format($r['paid'], 2) : '' }}</td>
                    <td class="n">{{ number_format($r['balance'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;color:#8a8a8a;padding:14pt">No invoices or payments yet.</td></tr>
            @endforelse
            <tr class="total"><td colspan="5">Balance (MYR)</td><td class="n">RM {{ number_format($balance, 2) }}</td></tr>
        </tbody>
    </table>

    @php $banks = collect(config('kretivco.bank_details')); @endphp
    @include('documents.partials.footer', [
        'notes' => $balance > 0.005
            ? ['Please settle the balance to the account below and send proof of payment to '.config('kretivco.brand.email').' or WhatsApp '.config('kretivco.brand.phone').', quoting the invoice numbers.', 'If you have already paid, thank you and please ignore this statement.', 'Any query on this statement should be raised within 7 days.']
            : ['Thank you. Your account is fully settled.'],
        'bank' => $balance > 0.005 ? $banks->first() : null,
        'bankKey' => $banks->keys()->first(),
        'isReceipt' => true,
    ])
</body>
</html>
