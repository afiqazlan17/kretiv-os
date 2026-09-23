<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Job;
use App\Models\JobDocument;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\DocumentData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentControllerTest extends TestCase
{
    use RefreshDatabase;

    private function job(): Job
    {
        $customer = Customer::create(['customer_id' => 'C001', 'name' => 'Acme']);

        return Job::create([
            'job_id' => 'KP-2026-001', 'customer_id' => $customer->id, 'department' => 'print',
            'job_type' => 'Banner', 'job_type_category' => 'client_project', 'status' => Job::STATUS_IN_PROGRESS,
            'estimation_value' => 1000,
        ]);
    }

    private function generate(User $user, Job $job, string $type, array $payload = [])
    {
        return $this->actingAs($user)->postJson(route('jobs.documents.generate', [$job, $type]), array_merge(['title' => $job->job_type], $payload));
    }

    public function test_generating_an_invoice_downloads_a_pdf_and_posts_a_ledger_entry(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        $response = $this->generate($bod, $job, 'invoice');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertDatabaseHas('ledger_entries', ['job_id' => 'KP-2026-001', 'type' => 'invoice', 'amount' => 1000]);
        $this->assertSame(1, LedgerEntry::count());
        $this->assertDatabaseHas('job_documents', ['job_id' => $job->id, 'doc_type' => 'invoice']);
    }

    public function test_invoice_total_includes_delivery_and_discount(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        $this->generate($bod, $job, 'invoice', [
            'items' => [['item' => 'Banner', 'desc' => '', 'qty' => 2, 'price' => 50]],
            'delivery' => 10,
            'discount' => 5,
        ])->assertOk();

        $this->assertDatabaseHas('ledger_entries', ['type' => 'invoice', 'amount' => 105]);
    }

    public function test_generating_a_quotation_does_not_post_a_ledger_entry(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        $response = $this->generate($bod, $job, 'quotation');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertSame(0, LedgerEntry::count());
        $this->assertDatabaseHas('job_documents', ['job_id' => $job->id, 'doc_type' => 'quotation']);
    }

    public function test_generating_a_proforma_invoice_does_not_post_a_ledger_entry(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        $this->generate($bod, $job, 'proforma')->assertOk();

        $this->assertSame(0, LedgerEntry::count());
        $this->assertDatabaseHas('job_documents', ['job_id' => $job->id, 'doc_type' => 'proforma']);
    }

    public function test_documents_are_blocked_until_the_job_is_claimed(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();
        $job->update(['status' => Job::STATUS_POTENTIAL]);

        foreach (['quotation', 'proforma', 'invoice', 'receipt'] as $type) {
            $this->generate($bod, $job, $type)->assertStatus(422);
        }

        $this->assertSame(0, JobDocument::count());
        $this->actingAs($bod)->getJson(route('jobs.documents.draft', [$job, 'quotation']))->assertStatus(422);
    }

    public function test_a_receipt_needs_an_existing_invoice(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        $this->generate($bod, $job, 'receipt')->assertStatus(422);

        $this->assertSame(0, JobDocument::where('doc_type', 'receipt')->count());
        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_a_receipt_can_record_a_partial_payment_against_the_invoice(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();
        $this->generate($bod, $job, 'invoice')->assertOk();

        $this->generate($bod, $job, 'receipt', ['amount_paid' => 400, 'payment_method' => 'Cash'])->assertOk();

        $this->assertDatabaseHas('ledger_entries', ['type' => 'receipt', 'amount' => 400, 'reversed' => false]);
    }

    public function test_a_receipt_cannot_exceed_the_invoice_total(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();
        $this->generate($bod, $job, 'invoice')->assertOk();

        $this->generate($bod, $job, 'receipt', ['amount_paid' => 1500])->assertStatus(422);

        $this->assertSame(0, LedgerEntry::where('type', 'receipt')->count());
    }

    public function test_the_draft_hands_the_modal_its_defaults_and_the_invoice_to_pay_against(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();
        $this->generate($bod, $job, 'invoice')->assertOk();

        $draft = $this->actingAs($bod)->getJson(route('jobs.documents.draft', [$job, 'receipt']))->assertOk()->json();

        $this->assertSame('RC-'.now()->year.'-001', $draft['doc_number']);
        $this->assertSame('INV-'.now()->year.'-001', $draft['invoice_number']);
        $this->assertEquals(1000, $draft['invoice_total']);
        $this->assertEquals(1000, $draft['defaults']['amount_paid']);
        $this->assertSame('Banner', $draft['defaults']['title']);
        $this->assertSame($bod->name, $draft['defaults']['by_staff']);
    }

    public function test_previewing_renders_a_pdf_without_storing_or_posting_anything(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        $response = $this->actingAs($bod)->postJson(route('jobs.documents.preview', [$job, 'invoice']), ['title' => 'Banner']);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertSame(0, JobDocument::count());
        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_saving_writes_items_delivery_and_discount_back_to_the_job(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        $this->actingAs($bod)->postJson(route('jobs.documents.save', [$job, 'quotation']), [
            'title' => 'Big Banner',
            'items' => [['item' => 'Banner', 'desc' => '3x6ft', 'qty' => 2, 'price' => 100]],
            'delivery' => 20,
            'discount' => 10,
        ])->assertOk();

        $job->refresh();
        $this->assertSame('Big Banner', $job->job_type);
        $this->assertEquals(210, $job->estimation_value);
        $this->assertEquals(20, $job->delivery_amount);
        $this->assertEquals(10, $job->discount_amount);
        $this->assertSame('3x6ft', $job->line_items[0]['desc']);
        $this->assertDatabaseHas('activity_log', ['job_id' => $job->id, 'field_changed' => 'estimation_value', 'new_value' => '210.00']);
    }

    public function test_note_wording_differs_by_document_type(): void
    {
        $bank = config('kretivco.bank_details.mbb');

        $quotation = implode("\n", DocumentData::defaultNotes('quotation', $bank));
        $invoice = implode("\n", DocumentData::defaultNotes('invoice', $bank));
        $receipt = implode("\n", DocumentData::defaultNotes('receipt', $bank));

        $this->assertStringContainsString('80% deposit', $quotation);
        $this->assertStringContainsString('Please make payment to MAYBANK | 5621-0668-8317 | KRETIVCO MEDIAWORKS.', $quotation);
        $this->assertSame('AFFIN', config('kretivco.bank_details.affin.label'));
        $this->assertSame('105630012033', config('kretivco.bank_details.affin.acct'));
        $this->assertStringContainsString('Payment due within 7 days from the invoice date.', $invoice);
        $this->assertStringContainsString('surcharge as agreed in the service agreement', $invoice);
        $this->assertStringNotContainsString('80% deposit', $invoice);
        $this->assertSame($invoice, implode("\n", DocumentData::defaultNotes('proforma', $bank)));
        $this->assertStringContainsString('Please retain this receipt for your reference.', $receipt);
        $this->assertStringNotContainsString('Please make payment to', $receipt);
    }

    public function test_a_generated_document_can_be_re_downloaded_from_its_history(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        $this->generate($bod, $job, 'quotation');
        $document = JobDocument::first();

        $response = $this->actingAs($bod)->get(route('jobs.documents.show', [$job, $document]));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_regenerating_the_same_document_type_supersedes_the_previous_one(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        $this->generate($bod, $job, 'quotation');
        $this->generate($bod, $job, 'quotation');

        $this->assertSame(2, JobDocument::where('job_id', $job->id)->where('doc_type', 'quotation')->count());
    }

    public function test_combining_two_same_customer_jobs_creates_one_document_shared_by_both(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();
        $sibling = Job::create([
            'job_id' => 'KB-2026-001', 'customer_id' => $job->customer_id, 'department' => 'brand',
            'job_type' => 'Design', 'job_type_category' => 'client_project', 'status' => Job::STATUS_IN_PROGRESS,
            'estimation_value' => 500,
        ]);

        $response = $this->actingAs($bod)->post(route('jobs.documents.combine', $job), [
            'doc_type' => 'quotation',
            'job_ids' => [$sibling->id],
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $jobDoc = JobDocument::where('job_id', $job->id)->first();
        $siblingDoc = JobDocument::where('job_id', $sibling->id)->first();
        $this->assertNotNull($jobDoc);
        $this->assertNotNull($siblingDoc);
        $this->assertSame($jobDoc->doc_number, $siblingDoc->doc_number);
        $this->assertStringEndsWith('-C', $jobDoc->doc_number);
        $this->assertSame($jobDoc->storage_path, $siblingDoc->storage_path);
    }

    public function test_combining_jobs_with_mismatched_statuses_is_rejected(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();
        $sibling = Job::create([
            'job_id' => 'KB-2026-001', 'customer_id' => $job->customer_id, 'department' => 'brand',
            'job_type' => 'Design', 'job_type_category' => 'client_project', 'status' => Job::STATUS_POTENTIAL,
            'estimation_value' => 500,
        ]);

        $response = $this->actingAs($bod)->post(route('jobs.documents.combine', $job), [
            'doc_type' => 'quotation',
            'job_ids' => [$sibling->id],
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, JobDocument::count());
    }

    public function test_combining_with_a_job_from_a_different_customer_is_ignored(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();
        $otherCustomer = Customer::create(['customer_id' => 'C002', 'name' => 'Other Co']);
        $unrelated = Job::create([
            'job_id' => 'KB-2026-002', 'customer_id' => $otherCustomer->id, 'department' => 'brand',
            'job_type' => 'Design', 'job_type_category' => 'client_project', 'status' => Job::STATUS_IN_PROGRESS,
            'estimation_value' => 500,
        ]);

        $response = $this->actingAs($bod)->post(route('jobs.documents.combine', $job), [
            'doc_type' => 'quotation',
            'job_ids' => [$unrelated->id],
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, JobDocument::count());
    }

    public function test_discount_row_only_appears_when_there_is_a_discount(): void
    {
        if (! shell_exec('command -v pdftotext')) {
            $this->markTestSkipped('pdftotext not installed.');
        }
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();
        $payload = ['title' => 'Banner', 'items' => [['item' => 'Banner', 'desc' => '', 'qty' => 1, 'price' => 100]], 'delivery' => 0];

        $none = $this->actingAs($bod)->post(route('jobs.documents.preview', [$job, 'quotation']), $payload + ['discount' => 0]);
        $some = $this->actingAs($bod)->post(route('jobs.documents.preview', [$job, 'quotation']), $payload + ['discount' => 10]);

        $this->assertStringNotContainsString('Delivery', $this->pdfText($none->getContent()));
        $this->assertStringNotContainsString('Discount', $this->pdfText($none->getContent()));
        $this->assertStringContainsString('Discount', $this->pdfText($some->getContent()));
    }

    private function pdfText(string $pdf): string
    {
        $file = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($file, $pdf);
        $text = (string) shell_exec('pdftotext '.escapeshellarg($file).' - 2>/dev/null');
        unlink($file);

        return $text;
    }

    public function test_new_job_form_can_preview_a_quotation_before_the_job_exists(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $customer = Customer::create(['customer_id' => 'C009', 'name' => 'Preview Person', 'company' => 'Preview Sdn Bhd']);

        $response = $this->actingAs($bod)->postJson(route('jobs.quotation-preview'), [
            'customer_id' => $customer->id, 'bank' => 'affin', 'title' => 'Kad Kahwin',
            'items' => [['item' => 'Kad Kahwin', 'qty' => 2, 'price' => 50]],
        ]);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertSame(0, Job::count());
    }
}
