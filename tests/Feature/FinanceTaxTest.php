<?php

namespace Tests\Feature;

use App\Http\Controllers\FinanceController;
use App\Http\Controllers\TaxSummaryController;
use App\Models\Asset;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Services\FinanceReports;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceTaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_capital_allowance_runs_initial_plus_annual_then_annual_until_claimed(): void
    {
        // Computer RM 5,000: 20% IA + 20% AA = 40% in year 1, then 20% a year.
        $laptop = new Asset(['name' => 'Laptop', 'category' => 'computer', 'purchase_date' => '2026-03-01', 'cost' => 5000]);
        $this->assertSame(['allowance' => 2000.0, 'twdv' => 3000.0], $laptop->capitalAllowance(2026));
        $this->assertSame(['allowance' => 1000.0, 'twdv' => 2000.0], $laptop->capitalAllowance(2027));
        $this->assertSame(['allowance' => 1000.0, 'twdv' => 0.0], $laptop->capitalAllowance(2029));
        $this->assertSame(0.0, $laptop->capitalAllowance(2030)['allowance']);
        $this->assertSame(0.0, $laptop->capitalAllowance(2025)['allowance']);

        // Small value asset (<= RM 2,000): 100% in the year bought.
        $tripod = new Asset(['name' => 'Tripod', 'category' => 'machinery', 'purchase_date' => '2026-05-01', 'cost' => 450]);
        $this->assertSame(['allowance' => 450.0, 'twdv' => 0.0], $tripod->capitalAllowance(2026));
    }

    public function test_buying_an_asset_is_not_an_expense_and_the_balance_sheet_still_balances(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $this->actingAs($bod)->post(route('finance.opening-balance.store'), ['bank' => 'mbb', 'amount' => 10000]);
        $this->actingAs($bod)->post(route('finance.assets.store'), ['name' => 'Camera', 'category' => 'machinery', 'purchase_date' => now()->toDateString(), 'cost' => 6000, 'bank' => 'mbb'])->assertRedirect();
        $this->actingAs($bod)->post(route('finance.drawings.store'), ['bank' => 'mbb', 'amount' => 1000])->assertRedirect();

        $reports = new FinanceReports(FinanceController::entriesFor($bod));
        $this->assertEquals(0, $reports->profitAndLoss()['opex']);
        $bs = $reports->balanceSheet();
        $this->assertEquals(6000, $bs['fixed']);
        $this->assertEquals(3000, $bs['banks']['mbb']);
        $this->assertEquals(1000, $bs['drawings']);
        $this->assertTrue($bs['balanced']);
    }

    public function test_tax_summary_adds_back_non_deductible_and_half_of_entertainment_less_capital_allowance(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        LedgerEntry::create(['date' => '2026-02-01', 'type' => 'invoice', 'description' => 'x', 'debit_account' => 'ar', 'credit_account' => 'revenue_print', 'amount' => 10000]);
        $this->actingAs($bod)->post(route('finance.expense.store'), ['category' => 'entertainment', 'amount' => 400, 'bank' => 'mbb', 'date' => '2026-03-01']);
        $this->actingAs($bod)->post(route('finance.expense.store'), ['category' => 'other', 'amount' => 100, 'bank' => 'mbb', 'date' => '2026-03-02', 'tax_treatment' => 'non_deductible']);
        Asset::create(['name' => 'Laptop', 'category' => 'computer', 'purchase_date' => '2026-03-01', 'cost' => 5000, 'bank' => 'mbb']);

        $t = TaxSummaryController::summary(2026);

        $this->assertSame('partial', LedgerEntry::where('description', 'Entertainment (client)')->value('tax_treatment'));
        $this->assertEquals(9500, $t['pl']['net']);           // 10,000 - 400 - 100
        $this->assertEquals(300, $t['addBack']);               // 100 + 400 / 2
        $this->assertEquals(2000, $t['capitalAllowance']);
        $this->assertEquals(7800, $t['adjustedIncome']);       // 9,500 + 300 - 2,000
        $this->actingAs($bod)->get(route('finance.tax', ['year' => 2026]))->assertOk()->assertSee('RM 7,800.00');
    }
}
