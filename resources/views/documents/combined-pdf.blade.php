<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('documents.partials.style')
</head>
<body>
    @php
        $noLabel = ['quotation' => 'QNo#', 'proforma' => 'Invoice No#', 'invoice' => 'Invoice No#', 'receipt' => 'Receipt No#'][$type] ?? 'No#';
        $showBreakdown = in_array($type, ['quotation', 'proforma'], true);
        $subtotal = $amounts->sum();
        $delivery = $showBreakdown ? (float) $jobs->sum('delivery_amount') : 0;
        $discount = $showBreakdown ? (float) $jobs->sum('discount_amount') : 0;
        $total = $showBreakdown ? $subtotal + $delivery - $discount : $subtotal;
        $notes = \App\Support\DocumentData::defaultNotes($type, \App\Support\DocumentData::bank($jobs->first()), $jobs->first()->department);
    @endphp

    @include('documents.partials.header')
    @include('documents.partials.customer', ['customer' => \App\Support\DocumentData::customerBlock($customer)])

    <table class="grid">
        <thead>
            <tr>
                <th style="width:69.75pt;">Job ID</th>
                <th style="width:79.75pt;">Department</th>
                <th style="width:177.75pt;">Description</th>
                <th style="width:99.75pt;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($jobs as $j)
                <tr>
                    <td>{{ $j->job_id }}</td>
                    <td>{{ config('kretivco.departments.'.$j->department.'.label', $j->department) }}</td>
                    <td>{{ $j->job_type }}</td>
                    <td class="rt">RM {{ number_format($amounts[$j->id] ?? 0, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="tot">
        @if ($showBreakdown)
            <tr><td>Subtotal</td><td class="v">RM {{ number_format($subtotal, 2) }}</td></tr>
            @if ($delivery > 0)
                <tr><td>Delivery</td><td class="v">RM {{ number_format($delivery, 2) }}</td></tr>
            @endif
            @if ($discount > 0)
                <tr><td>Discount</td><td class="v">(RM {{ number_format($discount, 2) }})</td></tr>
            @endif
        @endif
        <tr class="grand"><td>Total (MYR)</td><td class="v">RM {{ number_format($total, 2) }}</td></tr>
    </table>

    @include('documents.partials.footer', ['notes' => $notes])
</body>
</html>
