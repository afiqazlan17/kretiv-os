<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Job;
use App\Models\JobDocument;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\DocumentData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
        $job->update(['status' => Job::STATUS_NEW, 'pic' => null]);

        foreach (['quotation', 'proforma', 'invoice', 'receipt'] as $type) {
            $this->generate($bod, $job, $type)->assertStatus(422);
        }

        $this->assertSame(0, JobDocument::count());
        $this->actingAs($bod)->getJson(route('jobs.documents.draft', [$job, 'quotation']))->assertStatus(422);

        // Once taken in (Potential) the quotation can go out, but not the invoice yet.
        $job->update(['status' => Job::STATUS_POTENTIAL, 'pic' => $bod->name]);
        $this->actingAs($bod)->getJson(route('jobs.documents.draft', [$job, 'quotation']))->assertOk();
        $this->actingAs($bod)->getJson(route('jobs.documents.draft', [$job, 'invoice']))->assertStatus(422);
    }

    public function test_a_deposit_can_be_receipted_before_the_invoice_and_the_invoice_takes_it_off(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();
        $total = DocumentData::jobTotal($job);

        $this->generate($bod, $job, 'receipt', ['amount_paid' => 300])->assertOk();
        $this->assertEquals(300, DocumentData::paidSoFar($job));

        $invoiceDraft = $this->actingAs($bod)->getJson(route('jobs.documents.draft', [$job, 'invoice']))->json();
        $this->assertEquals(300, $invoiceDraft['paid_before']);
        $invoice = DocumentData::build($job, 'invoice', [], 'INV-1', 'Afiq', null, null, 300);
        $this->assertEquals(300, $invoice['deposit_paid']);
        $this->assertEquals(round($invoice['total'] - 300, 2), $invoice['balance_due']);

        $deposit = DocumentData::build($job, 'receipt', ['amount_paid' => 100], 'RC-1', 'Afiq', $total, null, 0);
        $this->assertSame('DEPOSIT RECEIPT', $deposit['doc_title']);
        $final = DocumentData::build($job, 'receipt', [], 'RC-2', 'Afiq', $total, null, 300);
        $this->assertSame('PAYMENT RECEIPT', $final['doc_title']);
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

    public function test_a_deposit_then_the_balance_are_two_receipts_and_both_stay_on_the_ledger(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();
        $this->generate($bod, $job, 'invoice')->assertOk();
        $year = now()->year;

        $this->generate($bod, $job, 'receipt', ['amount_paid' => 800])->assertOk();

        // The second receipt defaults to what's still owed and gets its own number.
        $draft = $this->actingAs($bod)->getJson(route('jobs.documents.draft', [$job, 'receipt']))->json();
        $this->assertSame('RCP'.now()->format('ym').'0002-P', $draft['doc_number']);
        $this->assertEquals(800, $draft['paid_before']);
        $this->assertEquals(200, $draft['defaults']['amount_paid']);

        $this->generate($bod, $job, 'receipt', ['amount_paid' => 250])->assertStatus(422); // more than owed
        $this->generate($bod, $job, 'receipt', ['amount_paid' => 200])->assertOk();

        $this->assertSame(2, LedgerEntry::where('type', 'receipt')->where('reversed', false)->count());
        $this->assertEquals(1000, DocumentData::paidSoFar($job));
        $this->assertDatabaseHas('ledger_entries', ['doc_number' => 'RCP'.now()->format('ym').'0001-P', 'amount' => 800, 'reversed' => false]);
        $this->assertDatabaseHas('ledger_entries', ['doc_number' => 'RCP'.now()->format('ym').'0002-P', 'amount' => 200, 'reversed' => false]);

        $this->generate($bod, $job, 'receipt', ['amount_paid' => 1])->assertStatus(422); // fully paid
    }

    public function test_finance_can_void_a_payment_but_staff_cannot(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);
        $job = $this->job();
        $this->generate($bod, $job, 'invoice')->assertOk();
        $this->generate($bod, $job, 'receipt', ['amount_paid' => 300])->assertOk();
        $entry = LedgerEntry::where('type', 'receipt')->first();

        $this->actingAs($staff)->post(route('jobs.payments.void', [$job, $entry]))->assertForbidden();
        $this->actingAs($bod)->post(route('jobs.payments.void', [$job, $entry]))->assertRedirect();

        $this->assertTrue($entry->refresh()->reversed);
        $this->assertEquals(0, DocumentData::paidSoFar($job));
        $this->actingAs($bod)->get(route('jobs.show', $job))->assertSee('(Voided)');
    }

    public function test_the_draft_hands_the_modal_its_defaults_and_the_invoice_to_pay_against(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();
        $this->generate($bod, $job, 'invoice')->assertOk();

        $draft = $this->actingAs($bod)->getJson(route('jobs.documents.draft', [$job, 'receipt']))->assertOk()->json();

        $this->assertSame('RCP'.now()->format('ym').'0001-P', $draft['doc_number']);
        $this->assertSame('INV'.now()->format('ym').'0001-P', $draft['invoice_number']);
        $this->assertEquals(1000, $draft['invoice_total']);
        $this->assertEquals(1000, $draft['defaults']['amount_paid']);
        $this->assertSame('Banner', $draft['defaults']['title']);
        $this->assertSame($bod->shortName(), $draft['defaults']['by_staff']);
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

    public function test_edited_notes_are_kept_for_the_job_and_reopen_in_the_modal(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        $this->actingAs($bod)->postJson(route('jobs.documents.save', [$job, 'quotation']), [
            'title' => 'Banner',
            'notes' => "Special price for Acme only.\n\nValid until end of month.",
        ])->assertOk();

        $this->assertSame(['Special price for Acme only.', 'Valid until end of month.'], $job->refresh()->document_notes['quotation']);

        $draft = $this->actingAs($bod)->getJson(route('jobs.documents.draft', [$job, 'quotation']))->assertOk();
        $draft->assertJsonPath('notes_custom', true);
        $draft->assertJsonPath('defaults.notes', ['Special price for Acme only.', 'Valid until end of month.']);
        $draft->assertJsonPath('standard_notes', DocumentData::defaultNotes('quotation', DocumentData::bank($job)));

        // Other document types keep the standard wording.
        $this->actingAs($bod)->getJson(route('jobs.documents.draft', [$job, 'invoice']))->assertJsonPath('notes_custom', false);
    }

    public function test_use_default_notes_clears_the_jobs_saved_notes(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();
        $job->update(['document_notes' => ['quotation' => ['Old custom line']]]);

        $this->actingAs($bod)->postJson(route('jobs.documents.save', [$job, 'quotation']), [
            'title' => 'Banner',
            'use_default_notes' => true,
        ])->assertOk();

        $this->assertNull($job->refresh()->document_notes);
    }

    public function test_generating_a_document_also_keeps_its_edited_notes(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        $this->generate($bod, $job, 'invoice', ['notes' => 'Pay by Friday please.'])->assertOk();

        $this->assertSame(['Pay by Friday please.'], $job->refresh()->document_notes['invoice']);
    }

    public function test_quotation_is_valid_14_days_invoice_due_in_14_and_proforma_in_7(): void
    {
        $job = $this->job();

        $this->assertSame(['Valid until', now()->addDays(14)->format('d M Y')], DocumentData::build($job, 'quotation', [], 'QT-1', 'Afiq')['header_extra']);
        $this->assertSame(['Due', now()->addDays(14)->format('d M Y')], DocumentData::build($job, 'invoice', [], 'INV-1', 'Afiq')['header_extra']);
        $this->assertSame(['Due', now()->addDays(7)->format('d M Y')], DocumentData::build($job, 'proforma', [], 'PRF-1', 'Afiq')['header_extra']);
        $this->assertSame(['Due', '15 Oct 2026'], DocumentData::build($job, 'invoice', ['due_date' => '2026-10-15'], 'INV-1', 'Afiq')['header_extra']);
        $this->assertNull(DocumentData::build($job, 'receipt', [], 'RC-1', 'Afiq', 1000)['header_extra']);
    }

    public function test_receipt_references_its_invoice_and_documents_show_the_customer_phone(): void
    {
        $job = $this->job();
        $job->customer->update(['phone' => '012-3456789']);

        $doc = DocumentData::build($job->refresh(), 'receipt', [], 'RC-1', 'Afiq', 1000, 'INV-2026-001');

        $this->assertSame('INV-2026-001', $doc['invoice_number']);
        $this->assertSame('+60123456789', $doc['customer']['phone']);
    }

    public function test_note_wording_differs_by_document_type_and_department(): void
    {
        $bank = config('kretivco.bank_details.mbb');
        $notes = fn (string $type, ?string $dept = null, bool $final = true) => implode("\n", DocumentData::defaultNotes($type, $bank, $dept, $final));

        $this->assertStringContainsString('80% deposit', $notes('quotation', 'print'));
        $this->assertStringContainsString('50% deposit before work begins, 30% upon system demo/UAT', $notes('quotation', 'tech'));
        $this->assertStringContainsString('usage rights are released', $notes('quotation', 'brand'));
        $this->assertStringContainsString('balance is due 7 days before the event', $notes('quotation', 'event'));
        $this->assertStringContainsString('Payment is due within 14 days', $notes('invoice', 'print'));
        $this->assertStringContainsString('Balance payment is due 7 days before the event date.', $notes('invoice', 'event'));
        $this->assertStringContainsString(config('kretivco.brand.email'), $notes('invoice'));
        $this->assertStringContainsString('It is not a tax invoice', $notes('proforma'));
        $this->assertStringContainsString('confirms full payment', $notes('receipt'));
        $this->assertStringContainsString('confirms deposit received', $notes('receipt', 'print', false));
        $this->assertSame('AFFIN', config('kretivco.bank_details.affin.label'));
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

        $this->assertDoesNotMatchRegularExpression('/^Delivery$/m', $this->pdfText($none->getContent()));
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

    public function test_quotation_notes_endpoint_returns_the_default_quotation_notes(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);

        $response = $this->actingAs($bod)->getJson(route('jobs.quotation-notes', ['bank' => 'affin']));

        $response->assertOk();
        $this->assertStringContainsString('Prices are based on the specifications above.', $response->json('notes.0'));
    }

    public function test_new_job_preview_can_override_the_notes(): void
    {
        if (! shell_exec('command -v pdftotext')) {
            $this->markTestSkipped('pdftotext not installed.');
        }
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);

        $response = $this->actingAs($bod)->post(route('jobs.quotation-preview'), [
            'title' => 'Kad Kahwin', 'items' => [['item' => 'Kad Kahwin', 'qty' => 1, 'price' => 50]],
            'notes' => "Custom note line one.\nCustom note line two.",
        ]);

        $text = $this->pdfText($response->getContent());
        $this->assertStringContainsString('Custom note line one.', $text);
        $this->assertStringNotContainsString('Please indicate quotation number', $text);
    }

    public function test_issued_by_shows_the_company_name_and_every_document_is_on_the_job_page(): void
    {
        if (! shell_exec('command -v pdftotext')) {
            $this->markTestSkipped('pdftotext not installed.');
        }
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        $response = $this->actingAs($bod)->post(route('jobs.documents.preview', [$job, 'quotation']), [
            'title' => 'Banner', 'items' => [['item' => 'Banner', 'qty' => 1, 'price' => 100]],
        ]);
        $this->assertStringContainsString('Kretivco Mediaworks', $this->pdfText($response->getContent()));

        $this->actingAs($bod)->get(route('jobs.show', $job))
            ->assertOk()->assertSee('Proforma Invoice')->assertSee('Delivery Order')->assertSee('Credit Note')->assertSee('Quotation', false);
    }

    public function test_credit_note_needs_an_invoice_and_takes_the_amount_off_revenue_and_what_is_owed(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        $this->generate($bod, $job, 'credit_note', ['credit_amount' => 50])->assertStatus(422);
        $this->generate($bod, $job, 'invoice')->assertOk();
        $invoiceAmount = (float) LedgerEntry::where('type', 'invoice')->value('amount');

        $this->generate($bod, $job, 'credit_note', ['credit_amount' => $invoiceAmount + 1])->assertStatus(422);
        $this->generate($bod, $job, 'credit_note', ['credit_amount' => 50, 'credit_reason' => 'discount', 'credit_reason_text' => 'Loyalty'])->assertOk();

        $cn = LedgerEntry::where('type', 'credit_note')->first();
        $this->assertEquals(50, $cn->amount);
        $this->assertSame('ar', $cn->credit_account);
        $this->assertStringStartsWith('CN', $cn->doc_number);
        $this->assertEquals(50, DocumentData::creditedSoFar($job));

        // The receipt now only asks for what's left after the credit.
        $draft = $this->actingAs($bod)->getJson(route('jobs.documents.draft', [$job, 'receipt']))->json();
        $this->assertEquals($invoiceAmount - 50, $draft['invoice_total']);
    }

    public function test_delivery_order_for_print_and_handover_form_for_other_departments(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();
        $job->update(['po_number' => 'PO-7788']);

        $this->generate($bod, $job, 'delivery')->assertOk();
        $doc = JobDocument::where('doc_type', 'delivery')->first();
        $this->assertStringStartsWith('DO', $doc->doc_number);
        $this->assertSame(0, LedgerEntry::count());

        $built = DocumentData::build($job->refresh(), 'delivery', [], 'DO-1', 'Afiq');
        $this->assertSame('DELIVERY ORDER', $built['doc_title']);
        $this->assertSame('PO-7788', $built['po_number']);

        $job->update(['department' => 'tech']);
        $this->assertSame('HANDOVER FORM', DocumentData::build($job->refresh(), 'delivery', [], 'HO-1', 'Afiq')['doc_title']);
        $this->assertStringStartsWith('HO', DocumentData::number('delivery', $job));
    }

    public function test_po_is_saved_on_the_job_and_a_mismatch_is_flagged(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        $this->actingAs($bod)->post(route('jobs.po.update', $job), ['po_number' => 'PO-1', 'po_amount' => 1, 'po_file' => UploadedFile::fake()->create('po.pdf', 10, 'application/pdf')])->assertRedirect();
        $job->refresh();
        $this->assertSame('PO-1', $job->po_number);
        $this->actingAs($bod)->get(route('jobs.po.file', $job))->assertOk();
        $this->actingAs($bod)->get(route('jobs.show', $job))->assertSee("doesn't match the job total", false);
    }

    public function test_new_job_preview_uses_the_chosen_departments_notes(): void
    {
        if (! shell_exec('command -v pdftotext')) {
            $this->markTestSkipped('pdftotext not installed.');
        }
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $payload = ['title' => 'Website', 'items' => [['item' => 'Website', 'qty' => 1, 'price' => 1000]]];

        $tech = $this->pdfText($this->actingAs($bod)->postJson(route('jobs.quotation-preview'), $payload + ['department' => 'tech'])->getContent());
        $print = $this->pdfText($this->actingAs($bod)->postJson(route('jobs.quotation-preview'), $payload + ['department' => 'print'])->getContent());

        $this->assertStringContainsString('30% upon system demo', $tech);
        $this->assertStringNotContainsString('80% deposit', $tech);
        $this->assertStringContainsString('80% deposit', $print);
    }
}
