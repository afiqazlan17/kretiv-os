@php
    $s = $slip->snapshot;
    $rm = fn ($v) => number_format((float) $v, 2);
    $types = config('kretivco.allowance_types');
    $earnings = [['Basic salary', $slip->basic]];
    foreach ($slip->allowances ?? [] as $a) { $earnings[] = [($types[$a['type']] ?? ucfirst($a['type'])).' allowance', $a['amount']]; }
    if ($slip->ot_pay > 0) { $earnings[] = ['Overtime ('.rtrim(rtrim(number_format($slip->ot_hours, 2), '0'), '.').' h)', $slip->ot_pay]; }
    $deductions = array_values(array_filter([
        ['EPF', $slip->epf_employee], ['SOCSO', $slip->socso_employee], ['EIS', $slip->eis_employee], ['PCB (income tax)', $slip->pcb],
    ], fn ($d) => $d[1] > 0));
    $rows = max(count($earnings), count($deductions), 1);
    $employer = [['EPF', $slip->epf_employer], ['SOCSO', $slip->socso_employer], ['EIS', $slip->eis_employer]];
    [$otFrom, $otTo] = $run->otWindow();
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Payslip {{ $run->month()->format('F Y') }}</title>
<style>
    @page { margin: 34pt 40pt; }
    * { font-family: Helvetica, Arial, sans-serif; }
    body { color: #141414; font-size: 9pt; margin: 0; }
    table { border-collapse: collapse; width: 100%; }
    td { vertical-align: top; }
    .muted { color: #6b6b6b; }
    .label { font-size: 7pt; letter-spacing: 0.8pt; text-transform: uppercase; color: #8a8a8a; }
    .brand { font-size: 13pt; font-weight: bold; }
    .title { font-size: 22pt; font-weight: bold; letter-spacing: -0.3pt; }
    .rule { border-top: 1.5pt solid #141414; margin: 14pt 0 12pt; }
    .panel { background: #f5f5f5; border-radius: 6pt; padding: 10pt 12pt; }
    .info td { padding: 2.5pt 0; }
    .lines th { font-size: 7pt; letter-spacing: 0.8pt; text-transform: uppercase; color: #8a8a8a; text-align: left; font-weight: normal; padding: 0 0 6pt; border-bottom: 0.75pt solid #d9d9d9; }
    .lines td { padding: 6pt 0; border-bottom: 0.5pt solid #ececec; }
    .lines .amt { text-align: right; white-space: nowrap; }
    .lines .gap { width: 22pt; border-bottom: none; }
    .lines tr.total td { border-bottom: none; border-top: 0.75pt solid #141414; font-weight: bold; padding-top: 7pt; }
    .net { background: #141414; color: #fff; border-radius: 6pt; padding: 12pt 14pt; }
    .net .big { font-size: 20pt; font-weight: bold; }
    .small { font-size: 7.5pt; }
</style>
</head>
<body>
    <table>
        <tr>
            <td style="width:58pt"><img src="{{ public_path('images/kretivco-logo.png') }}" style="width:50pt;height:50pt"></td>
            <td>
                <div class="brand">{{ config('kretivco.brand.name') }}</div>
                <div class="muted small">{{ config('kretivco.brand.ssm') }}</div>
                <div class="muted small">{{ config('kretivco.brand.address_line_1') }}, {{ config('kretivco.brand.address_line_2') }}</div>
            </td>
            <td style="text-align:right">
                <div class="title">Payslip</div>
                <div class="muted">{{ $run->month()->format('F Y') }}</div>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    <table class="panel info">
        <tr>
            <td style="width:50%">
                <div class="label">Employee</div>
                <div style="font-size:11pt;font-weight:bold;margin:2pt 0 4pt">{{ $s['name'] }}</div>
                <table>
                    @if ($s['title'])<tr><td class="muted" style="width:70pt">Position</td><td>{{ $s['title'] }}</td></tr>@endif
                    @if ($s['department'])<tr><td class="muted">Department</td><td>{{ config("kretivco.departments.{$s['department']}.label", $s['department']) }}</td></tr>@endif
                    @if ($s['staff_no'])<tr><td class="muted">Staff no.</td><td>{{ $s['staff_no'] }}</td></tr>@endif
                    <tr><td class="muted">IC no.</td><td>{{ $s['ic_number'] ?: '-' }}</td></tr>
                </table>
            </td>
            <td style="width:50%">
                <table style="margin-top:14pt">
                    <tr><td class="muted" style="width:80pt">Pay date</td><td>{{ $run->pay_date->format('d M Y') }}</td></tr>
                    <tr><td class="muted">Paid to</td><td>{{ trim(($s['bank_name'] ?? '').' '.($s['bank_account'] ?? '')) ?: '-' }}</td></tr>
                    <tr><td class="muted">EPF no.</td><td>{{ $s['epf_number'] ?: '-' }}</td></tr>
                    <tr><td class="muted">SOCSO no.</td><td>{{ $s['socso_number'] ?: '-' }}</td></tr>
                    <tr><td class="muted">Tax no.</td><td>{{ $s['tax_number'] ?: '-' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="lines" style="margin-top:16pt">
        <tr><th>Earnings</th><th class="amt" style="text-align:right">RM</th><th class="gap"></th><th>Deductions</th><th style="text-align:right">RM</th></tr>
        @for ($i = 0; $i < $rows; $i++)
            <tr>
                <td>{{ $earnings[$i][0] ?? '' }}</td><td class="amt">{{ isset($earnings[$i]) ? $rm($earnings[$i][1]) : '' }}</td>
                <td class="gap"></td>
                <td>{{ $deductions[$i][0] ?? ($i === 0 ? 'None' : '') }}</td><td class="amt">{{ isset($deductions[$i]) ? $rm($deductions[$i][1]) : '' }}</td>
            </tr>
        @endfor
        <tr class="total">
            <td>Gross pay</td><td class="amt">{{ $rm($slip->gross) }}</td>
            <td class="gap"></td>
            <td>Total deductions</td><td class="amt">{{ $rm($slip->deductions()) }}</td>
        </tr>
    </table>

    <table style="margin-top:18pt">
        <tr>
            <td style="width:56%;padding-right:14pt">
                <div class="label" style="margin-bottom:5pt">Paid by the company on top of your salary</div>
                <table class="lines">
                    @foreach ($employer as [$name, $amt])
                        <tr><td>{{ $name }} (employer)</td><td class="amt">{{ $rm($amt) }}</td></tr>
                    @endforeach
                </table>
                @if ($slip->ot_hours > 0)
                    <p class="muted small" style="margin-top:8pt">Overtime covers approved hours from {{ $otFrom->format('d M') }} to {{ $otTo->format('d M Y') }}.</p>
                @endif
            </td>
            <td style="width:44%">
                <div class="net">
                    <div class="small" style="letter-spacing:0.8pt;text-transform:uppercase;color:#bdbdbd">Net pay</div>
                    <div class="big">RM {{ $rm($slip->net) }}</div>
                    <div class="small" style="color:#bdbdbd;margin-top:2pt">Credited on {{ $run->pay_date->format('d M Y') }}</div>
                </div>
            </td>
        </tr>
    </table>

    <p class="muted small" style="position:fixed;bottom:0;left:0;right:0;border-top:0.5pt solid #e0e0e0;padding-top:6pt">
        This payslip is computer generated and needs no signature. Please keep it private. Questions? Talk to HR.
    </p>
</body>
</html>
