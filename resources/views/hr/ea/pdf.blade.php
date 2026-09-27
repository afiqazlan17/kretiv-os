@php
    $rm = fn ($v) => number_format((float) $v, 2);
    $b = [
        ['1(a)', 'Gaji kasar, upah atau gaji cuti (termasuk gaji lebih masa)', 'Gross salary, wages or leave pay (including overtime)', $ea['salary']],
        ['1(b)', 'Fi (termasuk fi pengarah), komisen atau bonus', 'Fees (including director fees), commission or bonus', 0],
        ['1(c)', 'Tip kasar, perkuisit, penerimaan sagu hati atau elaun-elaun lain', 'Gross tips, perquisites, awards or other allowances', $ea['allowances']],
        ['1(d)', 'Cukai pendapatan yang dibayar oleh majikan bagi pihak pekerja', 'Income tax borne by the employer', 0],
        ['1(e)', 'Manfaat Skim Opsyen Saham Pekerja (ESOS)', 'Employee Share Option Scheme (ESOS) benefit', 0],
        ['1(f)', 'Ganjaran bagi tempoh', 'Gratuity', 0],
        ['2', 'Butiran bayaran tunggakan dan lain-lain bagi tahun terdahulu', 'Arrears and payments for preceding years', 0],
        ['3', 'Manfaat berupa barangan', 'Benefits in kind', 0],
        ['4', 'Nilai tempat kediaman yang disediakan oleh majikan', 'Value of living accommodation provided', 0],
        ['5', 'Bayaran balik daripada Kumpulan Wang Simpanan / Pencen yang tidak diluluskan', 'Refund from unapproved provident / pension fund', 0],
        ['6', 'Pampasan kerana kehilangan pekerjaan', 'Compensation for loss of employment', 0],
    ];
    $totalB = collect($b)->sum(fn ($r) => $r[3]);
@endphp
<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Borang EA {{ $ea['year'] }}</title>
<style>
    @page { margin: 26pt 34pt; }
    * { font-family: Helvetica, Arial, sans-serif; }
    body { color: #141414; font-size: 8pt; margin: 0; }
    table { border-collapse: collapse; width: 100%; }
    td { vertical-align: top; }
    .muted { color: #6b6b6b; }
    .en { color: #8a8a8a; font-size: 6.8pt; }
    .sec { background: #141414; color: #fff; font-weight: bold; font-size: 7.6pt; letter-spacing: 0.4pt; padding: 4pt 7pt; margin-top: 9pt; }
    .grid td { padding: 3.2pt 7pt; border-bottom: 0.5pt solid #e6e6e6; }
    .grid .no { width: 26pt; color: #6b6b6b; }
    .grid .amt { width: 80pt; text-align: right; white-space: nowrap; font-weight: bold; }
    .grid tr.total td { border-top: 0.9pt solid #141414; border-bottom: none; font-weight: bold; }
    .box { border: 0.75pt solid #cfcfcf; border-radius: 4pt; padding: 7pt 9pt; }
    .kv td { padding: 2pt 0; }
    .kv .k { color: #6b6b6b; width: 118pt; }
</style></head>
<body>
    <table>
        <tr>
            <td style="width:52pt"><img src="{{ public_path('images/kretivco-logo.png') }}" style="width:44pt;height:44pt"></td>
            <td>
                <div style="font-size:12pt;font-weight:bold">{{ config('kretivco.brand.name') }}</div>
                <div class="muted" style="font-size:7pt">{{ config('kretivco.brand.ssm') }} · {{ config('kretivco.brand.address_line_1') }}, {{ config('kretivco.brand.address_line_2') }}</div>
                <div class="muted" style="font-size:7pt">No. Majikan E: {{ config('kretivco.brand.lhdn_employer_no') ?: '________________' }}</div>
            </td>
            <td style="text-align:right">
                <div style="font-size:20pt;font-weight:bold;letter-spacing:-0.3pt">BORANG EA</div>
                <div class="muted" style="font-size:7pt">C.P.8A</div>
            </td>
        </tr>
    </table>
    <div style="border-top:1.4pt solid #141414;margin:8pt 0 6pt"></div>
    <div style="text-align:center;font-weight:bold;font-size:8.6pt">PENYATA SARAAN DARIPADA PENGGAJIAN BAGI TAHUN BERAKHIR 31 DISEMBER {{ $ea['year'] }}</div>
    <div class="en" style="text-align:center">Statement of remuneration from employment for the year ended 31 December {{ $ea['year'] }}</div>
    <table style="margin-top:6pt"><tr>
        <td class="muted">No. Cukai Pendapatan Pekerja: <b style="color:#141414">{{ $ea['tax_no'] ?: '-' }}</b></td>
        <td style="text-align:right" class="muted">Cawangan LHDNM: ________________</td>
    </tr></table>

    <div class="sec">A. BUTIRAN PEKERJA <span style="font-weight:normal;opacity:.7">/ EMPLOYEE DETAILS</span></div>
    <div class="box" style="border-top:none;border-radius:0 0 4pt 4pt">
        <table class="kv">
            <tr><td class="k">1. Nama penuh</td><td><b>{{ strtoupper($ea['name']) }}</b></td><td class="k">6. No. KWSP</td><td>{{ $ea['epf_no'] ?: '-' }}</td></tr>
            <tr><td class="k">2. Jawatan</td><td>{{ $ea['title'] ?: '-' }}</td><td class="k">7. No. PERKESO</td><td>{{ $ea['socso_no'] ?: '-' }}</td></tr>
            <tr><td class="k">3. No. kakitangan</td><td>{{ $ea['staff_no'] ?: '-' }}</td><td class="k">8. Bilangan anak yang layak</td><td>-</td></tr>
            <tr><td class="k">4. No. K.P. baru</td><td>{{ $ea['ic'] ?: '-' }}</td><td class="k">9. Tarikh mula berkhidmat</td><td>{{ $ea['start']?->format('d/m/Y') ?? '-' }}</td></tr>
            <tr><td class="k">5. No. pasport</td><td>-</td><td class="k">10. Tarikh berhenti kerja</td><td>{{ $ea['end']?->format('d/m/Y') ?? '-' }}</td></tr>
        </table>
    </div>

    <div class="sec">B. PENDAPATAN PENGGAJIAN, MANFAAT DAN TEMPAT KEDIAMAN <span style="font-weight:normal;opacity:.7">/ EMPLOYMENT INCOME</span></div>
    <table class="grid">
        @foreach ($b as [$no, $ms, $en, $amt])
            <tr><td class="no">{{ $no }}</td><td>{{ $ms }}<br><span class="en">{{ $en }}</span></td><td class="amt">{{ $rm($amt) }}</td></tr>
        @endforeach
        <tr class="total"><td></td><td>JUMLAH / TOTAL (RM)</td><td class="amt">{{ $rm($totalB) }}</td></tr>
    </table>

    <table style="margin-top:0"><tr>
        <td style="width:50%;padding-right:6pt">
            <div class="sec">C. PENCEN DAN LAIN-LAIN</div>
            <table class="grid">
                <tr><td class="no">1</td><td>Pencen</td><td class="amt">0.00</td></tr>
                <tr><td class="no">2</td><td>Anuiti atau bayaran berkala lain</td><td class="amt">0.00</td></tr>
                <tr class="total"><td></td><td>JUMLAH</td><td class="amt">0.00</td></tr>
            </table>
            <div class="sec">E. CARUMAN WAJIB PEKERJA</div>
            <table class="grid">
                <tr><td class="no">1</td><td>KWSP<br><span class="en">EPF, employee's share</span></td><td class="amt">{{ $rm($ea['epf']) }}</td></tr>
                <tr><td class="no">2</td><td>PERKESO (SOCSO dan SIP/EIS)<br><span class="en">SOCSO and EIS, employee's share</span></td><td class="amt">{{ $rm($ea['socso']) }}</td></tr>
            </table>
        </td>
        <td style="width:50%;padding-left:6pt">
            <div class="sec">D. JUMLAH POTONGAN</div>
            <table class="grid">
                <tr><td class="no">1</td><td>Potongan Cukai Bulanan (PCB) dibayar kepada LHDNM</td><td class="amt">{{ $rm($ea['pcb']) }}</td></tr>
                <tr><td class="no">2</td><td>Arahan potongan CP38</td><td class="amt">0.00</td></tr>
                <tr><td class="no">3</td><td>Zakat melalui potongan gaji</td><td class="amt">0.00</td></tr>
                <tr><td class="no">4</td><td>Tuntutan potongan melalui Borang TP1</td><td class="amt">0.00</td></tr>
            </table>
            <div class="sec">F. ELAUN / PERKUISIT DIKECUALIKAN CUKAI</div>
            <table class="grid"><tr><td class="no"></td><td>Jumlah</td><td class="amt">0.00</td></tr></table>
        </td>
    </tr></table>

    <table style="margin-top:14pt"><tr>
        <td style="width:55%">
            <div class="muted">Nama pegawai: <b style="color:#141414">{{ $signer?->name ?? '________________' }}</b></div>
            <div class="muted" style="margin-top:2pt">Jawatan: <span style="color:#141414">{{ $signer?->title ?? '________________' }}</span></div>
            <div class="muted" style="margin-top:2pt">Majikan: <span style="color:#141414">{{ config('kretivco.brand.name') }}, {{ config('kretivco.brand.phone') }}</span></div>
        </td>
        <td style="text-align:right" class="muted">Tarikh: <span style="color:#141414">{{ now()->format('d/m/Y') }}</span></td>
    </tr></table>
    <p class="en" style="position:fixed;bottom:0;left:0;right:0;border-top:0.5pt solid #e0e0e0;padding-top:5pt">
        Computer generated from {{ $ea['months'] }} month(s) of payroll in {{ $ea['year'] }}; no signature needed. Keep it for your income tax return (e-BE) due 30 April {{ $ea['year'] + 1 }}.
    </p>
</body></html>
