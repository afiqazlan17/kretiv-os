<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Gate;

// The Settings page's "Access Reference" table, worked out from the same
// checks the app enforces (policies, module access, User role helpers)
// rather than typed by hand, so it can't drift from what users can really do.
class AccessMatrix
{
    /** @return array<int, array{0: string, 1: array<string, bool>}> */
    public static function rows(): array
    {
        $jobs = fn (User $u) => $u->canAccess('jobs');

        $capabilities = [
            'See every department' => fn (User $u) => $u->seesAllDepartments(),
            'Create and update jobs (own departments)' => $jobs,
            'Issue quotations and proforma invoices' => fn (User $u) => $jobs($u) && $u->canIssueDocument('quotation') && $u->canIssueDocument('proforma'),
            'Issue invoices' => fn (User $u) => $jobs($u) && $u->canIssueDocument('invoice'),
            'Issue receipts (confirm payment received)' => fn (User $u) => $jobs($u) && $u->canIssueDocument('receipt'),
            'Issue delivery orders / handover forms' => fn (User $u) => $jobs($u) && $u->canIssueDocument('delivery'),
            'Issue credit notes' => fn (User $u) => $jobs($u) && $u->canIssueDocument('credit_note'),
            'Send artwork for customer approval' => fn (User $u) => $jobs($u),
            'Void a recorded payment' => fn (User $u) => $jobs($u) && $u->canVoidPayments(),
            'Delete a job permanently' => fn (User $u) => $jobs($u) && Gate::forUser($u)->allows('delete', new Job),
            'Add customers, vendors and items' => $jobs,
            'Edit customers' => fn (User $u) => $jobs($u) && Gate::forUser($u)->allows('update', new Customer),
            'Edit vendors and items' => fn (User $u) => $jobs($u) && Gate::forUser($u)->allows('update', new Vendor),
            'Delete customers, vendors and items' => fn (User $u) => $jobs($u) && Gate::forUser($u)->allows('delete', new Customer),
            'Reports and Excel export' => fn (User $u) => $jobs($u) && ($u->isBod() || $u->isDeptHead()),
            'Finance module (own departments)' => fn (User $u) => $u->canAccess('finance') && $u->canManageFinance(),
            'Company finance (bank balances, director loans, transfers)' => fn (User $u) => $u->canAccess('finance') && $u->seesCompanyFinance(),
            'Manage users and settings' => fn (User $u) => Gate::forUser($u)->allows('create', User::class),
        ];

        $people = collect(array_keys(config('kretivco.roles')))
            ->mapWithKeys(fn (string $role) => [$role => (new User)->forceFill(['role' => $role, 'active' => true])]);

        return collect($capabilities)
            ->map(fn (callable $check, string $label) => [$label, $people->map(fn (User $u) => (bool) $check($u))->all()])
            ->values()->all();
    }
}
