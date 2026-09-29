<?php

namespace App\Services;

use App\Models\Job;
use App\Models\LedgerEntry;
use Illuminate\Support\Collection;

// Every entry is a simple two-account transaction (debit_account,
// credit_account, one amount) — always balanced by construction. Ported
// faithfully from lib/hooks.js + lib/ledger.js in the old Next.js app;
// the supersede/reversal patterns matter for correctness so keep this in
// lockstep with that source of truth if the old app ever changes.
class LedgerService
{
    // ── Account naming helpers (lib/constants.js) ──

    public static function revenueAccount(string $dept): string
    {
        return "revenue_{$dept}";
    }

    // A department's Cost of Service account is split per expense category
    // (e.g. cogs_print_commission) so department cost still separately
    // reports "how much Commission" without losing department attribution.
    public static function cogsAccount(string $dept, ?string $category = null): string
    {
        return $category ? "cogs_{$dept}_{$category}" : "cogs_{$dept}";
    }

    public static function opexAccount(string $category): string
    {
        return "opex_{$category}";
    }

    public static function bankAccount(string $bank): string
    {
        return "bank_{$bank}";
    }

    public static function slugify(?string $name): string
    {
        $slug = strtolower(trim($name ?? ''));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);

        return trim($slug, '_');
    }

    // Directors are a dynamic list (any BOD user), not a fixed enum like
    // banks, so their loan account is keyed off a slug of their name.
    public static function loanAccount(string $directorName): string
    {
        return 'loan_'.self::slugify($directorName);
    }

    // ── Balance computation ──

    // Accounts that grow with a Debit (asset/expense-style); everything
    // else (revenue, equity, liabilities like AR's counterpart or a
    // director loan) grows with a Credit. Mirrors DEBIT_NORMAL in the old
    // app's finance/page.jsx exactly.
    public static function isDebitNormal(string $accountKey): bool
    {
        return $accountKey === 'ar'
            || $accountKey === 'fixed_assets'
            || $accountKey === 'equity_drawings'
            || str_starts_with($accountKey, 'bank_')
            || str_starts_with($accountKey, 'cogs_')
            || str_starts_with($accountKey, 'opex_');
    }

    /**
     * @param  Collection<int, LedgerEntry>  $entries
     */
    public static function balanceFor($entries, string $accountKey): float
    {
        $raw = $entries->reduce(function (float $bal, LedgerEntry $e) use ($accountKey) {
            if ($e->debit_account === $accountKey) {
                $bal += (float) $e->amount;
            }
            if ($e->credit_account === $accountKey) {
                $bal -= (float) $e->amount;
            }

            return $bal;
        }, 0.0);

        return self::isDebitNormal($accountKey) ? $raw : -$raw;
    }

    // A department's Cost of Service is split across per-category
    // sub-accounts (cogs_<dept>_commission, cogs_<dept>_subcontractor, ...)
    // — this sums all of them (plus any flat cogs_<dept> entries) back into
    // one department total, by matching the account-key prefix.
    /**
     * @param  Collection<int, LedgerEntry>  $entries
     */
    public static function cogsForDept($entries, string $dept): float
    {
        $keys = $entries->flatMap(function (LedgerEntry $e) use ($dept) {
            $matches = [];
            foreach ([$e->debit_account, $e->credit_account] as $account) {
                if ($account === "cogs_{$dept}" || str_starts_with((string) $account, "cogs_{$dept}_")) {
                    $matches[] = $account;
                }
            }

            return $matches;
        })->unique();

        return $keys->sum(fn ($key) => self::balanceFor($entries, $key));
    }

    // ── Core entry + reversal primitives ──

    public function addEntry(array $entry): LedgerEntry
    {
        return LedgerEntry::create(array_merge([
            'date' => now(),
            'reversed' => false,
            'reverses_id' => null,
            'created_by' => 'System',
        ], $entry));
    }

    // Reverse every not-yet-reversed entry matching a predicate, by posting
    // the mirrored (debit/credit swapped) counter-entry — never deletes the
    // original, so the audit trail stays intact.
    public function reverseEntries(callable $predicate, string $userName): void
    {
        $targets = LedgerEntry::where('reversed', false)->get()->filter($predicate);

        foreach ($targets as $orig) {
            $this->addEntry([
                'date' => now(),
                'type' => 'reversal',
                'description' => "Reversal: {$orig->description}",
                'department' => $orig->department,
                'job_id' => $orig->job_id,
                'doc_number' => $orig->doc_number,
                'debit_account' => $orig->credit_account,
                'credit_account' => $orig->debit_account,
                'amount' => $orig->amount,
                'bank' => $orig->bank,
                'created_by' => $userName ?: 'System',
                'reverses_id' => $orig->id,
            ]);
            $orig->update(['reversed' => true]);
        }
    }

    // Void every not-yet-reversed entry tied to a job (e.g. on cancellation).
    public function reverseJobLedgerEntries(string $jobId, string $userName): void
    {
        $this->reverseEntries(fn ($e) => $e->job_id === $jobId, $userName);
    }

    // ── Document posting (Invoice / Receipt) ──

    // The actual total an Invoice should post for — the amount shown on the
    // generated PDF when one's given, else the raw line-item sum, else the
    // job's estimate.
    public function computeInvoiceAmount($job, $amountOverride = null): float
    {
        if ($amountOverride !== null) {
            return (float) $amountOverride;
        }

        $itemsTotal = collect($job->line_items ?? [])
            ->sum(fn ($li) => (float) ($li['qty'] ?? 0) * (float) ($li['price'] ?? 0));

        return $itemsTotal ?: (float) ($job->estimation_value ?? 0);
    }

    // A Receipt has no line-item concept of its own — it's proof of payment
    // against whatever was actually invoiced/estimated.
    public function computeReceiptAmount($job, $amountOverride = null): float
    {
        if ($amountOverride !== null) {
            return (float) $amountOverride;
        }

        return (float) ($job->final_value ?? $job->estimation_value ?? 0);
    }

    // Decide what a document posting (Invoice or Receipt) should do:
    //  - 'skip'      — nothing to post (amount is 0/falsy)
    //  - 'unchanged' — an unreversed entry of this type already has this
    //                  exact amount; reprinting/resharing shouldn't touch
    //                  the ledger
    //  - 'post'      — genuinely new or changed; reverse any prior entry of
    //                  this type for the job, then post a fresh one
    protected function decideDocPosting(string $jobId, string $type, float $amount): string
    {
        if (! $amount) {
            return 'skip';
        }

        $existing = LedgerEntry::where('job_id', $jobId)->where('type', $type)->where('reversed', false)->first();

        if ($existing && (float) $existing->amount === $amount) {
            return 'unchanged';
        }

        return 'post';
    }

    // Invoice issued: revenue recognized, amount owed by the customer. Doc
    // may be regenerated — supersede rather than double-count by reversing
    // any prior unreversed invoice for this job first.
    public function postInvoiceEntry($job, string $docNumber, string $userName, $amountOverride = null): ?LedgerEntry
    {
        $amount = $this->computeInvoiceAmount($job, $amountOverride);
        $decision = $this->decideDocPosting($job->job_id, 'invoice', $amount);

        if ($decision === 'skip') {
            return null;
        }

        if ($decision === 'unchanged') {
            $this->syncDeposits($job, $userName);

            return LedgerEntry::where('job_id', $job->job_id)->where('type', 'invoice')->where('reversed', false)->first();
        }

        $this->reverseEntries(fn ($e) => $e->job_id === $job->job_id && $e->type === 'invoice', $userName);

        return $this->afterPosting($job, $userName, $this->addEntry([
            'date' => now(),
            'type' => 'invoice',
            'description' => "Invoice {$docNumber}: {$job->customer?->name}",
            'department' => $job->department,
            'job_id' => $job->job_id,
            'doc_number' => $docNumber,
            'debit_account' => 'ar',
            'credit_account' => self::revenueAccount($job->department),
            'amount' => $amount,
            'bank' => null,
            'created_by' => $userName ?: 'System',
        ]));
    }

    // Receipt issued: payment received, clears what was owed. Same
    // supersede rule as invoices.
    public function postReceiptEntry($job, string $docNumber, string $userName, $amountOverride = null, ?string $bank = null): ?LedgerEntry
    {
        $amount = $this->computeReceiptAmount($job, $amountOverride);

        if (! $amount) {
            return null;
        }

        // A job can take several payments (deposit, then balance), each with its
        // own receipt number. Only a receipt with the SAME number is superseded
        // here, so a second payment never wipes out the first one.
        $existing = LedgerEntry::where('job_id', $job->job_id)->where('type', 'receipt')
            ->where('doc_number', $docNumber)->where('reversed', false)->first();

        if ($existing && (float) $existing->amount === (float) $amount) {
            return $existing;
        }

        // A project payment lands in the one account its receipt shows.
        $bank = $bank ?: ($job->bank ?: 'mbb');
        if ($existing) {
            $this->reverseEntries(fn ($e) => $e->id === $existing->id, $userName);
        }
        // Paid before there's an invoice: it's a deposit we hold, not money
        // against a receivable (see syncDeposits).
        $hasInvoice = LedgerEntry::where('job_id', $job->job_id)->where('type', 'invoice')->where('reversed', false)->exists();

        return $this->afterPosting($job, $userName, $this->addEntry([
            'date' => now(),
            'type' => 'receipt',
            'description' => "Receipt {$docNumber}: {$job->customer?->name}",
            'department' => $job->department,
            'job_id' => $job->job_id,
            'doc_number' => $docNumber,
            'debit_account' => self::bankAccount($bank),
            'credit_account' => $hasInvoice ? 'ar' : 'customer_deposits',
            'amount' => $amount,
            'bank' => $bank,
            'created_by' => $userName ?: 'System',
        ]));
    }

    // Credit note against the invoice: takes the amount off revenue and off
    // what the customer owes. Each credit note has its own number.
    public function postCreditNote($job, string $docNumber, string $userName, float $amount): ?LedgerEntry
    {
        if ($amount <= 0) {
            return null;
        }

        return $this->addEntry([
            'date' => now(),
            'type' => 'credit_note',
            'description' => "Credit Note {$docNumber}: {$job->customer?->name}",
            'department' => $job->department,
            'job_id' => $job->job_id,
            'doc_number' => $docNumber,
            'debit_account' => self::revenueAccount($job->department),
            'credit_account' => 'ar',
            'amount' => $amount,
            'bank' => null,
            'created_by' => $userName ?: 'System',
        ]);
    }

    /** Cancels one recorded payment (e.g. keyed in twice by mistake) by reversing its entry. */
    public function voidReceipt(LedgerEntry $entry, string $userName): void
    {
        $this->reverseEntries(fn ($e) => $e->id === $entry->id, $userName);
        if ($entry->job_id && ($job = Job::where('job_id', $entry->job_id)->first())) {
            $this->syncDeposits($job, $userName);
        }
    }

    /** What's still held as a deposit for the job (received before any invoice, not yet applied, refunded or kept). */
    public static function depositHeld($job): float
    {
        return round(self::balanceFor(LedgerEntry::where('job_id', $job->job_id)->get(), 'customer_deposits'), 2);
    }

    /**
     * A cancelled job's deposit: paid back to the customer (money out of the
     * bank) or kept under the non-refundable terms (becomes income).
     */
    public function settleDeposit($job, string $how, float $amount, ?string $bank, string $userName, ?string $date = null): LedgerEntry
    {
        $refund = $how === 'refund';

        return $this->addEntry([
            'date' => $date ?? now(),
            'type' => $refund ? 'deposit_refund' : 'deposit_forfeit',
            'description' => ($refund ? 'Deposit refunded' : 'Deposit kept (non-refundable)').": {$job->customer?->name}, {$job->job_id}",
            'department' => $job->department,
            'job_id' => $job->job_id,
            'debit_account' => 'customer_deposits',
            'credit_account' => $refund ? self::bankAccount($bank) : self::revenueAccount($job->department),
            'amount' => round($amount, 2),
            'bank' => $refund ? $bank : null,
            'created_by' => $userName ?: 'System',
        ]);
    }

    private function afterPosting($job, string $userName, LedgerEntry $entry): LedgerEntry
    {
        $this->syncDeposits($job, $userName);

        return $entry;
    }

    /**
     * Customer deposits: money received before the invoice is held in
     * 'customer_deposits' (a liability), not taken off receivables. Once the
     * job has an invoice, everything held is applied to it (Dr deposits,
     * Cr AR). Re-run after any invoice, receipt or void so the applied
     * amount always equals what's held, and none while there's no invoice.
     */
    public function syncDeposits($job, string $userName): void
    {
        $entries = LedgerEntry::where('job_id', $job->job_id)->where('reversed', false)->get();
        $held = (float) $entries->where('type', 'receipt')->where('credit_account', 'customer_deposits')->sum('amount')
            - (float) $entries->whereIn('type', ['deposit_refund', 'deposit_forfeit'])->sum('amount');
        $hasInvoice = $entries->where('type', 'invoice')->isNotEmpty();
        $target = $hasInvoice ? round($held, 2) : 0.0;
        $applied = round((float) $entries->where('type', 'deposit_applied')->sum('amount'), 2);

        if (abs($applied - $target) < 0.005) {
            return;
        }
        $this->reverseEntries(fn ($e) => $e->job_id === $job->job_id && $e->type === 'deposit_applied', $userName);
        if ($target > 0) {
            $this->addEntry([
                'date' => now(),
                'type' => 'deposit_applied',
                'description' => "Deposit applied to invoice: {$job->customer?->name}",
                'department' => $job->department,
                'job_id' => $job->job_id,
                'debit_account' => 'customer_deposits',
                'credit_account' => 'ar',
                'amount' => $target,
                'bank' => null,
                'created_by' => $userName ?: 'System',
            ]);
        }
    }

    // Plain-language expense entry — department set = cost of service for
    // that department, blank = company-wide operating expense.
    public function postExpenseEntry(array $data, string $userName): ?LedgerEntry
    {
        $amount = (float) ($data['amount'] ?? 0);
        $bank = $data['bank'] ?? null;

        if (! $amount || ! $bank) {
            return null;
        }

        $department = $data['department'] ?? null;
        $category = $data['category'] ?? null;
        $debitAccount = $department ? self::cogsAccount($department, $category) : self::opexAccount($category);
        $categoryLabel = config("kretivco.expense_categories.{$category}", $category);

        return $this->addEntry([
            'date' => $data['date'] ?? now(),
            'type' => $department ? 'job_expense' : 'operating_expense',
            'description' => trim($data['notes'] ?? '') ?: $categoryLabel,
            'department' => $department,
            'job_id' => $data['job_id'] ?? null,
            'doc_number' => $data['doc_number'] ?? null,
            'debit_account' => $debitAccount,
            'credit_account' => self::bankAccount($bank),
            'amount' => $amount,
            'bank' => $bank,
            'created_by' => $userName ?: 'System',
            'receipt_path' => $data['receipt_path'] ?? null,
            'receipt_name' => $data['receipt_name'] ?? null,
            // Client entertainment is only half deductible unless marked otherwise.
            'tax_treatment' => $data['tax_treatment'] ?? ($category === 'entertainment' ? 'partial' : null),
        ]);
    }

    /** Buying a fixed asset: moves cash into Fixed Assets (balance sheet), not an expense. */
    public function postAssetPurchase(array $data, string $userName): LedgerEntry
    {
        return $this->addEntry([
            'date' => $data['purchase_date'],
            'type' => 'asset_purchase',
            'description' => 'Asset: '.$data['name'],
            'department' => null,
            'job_id' => null,
            'doc_number' => null,
            'debit_account' => 'fixed_assets',
            'credit_account' => self::bankAccount($data['bank']),
            'amount' => (float) $data['cost'],
            'bank' => $data['bank'],
            'created_by' => $userName ?: 'System',
            'receipt_path' => $data['receipt_path'] ?? null,
            'receipt_name' => $data['receipt_name'] ?? null,
        ]);
    }

    /** Owner taking money out of the business: reduces equity, never an expense (not tax deductible). */
    public function postDrawings(array $data, string $userName): LedgerEntry
    {
        return $this->addEntry([
            'date' => $data['date'] ?? now(),
            'type' => 'owner_drawings',
            'description' => trim($data['notes'] ?? '') ?: 'Owner drawings',
            'department' => null,
            'job_id' => null,
            'doc_number' => null,
            'debit_account' => 'equity_drawings',
            'credit_account' => self::bankAccount($data['bank']),
            'amount' => (float) $data['amount'],
            'bank' => $data['bank'],
            'created_by' => $userName ?: 'System',
        ]);
    }

    // Adjust a bank account's starting balance (additive — call again to
    // correct).
    public function postOpeningBalanceAdjustment(string $bank, $amount, string $userName): ?LedgerEntry
    {
        $amt = (float) $amount;

        if (! $amt) {
            return null;
        }

        return $this->addEntry([
            'date' => now(),
            'type' => 'opening_balance',
            'description' => 'Selaraskan baki permulaan Bank '.strtoupper($bank),
            'department' => null,
            'job_id' => null,
            'doc_number' => null,
            'debit_account' => self::bankAccount($bank),
            'credit_account' => 'equity_opening',
            'amount' => $amt,
            'bank' => $bank,
            'created_by' => $userName ?: 'System',
        ]);
    }

    // A director injecting funds into the company (or the company repaying
    // them back) — tracked as a liability per-director (loan_<slug>). 'in'
    // = director → company (raises the liability); 'repayment' = company →
    // director (pays it down).
    public function postDirectorLoan(array $data, string $userName): ?LedgerEntry
    {
        $amt = (float) ($data['amount'] ?? 0);
        $bank = $data['bank'] ?? null;
        $directorName = trim($data['director_name'] ?? '');

        if (! $amt || ! $bank || ! $directorName) {
            return null;
        }

        $account = self::loanAccount($directorName);
        $isIn = ($data['direction'] ?? null) === 'in';
        $notes = trim($data['notes'] ?? '');

        return $this->addEntry([
            'date' => $data['date'] ?? now(),
            'type' => $isIn ? 'director_loan_in' : 'director_loan_repayment',
            'description' => ($isIn ? 'Loan from ' : 'Loan repayment to ').$directorName.($notes ? " — {$notes}" : ''),
            'department' => null,
            'job_id' => null,
            'doc_number' => null,
            'debit_account' => $isIn ? self::bankAccount($bank) : $account,
            'credit_account' => $isIn ? $account : self::bankAccount($bank),
            'amount' => $amt,
            'bank' => $bank,
            'created_by' => $userName ?: 'System',
            'receipt_path' => $data['receipt_path'] ?? null,
            'receipt_name' => $data['receipt_name'] ?? null,
        ]);
    }

    // Moving cash between the company's own bank accounts — pure asset-to-
    // asset movement, debit destination/credit source, so it never touches
    // Revenue/Expense/Equity.
    public function postBankTransfer(array $data, string $userName): ?LedgerEntry
    {
        $amt = (float) ($data['amount'] ?? 0);
        $fromBank = $data['from_bank'] ?? null;
        $toBank = $data['to_bank'] ?? null;

        if (! $amt || ! $fromBank || ! $toBank || $fromBank === $toBank) {
            return null;
        }

        $notes = trim($data['notes'] ?? '');
        $fromLabel = config("kretivco.banks.{$fromBank}.label", $fromBank);
        $toLabel = config("kretivco.banks.{$toBank}.label", $toBank);

        return $this->addEntry([
            'date' => $data['date'] ?? now(),
            'type' => 'bank_transfer',
            'description' => "Transfer {$fromLabel} → {$toLabel}".($notes ? " — {$notes}" : ''),
            'department' => null,
            'job_id' => null,
            'doc_number' => null,
            'debit_account' => self::bankAccount($toBank),
            'credit_account' => self::bankAccount($fromBank),
            'amount' => $amt,
            'bank' => null,
            'created_by' => $userName ?: 'System',
        ]);
    }
}
