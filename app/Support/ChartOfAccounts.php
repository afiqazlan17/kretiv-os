<?php

namespace App\Support;

// Human-readable identity (code, name, type) for the ledger's account keys
// ('ar', 'bank_mbb', 'revenue_print', 'cogs_print_commission', ...). The
// keys stay the storage format; this only names them for reports.
class ChartOfAccounts
{
    public const ASSET = 'Current Asset';

    public const LIABILITY = 'Liability';

    public const EQUITY = 'Equity';

    public const INCOME = 'Income';

    public const COST = 'Cost of Services';

    public const EXPENSE = 'Operating Expense';

    /**
     * @return array{code: string, name: string, type: string}
     */
    public static function describe(string $key): array
    {
        if ($key === 'ar') {
            return ['code' => 'CA-AR', 'name' => 'Accounts Receivable (Outstanding)', 'type' => self::ASSET];
        }

        if ($key === 'fixed_assets') {
            return ['code' => 'FA-COST', 'name' => 'Fixed Assets (at cost)', 'type' => 'Fixed Asset'];
        }
        if ($key === 'equity_drawings') {
            return ['code' => 'EQ-DRAWINGS', 'name' => 'Owner Drawings', 'type' => self::EQUITY];
        }
        if ($key === 'customer_deposits') {
            return ['code' => 'LI-DEPOSITS', 'name' => 'Customer Deposits (received before invoice)', 'type' => self::LIABILITY];
        }
        if ($key === 'payable_statutory') {
            return ['code' => 'LI-STATUTORY', 'name' => 'Statutory Payable (EPF, SOCSO, EIS, PCB)', 'type' => self::LIABILITY];
        }
        if ($key === 'equity_opening') {
            return ['code' => 'EQ-OPENING', 'name' => 'Opening Balance', 'type' => self::EQUITY];
        }

        if (str_starts_with($key, 'bank_')) {
            $bank = substr($key, 5);

            return ['code' => 'CA-BANK-'.strtoupper($bank), 'name' => 'Bank '.config("kretivco.banks.{$bank}.label", strtoupper($bank)), 'type' => self::ASSET];
        }

        if (str_starts_with($key, 'revenue_')) {
            $dept = substr($key, 8);

            return ['code' => 'IN-'.strtoupper($dept), 'name' => 'Revenue: '.self::department($dept), 'type' => self::INCOME];
        }

        if (str_starts_with($key, 'cogs_')) {
            [$dept, $category] = array_pad(explode('_', substr($key, 5), 2), 2, null);
            $suffix = $category ? ' ('.config("kretivco.expense_categories.{$category}", $category).')' : '';

            return ['code' => 'CS-'.strtoupper($dept).($category ? '-'.strtoupper($category) : ''), 'name' => 'Cost of Service: '.self::department($dept).$suffix, 'type' => self::COST];
        }

        if (str_starts_with($key, 'opex_')) {
            $category = substr($key, 5);

            return ['code' => 'OP-'.strtoupper($category), 'name' => 'Operating Expense: '.config("kretivco.expense_categories.{$category}", $category), 'type' => self::EXPENSE];
        }

        if (str_starts_with($key, 'loan_')) {
            $slug = substr($key, 5);

            return ['code' => 'LI-LOAN-'.strtoupper($slug), 'name' => 'Director Loan: '.ucwords(str_replace('_', ' ', $slug)), 'type' => self::LIABILITY];
        }

        return ['code' => strtoupper($key), 'name' => ucwords(str_replace('_', ' ', $key)), 'type' => 'Other'];
    }

    private static function department(string $key): string
    {
        return config("kretivco.departments.{$key}.label", ucfirst($key));
    }
}
