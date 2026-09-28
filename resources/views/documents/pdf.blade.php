<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('documents.partials.style')
</head>
<body>
    @include('documents.partials.header', ['type' => $doc['type'], 'noLabel' => $doc['no_label'], 'docNumber' => $doc['doc_number'], 'generatedBy' => $doc['by'], 'headerExtra' => $doc['header_extra']])
    @include('documents.partials.customer', ['customer' => $doc['customer']])

    <div class="title-line"><b>Title:</b> {{ $doc['title'] }}</div>
    @if ($doc['type'] === 'receipt' && $doc['invoice_number'])
        <div class="cust-line"><b>Payment for:</b> Invoice {{ $doc['invoice_number'] }}</div>
    @endif

    @if ($doc['type'] === 'receipt')
        {{-- Items list what was paid for; the money side is the invoice total,
             what was paid and what's left, so the figures always reconcile with
             the invoice (item amounts alone would leave out delivery/discount). --}}
        <table class="grid">
            <thead>
                <tr>
                    <th class="c" style="width:26pt;">No</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                @include('documents.partials.items', ['items' => $doc['items'], 'money' => false])
            </tbody>
        </table>

        <table class="tot receipt">
            <tr><td>Invoice Total</td><td class="v">RM {{ number_format($doc['invoice_total'], 2) }}</td></tr>
            @if (($doc['paid_before'] ?? 0) > 0)
                <tr><td>Paid Before</td><td class="v">(RM {{ number_format($doc['paid_before'], 2) }})</td></tr>
            @endif
            <tr><td>Payment Method</td><td class="v">{{ $doc['payment_method'] }}</td></tr>
            <tr><td><b>Amount Paid (MYR)</b></td><td class="v"><b>RM {{ number_format($doc['amount_paid'], 2) }}</b></td></tr>
            <tr class="grand"><td>Balance Due (MYR)</td><td class="v">RM {{ number_format($doc['balance_due'], 2) }}</td></tr>
        </table>
    @else
        <table class="grid">
            <thead>
                <tr>
                    <th class="c" style="width:26pt;">No</th>
                    <th>Description</th>
                    <th class="c" style="width:36pt;">Qty</th>
                    <th class="c" style="width:72pt;">Unit Price</th>
                    <th class="c" style="width:78pt;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @include('documents.partials.items', ['items' => $doc['items'], 'money' => true])
            </tbody>
        </table>

        <table class="tot">
            <tr><td>Subtotal</td><td class="v">RM {{ number_format($doc['subtotal'], 2) }}</td></tr>
            @if ($doc['delivery'] > 0)
                <tr><td>Delivery</td><td class="v">RM {{ number_format($doc['delivery'], 2) }}</td></tr>
            @endif
            @if ($doc['discount'] > 0)
                <tr><td>Discount</td><td class="v">(RM {{ number_format($doc['discount'], 2) }})</td></tr>
            @endif
            <tr class="grand"><td>Total (MYR)</td><td class="v">RM {{ number_format($doc['total'], 2) }}</td></tr>
        </table>
    @endif

    @include('documents.partials.footer', ['notes' => $doc['notes'], 'bank' => $doc['type'] === 'receipt' ? null : $doc['bank'], 'bankKey' => $doc['bank_key'], 'isReceipt' => $doc['type'] === 'receipt'])
</body>
</html>
