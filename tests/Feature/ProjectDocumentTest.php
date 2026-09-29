<?php

namespace Tests\Feature;

use App\Http\Controllers\StatementController;
use App\Models\Customer;
use App\Models\Job;
use App\Models\JobDocument;
use App\Models\LedgerEntry;
use App\Models\User;
use App\Support\DocumentData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectDocumentTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->customer = Customer::create(['customer_id' => 'C001', 'name' => 'Acme']);
    }

    private function job(string $code, string $department, float $price, string $status = Job::STATUS_POTENTIAL, ?string $project = 'PRJ-2026-001'): Job
    {
        return Job::create([
            'job_id' => $code, 'customer_id' => $this->customer->id, 'department' => $department, 'project_id' => $project,
            'job_type' => $department === 'print' ? 'Banner' : 'Logo', 'job_type_category' => 'client_project', 'status' => $status,
            'bank' => 'mbb', 'line_items' => [['item' => $department === 'print' ? 'Banner' : 'Logo', 'qty' => 1, 'price' => $price]],
        ]);
    }

    private function url(string $action, Job $job, string $type): string
    {
        return route('jobs.documents.'.$action, [$job, $type]).'?scope=project';
    }

    public function test_a_project_quotation_is_one_document_for_every_job_with_merged_notes(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $print = $this->job('KP-2026-001', 'print', 400);
        $brand = $this->job('KB-2026-001', 'brand', 200);
        $this->job('KT-2026-001', 'tech', 900, Job::STATUS_NEW);

        $draft = $this->actingAs($bod)->getJson($this->url('draft', $print, 'quotation'))->assertOk();
        $draft->assertJsonPath('scope', 'project')->assertJsonPath('project_total', 600);
        $this->assertMatchesRegularExpression('/^QTN\d{8}-PB$/', $draft->json('doc_number'));
        $this->assertSame(['KT-2026-001 (not taken in yet)'], $draft->json('project.left_out'));

        $notes = $draft->json('defaults.notes');
        $this->assertContains('KretivPrint:', $notes);
        $this->assertContains('KretivBrand:', $notes);
        $this->assertSame(1, collect($notes)->filter(fn ($n) => str_contains($n, 'valid for 14 days'))->count());

        $this->actingAs($bod)->postJson($this->url('generate', $print, 'quotation'), ['title' => 'Banner + Logo'])->assertOk();
        $numbers = JobDocument::where('doc_type', 'quotation')->pluck('doc_number', 'job_id');
        $this->assertCount(2, $numbers);
        $this->assertSame($draft->json('doc_number'), $numbers[$brand->id]);
        $this->assertSame($numbers[$print->id], $numbers[$brand->id]);

        // Regenerating from the other job keeps the same number.
        $this->actingAs($bod)->getJson($this->url('draft', $brand, 'quotation'))->assertJsonPath('doc_number', $numbers[$print->id]);
    }

    public function test_a_project_payment_is_split_across_the_jobs_and_voided_together(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $print = $this->job('KP-2026-001', 'print', 400);
        $brand = $this->job('KB-2026-001', 'brand', 200);

        $this->actingAs($bod)->postJson($this->url('generate', $brand, 'receipt'), ['title' => 'Banner + Logo', 'amount_paid' => 300])->assertOk();

        $entries = LedgerEntry::where('type', 'receipt')->get()->keyBy('job_id');
        $this->assertEqualsWithDelta(200, (float) $entries['KP-2026-001']->amount, 0.001);
        $this->assertEqualsWithDelta(100, (float) $entries['KB-2026-001']->amount, 0.001);
        $this->assertSame($entries['KP-2026-001']->doc_number, $entries['KB-2026-001']->doc_number);
        $this->assertSame(Job::STATUS_CONFIRMED, $print->refresh()->status);
        $this->assertSame(Job::STATUS_CONFIRMED, $brand->refresh()->status);

        $this->actingAs($bod)->post(route('jobs.payments.void', [$print, $entries['KP-2026-001']]))->assertRedirect();
        $this->assertSame(0, LedgerEntry::where('type', 'receipt')->where('reversed', false)->count());
    }

    public function test_a_project_invoice_posts_each_job_and_shows_as_one_line_on_the_statement(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $print = $this->job('KP-2026-001', 'print', 400, Job::STATUS_CONFIRMED);
        $this->job('KB-2026-001', 'brand', 200, Job::STATUS_CONFIRMED);

        $this->actingAs($bod)->postJson($this->url('generate', $print, 'invoice'), ['title' => 'Banner + Logo'])->assertOk();

        $invoices = LedgerEntry::where('type', 'invoice')->get();
        $this->assertEqualsCanonicalizing([400.0, 200.0], $invoices->map(fn ($e) => (float) $e->amount)->all());
        $this->assertCount(1, $invoices->pluck('doc_number')->unique());

        $rows = StatementController::build($this->customer)['rows'];
        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(600, $rows[0]['charge'], 0.001);
    }

    public function test_an_invoice_leaves_out_jobs_not_yet_confirmed_and_a_lone_job_stays_single(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $print = $this->job('KP-2026-001', 'print', 400, Job::STATUS_CONFIRMED);
        $this->job('KB-2026-001', 'brand', 200);

        $this->assertTrue(DocumentData::projectJobs($print, 'invoice')->isEmpty());
        $this->actingAs($bod)->getJson($this->url('draft', $print, 'invoice'))->assertJsonPath('scope', 'job');

        $solo = $this->job('KP-2026-002', 'print', 100, Job::STATUS_CONFIRMED, null);
        $this->actingAs($bod)->getJson($this->url('draft', $solo, 'quotation'))->assertJsonPath('scope', 'job')->assertJsonPath('project', null);
    }
}
