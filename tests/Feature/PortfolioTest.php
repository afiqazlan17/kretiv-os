<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Job;
use App\Models\JobPhoto;
use App\Models\User;
use App\Support\DocumentData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortfolioTest extends TestCase
{
    use RefreshDatabase;

    private function job(string $code, string $dept = 'print'): Job
    {
        $customer = Customer::firstOrCreate(['customer_id' => 'C001'], ['name' => 'Acme', 'company' => 'Acme Sdn Bhd']);

        return Job::create(['job_id' => $code, 'customer_id' => $customer->id, 'department' => $dept, 'job_type' => 'Banner '.$code, 'job_type_category' => 'client_project', 'status' => Job::STATUS_IN_PROGRESS, 'estimation_value' => 100]);
    }

    public function test_photos_upload_to_the_job_and_show_on_the_portfolio(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job('KP-2026-001');

        $this->actingAs($bod)->post(route('jobs.photos.store', $job), [
            'photos' => [UploadedFile::fake()->image('a.jpg', 3000, 2000), UploadedFile::fake()->image('b.png', 800, 800)],
            'marketing_ok' => 0,
        ])->assertRedirect();

        $this->assertSame(2, $job->photos()->count());
        $photo = $job->photos()->first();
        $this->assertFalse($photo->marketing_ok);
        [$w] = getimagesize(Storage::disk('public')->path($photo->thumb_path));
        $this->assertLessThanOrEqual(600, $w);

        $this->actingAs($bod)->get(route('jobs.show', $job))->assertOk()->assertSee('Finished Product Photos');
        $this->actingAs($bod)->get(route('portfolio.index'))->assertOk()->assertSee('Banner KP-2026-001');
        $this->actingAs($bod)->get(route('portfolio.index', ['marketing' => 1]))->assertOk()->assertDontSee('Banner KP-2026-001');
        $this->actingAs($bod)->get(route('jobs.photos.show', [$job, $photo, 'thumb' => 1]))->assertOk();
        $this->actingAs($bod)->get(route('portfolio.download'))->assertOk();

        $this->actingAs($bod)->patch(route('jobs.photos.update', [$job, $photo]), ['marketing_ok' => 1]);
        $this->assertTrue($photo->fresh()->marketing_ok);

        $this->actingAs($bod)->delete(route('jobs.photos.destroy', [$job, $photo]));
        Storage::disk('public')->assertMissing($photo->path);
        $this->assertSame(1, JobPhoto::count());
    }

    public function test_portfolio_only_shows_departments_the_user_can_see(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $print = $this->job('KP-2026-001', 'print');
        $tech = $this->job('KT-2026-001', 'tech');
        foreach ([$print, $tech] as $job) {
            $this->actingAs($bod)->post(route('jobs.photos.store', $job), ['photos' => [UploadedFile::fake()->image('a.jpg', 100, 100)]]);
        }

        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);
        if ($staff->seesAllDepartments()) {
            $this->markTestSkipped('Staff see every department in this setup.');
        }
        $this->actingAs($staff)->get(route('portfolio.index'))->assertSee('Banner KP-2026-001')->assertDontSee('Banner KT-2026-001');
        $techPhoto = $tech->photos()->first();
        $this->actingAs($staff)->get(route('jobs.photos.show', [$tech, $techPhoto]))->assertForbidden();
    }

    public function test_documents_print_in_bahasa_melayu_and_remember_it_for_the_customer(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job('KP-2026-001');

        $this->actingAs($bod)->postJson(route('jobs.documents.save', [$job, 'quotation']), ['title' => 'Banner', 'lang' => 'ms', 'use_default_notes' => true])->assertOk();
        $this->assertSame('ms', $job->customer->fresh()->doc_language);

        $doc = DocumentData::build($job->fresh(), 'quotation', ['use_default_notes' => true], 'QTN1', 'Afiq');
        $this->assertSame('SEBUT HARGA', $doc['doc_title']);
        $this->assertSame('Sah sehingga', $doc['header_extra'][0]);
        $this->assertStringStartsWith('Harga adalah berdasarkan', $doc['notes'][0]);
        $this->actingAs($bod)->getJson(route('jobs.documents.draft', [$job, 'quotation']))->assertJsonPath('defaults.lang', 'ms');
    }
}
