<?php

namespace Tests\Feature;

use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinanceControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_director_loan_with_a_receipt_stores_and_serves_the_file(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);

        $response = $this->actingAs($bod)->post(route('finance.director-loan.store'), [
            'direction' => 'in',
            'director_name' => 'Afiq Azlan',
            'amount' => 2100,
            'bank' => 'affin',
            'receipt' => UploadedFile::fake()->create('transfer-proof.pdf', 100),
        ]);

        $response->assertRedirect();
        $entry = LedgerEntry::where('type', 'director_loan_in')->firstOrFail();
        $this->assertNotNull($entry->receipt_path);
        $this->assertSame('transfer-proof.pdf', $entry->receipt_name);
        Storage::disk('public')->assertExists($entry->receipt_path);

        $this->actingAs($bod)->get(route('finance.ledger.receipt', $entry))->assertOk();
    }

    public function test_director_loan_without_a_receipt_still_works(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);

        $response = $this->actingAs($bod)->post(route('finance.director-loan.store'), [
            'direction' => 'in',
            'director_name' => 'Afiq Azlan',
            'amount' => 500,
            'bank' => 'affin',
        ]);

        $response->assertRedirect();
        $entry = LedgerEntry::where('type', 'director_loan_in')->firstOrFail();
        $this->assertNull($entry->receipt_path);
    }

    public function test_staff_cannot_post_finance_entries(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);

        $this->actingAs($staff)->post(route('finance.director-loan.store'), [
            'direction' => 'in',
            'director_name' => 'Afiq Azlan',
            'amount' => 500,
            'bank' => 'affin',
        ])->assertForbidden();
    }
}
