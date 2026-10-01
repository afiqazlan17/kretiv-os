<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ basename(\App\Support\DocumentData::fileName($doc['doc_number'], $doc['customer']['company'], $doc['customer']['name']), '.pdf') }}</title>
    @include('documents.partials.style')
</head>
<body>
    @php $lang = $doc['lang'] ?? 'en'; $T = fn ($s) => \App\Support\DocLang::t($s, $lang); @endphp
    @include('documents.partials.header', ['type' => $doc['type'], 'docTitle' => $doc['doc_title'], 'noLabel' => $doc['no_label'], 'docNumber' => $doc['doc_number'], 'generatedBy' => $doc['by'], 'headerExtra' => $doc['header_extra']])
    @include('documents.partials.customer', ['customer' => $doc['customer']])

    <div class="title-line"><b>{{ $T('Title:') }}</b> {{ $doc['title'] }}</div>
    @if (! empty($doc['po_number']))
        <div class="cust-line"><b>{{ $T('PO No:') }}</b> {{ $doc['po_number'] }}</div>
    @endif
    @if ($doc['type'] === 'receipt' && $doc['invoice_number'])
        <div class="cust-line"><b>{{ $T('Payment for:') }}</b> {{ $T('Invoice') }} {{ $doc['invoice_number'] }}</div>
    @elseif ($doc['type'] === 'credit_note' && $doc['invoice_number'])
        <div class="cust-line"><b>{{ $T('Against:') }}</b> {{ $T('Invoice') }} {{ $doc['invoice_number'] }}</div>
    @endif

    @if ($doc['type'] === 'delivery')
        <table class="grid">
            <thead>
                <tr>
                    <th class="c" style="width:26pt;">{{ $T('No') }}</th>
                    <th>{{ $T('Description') }}</th>
                    <th class="c" style="width:60pt;">{{ $T('Qty') }}</th>
                </tr>
            </thead>
            <tbody>
                @include('documents.partials.items', ['items' => $doc['items'], 'money' => false, 'qtyOnly' => true])
            </tbody>
        </table>
    @elseif ($doc['type'] === 'receipt')
        {{-- Items list what was paid for; the money side is the invoice total,
             what was paid and what's left, so the figures always reconcile with
             the invoice (item amounts alone would leave out delivery/discount). --}}
        <table class="grid">
            <thead>
                <tr>
                    <th class="c" style="width:26pt;">{{ $T('No') }}</th>
                    <th>{{ $T('Description') }}</th>
                </tr>
            </thead>
            <tbody>
                @include('documents.partials.sections', ['cols' => 1, 'money' => false, 'images' => false])
            </tbody>
        </table>

        <table class="tot receipt">
            <tr><td>{{ $T($doc['invoice_number'] ? 'Invoice Total' : 'Quotation Total') }}</td><td class="v">RM {{ number_format($doc['invoice_total'], 2) }}</td></tr>
            @if (($doc['paid_before'] ?? 0) > 0)
                <tr><td>{{ $T('Paid Before') }}</td><td class="v">(RM {{ number_format($doc['paid_before'], 2) }})</td></tr>
            @endif
            @if (! empty($doc['paid_on']))
                <tr><td>{{ $T('Payment Date') }}</td><td class="v">{{ $doc['paid_on'] }}</td></tr>
            @endif
            <tr><td>{{ $T('Payment Method') }}</td><td class="v">{{ $T($doc['payment_method']) }}</td></tr>
            <tr><td><b>{{ $T('Amount Paid (MYR)') }}</b></td><td class="v"><b>RM {{ number_format($doc['amount_paid'], 2) }}</b></td></tr>
            <tr class="grand"><td>{{ $T('Balance Due (MYR)') }}</td><td class="v">RM {{ number_format($doc['balance_due'], 2) }}</td></tr>
        </table>
    @else
        <table class="grid">
            <thead>
                <tr>
                    <th class="c" style="width:26pt;">{{ $T('No') }}</th>
                    <th>{{ $T('Description') }}</th>
                    <th class="c" style="width:36pt;">{{ $T('Qty') }}</th>
                    <th class="c" style="width:72pt;">{{ $T('Unit Price') }}</th>
                    <th class="c" style="width:78pt;">{{ $T('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @include('documents.partials.sections', ['cols' => 4, 'money' => true])
            </tbody>
        </table>

        <table class="tot {{ ($doc['deposit_paid'] ?? 0) > 0 || $doc['type'] === 'credit_note' ? 'wide' : '' }}">
            @if ($doc['type'] !== 'credit_note')
                <tr><td>{{ $T('Subtotal') }}</td><td class="v">RM {{ number_format($doc['subtotal'], 2) }}</td></tr>
            @endif
            @if ($doc['delivery'] > 0)
                <tr><td>{{ $T('Delivery') }}</td><td class="v">RM {{ number_format($doc['delivery'], 2) }}</td></tr>
            @endif
            @if ($doc['discount'] > 0)
                <tr><td>{{ $T('Discount') }}</td><td class="v">(RM {{ number_format($doc['discount'], 2) }})</td></tr>
            @endif
            @if ($doc['type'] === 'credit_note')
                <tr class="grand"><td>{{ $T('Credit Amount (MYR)') }}</td><td class="v">RM {{ number_format($doc['total'], 2) }}</td></tr>
            @else
                <tr class="grand"><td>{{ $T('Total (MYR)') }}</td><td class="v">RM {{ number_format($doc['total'], 2) }}</td></tr>
            @endif
            @if (($doc['deposit_paid'] ?? 0) > 0)
                <tr><td>{{ $T('Less: Deposit Received') }}</td><td class="v">(RM {{ number_format($doc['deposit_paid'], 2) }})</td></tr>
                <tr class="grand"><td>{{ $T('Balance Due (MYR)') }}</td><td class="v">RM {{ number_format($doc['balance_due'], 2) }}</td></tr>
            @endif
        </table>
    @endif

    @include('documents.partials.footer', [
        'notes' => $doc['notes'],
        'bank' => in_array($doc['type'], ['receipt', 'delivery', 'credit_note'], true) ? null : $doc['bank'],
        'bankKey' => $doc['bank_key'],
        'isReceipt' => in_array($doc['type'], ['receipt', 'credit_note'], true),
        'receivedBy' => $doc['type'] === 'delivery',
    ])
</body>
</html>
