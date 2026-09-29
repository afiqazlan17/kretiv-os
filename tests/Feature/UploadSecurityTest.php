<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function job(): Job
    {
        $customer = Customer::create(['customer_id' => 'C001', 'name' => 'Acme']);

        return Job::create(['job_id' => 'KP-2026-001', 'customer_id' => $customer->id, 'department' => 'print', 'job_type' => 'Banner', 'job_type_category' => 'client_project', 'status' => Job::STATUS_CONFIRMED]);
    }

    public function test_scripts_and_web_pages_cannot_be_uploaded_but_design_files_can(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        foreach (['shell.php', 'photo.php.jpg', 'page.html', '.htaccess', 'run.PHTML'] as $name) {
            $this->actingAs($bod)->post(route('jobs.attachments.store', $job), ['file' => UploadedFile::fake()->create($name, 1), 'kind' => 'other'])
                ->assertSessionHasErrors('file');
        }
        $this->actingAs($bod)->post(route('jobs.notes.store', $job), ['note' => 'hi', 'attachments' => [UploadedFile::fake()->create('x.php', 1)]])
            ->assertSessionHasErrors('attachments.0');

        foreach (['logo.ai', 'poster.psd', 'files.zip', 'quote.pdf'] as $name) {
            $this->actingAs($bod)->post(route('jobs.attachments.store', $job), ['file' => UploadedFile::fake()->create($name, 1), 'kind' => 'other'])
                ->assertSessionHasNoErrors();
        }
        $this->assertCount(4, $job->refresh()->attachments);
    }

    public function test_an_svg_is_downloaded_not_opened_and_a_pdf_still_opens(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();
        $this->actingAs($bod)->post(route('jobs.attachments.store', $job), ['file' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), 'kind' => 'other']);
        $this->actingAs($bod)->post(route('jobs.attachments.store', $job), ['file' => UploadedFile::fake()->create('quote.pdf', 1, 'application/pdf'), 'kind' => 'other']);
        [$svg, $pdf] = $job->refresh()->attachments;

        $res = $this->actingAs($bod)->get(route('jobs.attachments.show', [$job, $svg['id']]));
        $this->assertStringStartsWith('attachment', $res->headers->get('Content-Disposition'));
        $res->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringStartsWith('inline', $this->actingAs($bod)->get(route('jobs.attachments.show', [$job, $pdf['id']]))->headers->get('Content-Disposition'));
    }

    public function test_pages_carry_security_headers_and_password_resets_are_rate_limited(): void
    {
        $page = $this->get('https://localhost/login');
        $page->assertHeader('Strict-Transport-Security', 'max-age=31536000')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('geolocation=()', $page->headers->get('Permissions-Policy'));
        $this->assertFalse($this->get('http://localhost/login')->headers->has('Strict-Transport-Security'));

        foreach (range(1, 5) as $i) {
            $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertStatus(302);
        }
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])->assertStatus(429);
    }
}
