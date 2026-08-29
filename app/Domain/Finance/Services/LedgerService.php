<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\LedgerEntry;
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
                'description' => "Reversal — {$orig->description}",
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
            return LedgerEntry::where('job_id', $job->job_id)->where('type', 'invoice')->where('reversed', false)->first();
        }

        $this->reverseEntries(fn ($e) => $e->job_id === $job->job_id && $e->type === 'invoice', $userName);

        return $this->addEntry([
            'date' => now(),
            'type' => 'invoice',
            'description' => "Invois {$docNumber} — {$job->customer?->name}",
            'department' => $job->department,
            'job_id' => $job->job_id,
            'doc_number' => $docNumber,
            'debit_account' => 'ar',
            'credit_account' => self::revenueAccount($job->department),
            'amount' => $amount,
            'bank' => null,
            'created_by' => $userName ?: 'System',
        ]);
    }

    // Receipt issued: payment received, clears what was owed. Same
    // supersede rule as invoices.
    public function postReceiptEntry($job, string $docNumber, string $userName, $amountOverride = null): ?LedgerEntry
    {
        $amount = $this->computeReceiptAmount($job, $amountOverride);
        $decision = $this->decideDocPosting($job->job_id, 'receipt', $amount);

        if ($decision === 'skip') {
            return null;
        }

        if ($decision === 'unchanged') {
            return LedgerEntry::where('job_id', $job->job_id)->where('type', 'receipt')->where('reversed', false)->first();
        }

        $bank = $job->bank ?: 'mbb';
        $this->reverseEntries(fn ($e) => $e->job_id === $job->job_id && $e->type === 'receipt', $userName);

        return $this->addEntry([
            'date' => now(),
            'type' => 'receipt',
            'description' => "Resit {$docNumber} — {$job->customer?->name}",
            'department' => $job->department,
            'job_id' => $job->job_id,
            'doc_number' => $docNumber,
            'debit_account' => self::bankAccount($bank),
            'credit_account' => 'ar',
            'amount' => $amount,
            'bank' => $bank,
            'created_by' => $userName ?: 'System',
        ]);
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
        $categoryLabel = config("finance.expense_categories.{$category}", $category);

        return $this->addEntry([
            'date' => $data['date'] ?? now(),
            'type' => $department ? 'job_expense' : 'operating_expense',
            'description' => trim($data['notes'] ?? '') ?: $categoryLabel,
            'department' => $department,
            'job_id' => $data['job_id'] ?? null,
            'doc_number' => null,
            'debit_account' => $debitAccount,
            'credit_account' => self::bankAccount($bank),
            'amount' => $amount,
            'bank' => $bank,
            'created_by' => $userName ?: 'System',
            'receipt_path' => $data['receipt_path'] ?? null,
            'receipt_name' => $data['receipt_name'] ?? null,
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
        $fromLabel = config("jobs.banks.{$fromBank}.label", $fromBank);
        $toLabel = config("jobs.banks.{$toBank}.label", $toBank);

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
