<?php

namespace App\Http\Controllers;

use App\Models\JobDocument;
use App\Models\LedgerEntry;
use App\Services\FinanceReports;
use App\Support\ChartOfAccounts;
use App\Support\SimpleXlsx;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

// Accountant pack: one zip per period with an Excel workbook (summary, full
// ledger, trial balance, receivables), every expense receipt, and the
// invoice/receipt PDFs issued, so month-end or year-end handover is a
// single download.
class AccountantPackController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeCompany($request);

        return view('finance.accountant', [
            'months' => collect(range(0, 17))->map(fn ($i) => now()->startOfMonth()->subMonths($i)),
            'years' => range(now()->year, now()->year - 4),
        ]);
    }

    public function download(Request $request): BinaryFileResponse
    {
        $this->authorizeCompany($request);

        $data = $request->validate(['from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from']]);
        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->endOfDay();

        $all = FinanceController::entriesFor($request->user());
        $reports = new FinanceReports($all);
        $period = $reports->between($from, $to);
        $asAt = $reports->upTo($to);
        $pl = $period->profitAndLoss();
        $bs = $asAt->balanceSheet();
        $tb = $asAt->trialBalance();
        $entries = $all->filter(fn (LedgerEntry $e) => $e->date && $e->date->between($from, $to))->sortBy('date')->values();

        $label = $from->format('Y-m-d').'_to_'.$to->format('Y-m-d');
        $receiptFile = fn (LedgerEntry $e) => 'receipts/'.$e->date->format('Y-m-d').'_'.$e->id.'_'.basename((string) $e->receipt_name ?: 'receipt');

        $workbook = SimpleXlsx::build([
            'Summary' => [
                [config('kretivco.brand.name', 'Kretivco Mediaworks').' accounts'],
                ['Period', $from->format('d M Y').' to '.$to->format('d M Y')],
                ['Generated', now()->format('d M Y, g:ia').' by '.$request->user()->name],
                [],
                ['Profit and loss (period)', ''],
                ['Revenue', $pl['revenue']], ['Cost of services', $pl['cost']], ['Gross profit', $pl['gross']],
                ['Operating expense', $pl['opex']], ['Net profit', $pl['net']],
                [],
                ['Balance sheet (as at '.$to->format('d M Y').')', ''],
                ...collect($bs['banks'])->map(fn ($v, $k) => ['Bank: '.config("kretivco.banks.{$k}.label", $k), $v])->values()->all(),
                ['Accounts receivable', $bs['receivable']], ['Total assets', $bs['assets']],
                ['Total liabilities', $bs['liabilities']], ['Opening equity', $bs['opening']], ['Retained earnings', $bs['retained']],
                ['Balanced', $bs['balanced'] ? 'Yes' : 'No'],
            ],
            'Ledger' => [
                ['Date', 'Type', 'Description', 'Debit account', 'Credit account', 'Department', 'Job', 'Doc no.', 'Bank', 'Amount (RM)', 'Status', 'Receipt file'],
                ...$entries->map(fn (LedgerEntry $e) => [
                    $e->date->format('Y-m-d'), str_replace('_', ' ', $e->type), $e->description,
                    ChartOfAccounts::describe($e->debit_account)['name'], ChartOfAccounts::describe($e->credit_account)['name'],
                    $e->department ? config("kretivco.departments.{$e->department}.label", $e->department) : '',
                    $e->job_id ?? '', $e->doc_number ?? '', $e->bank ? config("kretivco.banks.{$e->bank}.label", $e->bank) : '',
                    (float) $e->amount, $e->reversed ? 'Voided' : '', $e->receipt_path ? $receiptFile($e) : '',
                ])->all(),
            ],
            'Trial Balance' => [
                ['Code', 'Account', 'Debit (RM)', 'Credit (RM)'],
                ...$tb['rows']->map(fn ($r) => [$r['code'], $r['name'], $r['debit'], $r['credit']])->all(),
                ['', 'Total', $tb['debit'], $tb['credit']],
            ],
            'Receivables' => [
                ['Customer', 'Job', 'Invoice date', 'Days', 'Outstanding (RM)'],
                ...collect($asAt->aging($to)['rows'])->map(fn ($r) => [$r['customer'], $r['job_id'], $r['date']->format('Y-m-d'), $r['days'], $r['outstanding']])->all(),
            ],
        ]);

        $zipPath = tempnam(sys_get_temp_dir(), 'kretiv-pack');
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::OVERWRITE);
        $zip->addFromString("Kretivco_Accounts_{$label}.xlsx", $workbook);

        $disk = Storage::disk('public');
        foreach ($entries->filter(fn (LedgerEntry $e) => $e->receipt_path && ! $e->reversed) as $e) {
            if ($disk->exists($e->receipt_path)) {
                $zip->addFile($disk->path($e->receipt_path), $receiptFile($e));
            }
        }
        JobDocument::whereIn('doc_type', ['invoice', 'receipt'])->whereBetween('generated_at', [$from, $to])->get()
            ->each(function (JobDocument $doc) use ($zip, $disk) {
                if ($disk->exists($doc->storage_path)) {
                    $zip->addFile($disk->path($doc->storage_path), "documents/{$doc->doc_type}s/{$doc->filename}");
                }
            });
        $zip->close();

        return response()->download($zipPath, "Kretivco_Accounts_{$label}.zip")->deleteFileAfterSend();
    }

    private function authorizeCompany(Request $request): void
    {
        abort_unless($request->user()->seesCompanyFinance(), 403);
    }
}
