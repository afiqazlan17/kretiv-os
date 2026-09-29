<?php

namespace App\Http\Controllers;

use App\Models\Job;
use App\Models\LedgerEntry;
use App\Support\DocumentData;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

// Bank statement import: upload a CSV exported from online banking, and each
// line is matched to the ledger (same bank, same direction, same amount,
// date within a few days). Unmatched money out can be recorded as an
// expense on the spot; unmatched money in points at a missing receipt.
// Nothing is saved except the parsed lines in the session, so re-uploading
// is harmless.
class BankImportController extends Controller
{
    private const DATE_WINDOW_DAYS = 5;

    public function index(Request $request): View
    {
        $this->authorizeCompany($request);

        $import = session('bank_import');
        $result = $import ? $this->match($import['bank'], collect($import['rows'])) : null;
        if ($result) {
            $result['suggestions'] = $this->suggestJobs($result['unmatched']);
        }

        return view('finance.bank-import', ['import' => $import, 'result' => $result]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $this->authorizeCompany($request);

        $data = $request->validate([
            'bank' => ['required', 'in:'.implode(',', array_keys(config('kretivco.banks')))],
            'statement' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $rows = self::parse((string) file_get_contents($request->file('statement')->getRealPath()));
        if ($rows === []) {
            return back()->withErrors(['statement' => 'No transactions found. Export the statement as CSV with Date, Description and Debit/Credit (or Amount) columns.']);
        }

        session(['bank_import' => ['bank' => $data['bank'], 'file' => $request->file('statement')->getClientOriginalName(), 'rows' => $rows]]);

        return redirect()->route('finance.bank-import');
    }

    public function clear(Request $request): RedirectResponse
    {
        $this->authorizeCompany($request);
        session()->forget('bank_import');

        return redirect()->route('finance.bank-import');
    }

    /**
     * Parses a bank CSV into [date, description, amount (+in / -out)] rows.
     * Finds the header row by a "date" column, then picks description and
     * either separate debit/credit columns or a single signed amount.
     *
     * @return array<int, array{date: string, description: string, amount: float}>
     */
    public static function parse(string $csv): array
    {
        $lines = array_map('str_getcsv', preg_split('/\R/', trim(preg_replace('/^\xEF\xBB\xBF/', '', $csv))));
        $headerAt = collect($lines)->search(fn ($cells) => collect($cells)->contains(fn ($c) => preg_match('/\b(date|tarikh)\b/i', (string) $c)));
        if ($headerAt === false) {
            return [];
        }

        $header = array_map(fn ($c) => strtolower(trim((string) $c)), $lines[$headerAt]);
        $find = fn (string $pattern, array $skip = []) => collect($header)->search(fn ($h, $i) => ! in_array($i, $skip, true) && preg_match($pattern, $h));
        $dateCol = $find('/\b(date|tarikh)\b/');
        $outCol = $find('/debit|withdraw|keluar|payment|\bdr\b/');
        $inCol = $find('/credit|deposit|masuk|\bcr\b/', array_filter([$outCol], fn ($v) => $v !== false));
        $amountCol = $find('/amount|jumlah|amaun/');
        $descCol = $find('/desc|particular|transaction|detail|keterangan|reference|narrative/', [$dateCol]);

        $money = function ($v): ?float {
            $v = trim((string) $v);
            if ($v === '' || $v === '-') {
                return null;
            }
            // Negative as "-88.00", "88.00-" (common in Malaysian bank exports), "(88.00)" or "88.00 DR".
            $negative = str_starts_with($v, '(') || str_starts_with($v, '-') || str_ends_with($v, '-') || preg_match('/\bdr\b/i', $v);
            $n = (float) preg_replace('/[^0-9.]/', '', $v);

            return $negative ? -$n : $n;
        };

        $rows = [];
        foreach (array_slice($lines, $headerAt + 1) as $cells) {
            $date = self::date($cells[$dateCol] ?? '');
            if (! $date) {
                continue;
            }
            $amount = null;
            if ($outCol !== false || $inCol !== false) {
                $out = $outCol !== false ? $money($cells[$outCol] ?? '') : null;
                $in = $inCol !== false ? $money($cells[$inCol] ?? '') : null;
                $amount = $in ? abs($in) : ($out ? -abs($out) : null);
            } elseif ($amountCol !== false) {
                $amount = $money($cells[$amountCol] ?? '');
            }
            if (! $amount) {
                continue;
            }
            $rows[] = ['date' => $date->toDateString(), 'description' => trim((string) ($cells[$descCol === false ? -1 : $descCol] ?? '')), 'amount' => round($amount, 2)];
        }

        return $rows;
    }

    private static function date(string $value): ?Carbon
    {
        $value = trim($value);
        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y', 'd-m-y', 'd M Y', 'd-M-Y', 'd M y', 'd.m.Y'] as $format) {
            $d = \DateTime::createFromFormat('!'.$format, $value);
            if ($d && $d->format($format) === $value) {
                return Carbon::instance($d);
            }
        }

        return null;
    }

    /** @return array{matched: Collection, unmatched: Collection, missing: Collection} */
    private function match(string $bank, Collection $rows): array
    {
        $account = "bank_{$bank}";
        $from = Carbon::parse($rows->min('date'))->subDays(self::DATE_WINDOW_DAYS);
        $to = Carbon::parse($rows->max('date'))->addDays(self::DATE_WINDOW_DAYS);
        $candidates = LedgerEntry::where('reversed', false)->where('type', '!=', 'reversal')
            ->where(fn ($q) => $q->where('debit_account', $account)->orWhere('credit_account', $account))
            ->whereBetween('date', [$from->startOfDay(), $to->endOfDay()])->get();
        $used = [];

        $lines = $rows->map(function (array $row) use ($account, $candidates, &$used) {
            $in = $row['amount'] > 0;
            $date = Carbon::parse($row['date']);
            $hit = $candidates
                ->reject(fn (LedgerEntry $e) => in_array($e->id, $used, true))
                ->filter(fn (LedgerEntry $e) => ($in ? $e->debit_account : $e->credit_account) === $account
                    && abs((float) $e->amount - abs($row['amount'])) < 0.01
                    && abs($e->date->startOfDay()->diffInDays($date->copy()->startOfDay())) <= self::DATE_WINDOW_DAYS)
                ->sortBy(fn (LedgerEntry $e) => abs($e->date->startOfDay()->diffInDays($date->copy()->startOfDay())))
                ->first();
            if ($hit) {
                $used[] = $hit->id;
            }

            return $row + ['entry' => $hit];
        });

        $inRange = $candidates->filter(fn (LedgerEntry $e) => $e->date->between(Carbon::parse($rows->min('date'))->startOfDay(), Carbon::parse($rows->max('date'))->endOfDay()));

        return [
            'matched' => $lines->filter(fn ($l) => $l['entry'])->values(),
            'unmatched' => $lines->reject(fn ($l) => $l['entry'])->values(),
            // In the ledger for these dates but not on the statement: typo'd amount, wrong bank, or not cleared yet.
            'missing' => $inRange->reject(fn (LedgerEntry $e) => in_array($e->id, $used, true))->values(),
        ];
    }

    /**
     * For money in that isn't in the ledger yet: the jobs it most likely pays
     * for, by the customer's name in the bank description and by the amount
     * being exactly what the job still owes. Up to 3 per line, best first.
     *
     * @return array<int, Collection<int, array{job: Job, owed: float, why: string}>>
     */
    private function suggestJobs(Collection $unmatched): array
    {
        $jobs = Job::with('customer')->where('archived', false)->whereNotIn('status', [Job::STATUS_NEW, Job::STATUS_CANCELLED])->get();
        $entries = LedgerEntry::whereIn('job_id', $jobs->pluck('job_id'))->where('reversed', false)
            ->whereIn('type', ['invoice', 'receipt', 'credit_note'])->get()->groupBy('job_id');
        $open = $jobs->map(function (Job $job) use ($entries) {
            $group = $entries->get($job->job_id, collect());
            $invoice = $group->where('type', 'invoice')->sortByDesc('id')->first();
            $basis = $invoice ? (float) $invoice->amount - (float) $group->where('type', 'credit_note')->sum('amount') : DocumentData::jobTotal($job);
            $owed = round($basis - (float) $group->where('type', 'receipt')->sum('amount'), 2);

            return ['job' => $job, 'owed' => $owed];
        })->filter(fn ($o) => $o['owed'] > 0.005);

        // Words from the customer's name and company, without the ones every company has.
        $common = ['SDN', 'BHD', 'BERHAD', 'ENTERPRISE', 'TRADING', 'RESOURCES', 'SERVICES', 'SOLUTIONS', 'GROUP', 'MALAYSIA', 'PLT', 'BIN', 'BINTI', 'THE', 'AND'];
        $words = fn (Job $job) => collect(preg_split('/[^A-Z0-9]+/', strtoupper(($job->customer?->name ?? '').' '.($job->customer?->company ?? ''))))
            ->filter(fn ($w) => strlen($w) >= 4 && ! in_array($w, $common, true))->unique();

        $suggestions = [];
        foreach ($unmatched as $i => $line) {
            if ($line['amount'] <= 0) {
                continue;
            }
            $text = strtoupper((string) $line['description']);
            $suggestions[$i] = $open->map(function ($o) use ($line, $text, $words) {
                $named = $words($o['job'])->contains(fn ($w) => str_contains($text, $w));
                $exact = abs($o['owed'] - $line['amount']) < 0.01;
                $fits = $line['amount'] <= $o['owed'] + 0.005;

                return $o + ['score' => ($named ? 2 : 0) + ($exact ? 2 : 0) + ($fits ? 1 : 0), 'why' => collect([$named ? 'name matches' : null, $exact ? 'same amount as the balance' : null])->filter()->join(', ')];
            })->filter(fn ($o) => $o['score'] >= 3)->sortByDesc('score')->take(3)->values();
        }

        return $suggestions;
    }

    private function authorizeCompany(Request $request): void
    {
        abort_unless($request->user()->seesCompanyFinance(), 403);
    }
}
