<?php

namespace Tests\Feature;

use App\Http\Controllers\BankImportController;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class FinancePhaseThreeTest extends TestCase
{
    use RefreshDatabase;

    private function bod(): User
    {
        return User::factory()->create(['role' => User::ROLE_BOD]);
    }

    public function test_accountant_pack_zips_the_workbook_and_receipts_for_the_period(): void
    {
        Storage::fake('public');
        $bod = $this->bod();
        $this->actingAs($bod)->post(route('finance.expense.store'), [
            'category' => 'utilities', 'amount' => 88, 'bank' => 'mbb', 'date' => '2026-08-15',
            'receipt' => UploadedFile::fake()->image('tnb.jpg'),
        ]);

        $response = $this->actingAs($bod)->get(route('finance.accountant.download', ['from' => '2026-08-01', 'to' => '2026-08-31']));
        $response->assertOk();

        $zip = new ZipArchive;
        $zip->open($response->baseResponse->getFile()->getPathname());
        $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i));
        $this->assertTrue($names->contains('Kretivco_Accounts_2026-08-01_to_2026-08-31.xlsx'));
        $this->assertTrue($names->contains(fn ($n) => str_starts_with($n, 'receipts/2026-08-15_') && str_ends_with($n, 'tnb.jpg')));
    }

    public function test_statement_csv_is_parsed_in_debit_credit_or_single_amount_form(): void
    {
        $maybank = "Account Statement\nDate,Transaction Description,Debit,Credit,Balance\n15/08/2026,TNB BILL,88.00,,1000.00\n16/08/2026,IBG FROM JASMI,,\"1,200.50\",2112.50\n";
        $this->assertSame([
            ['date' => '2026-08-15', 'description' => 'TNB BILL', 'amount' => -88.0],
            ['date' => '2026-08-16', 'description' => 'IBG FROM JASMI', 'amount' => 1200.5],
        ], BankImportController::parse($maybank));

        $single = "Date,Description,Amount\n2026-08-20,Canva,-54.90\n21/08/2026,Adobe,54.90-\n";
        $this->assertSame([
            ['date' => '2026-08-20', 'description' => 'Canva', 'amount' => -54.9],
            ['date' => '2026-08-21', 'description' => 'Adobe', 'amount' => -54.9],
        ], BankImportController::parse($single));
    }

    public function test_bank_import_matches_the_ledger_and_lists_the_rest(): void
    {
        $bod = $this->bod();
        LedgerEntry::create(['date' => '2026-08-14', 'type' => 'operating_expense', 'description' => 'Electricity', 'debit_account' => 'opex_utilities', 'credit_account' => 'bank_mbb', 'amount' => 88, 'bank' => 'mbb']);
        LedgerEntry::create(['date' => '2026-08-16', 'type' => 'operating_expense', 'description' => 'Printer ink', 'debit_account' => 'opex_other', 'credit_account' => 'bank_mbb', 'amount' => 40, 'bank' => 'mbb']);

        $csv = "Date,Description,Debit,Credit\n15/08/2026,TNB BILL,88.00,\n17/08/2026,GRAB,23.50,\n";
        $this->actingAs($bod)->post(route('finance.bank-import.upload'), ['bank' => 'mbb', 'statement' => UploadedFile::fake()->createWithContent('stmt.csv', $csv)])
            ->assertRedirect(route('finance.bank-import'));

        $this->actingAs($bod)->get(route('finance.bank-import'))->assertOk()
            ->assertSee('Electricity')          // matched (1 day apart)
            ->assertSee('GRAB')->assertSee('Record expense') // on statement, not in ledger
            ->assertSee('Printer ink');         // in ledger, not on statement
    }
}
