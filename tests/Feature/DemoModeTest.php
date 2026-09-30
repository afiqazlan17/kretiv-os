<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\Customer;
use App\Models\Job;
use App\Models\JobDocument;
use App\Models\PayrollRun;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\PaymentHistory;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    private function demoOn(): void
    {
        config(['demo.enabled' => true, 'app.url' => 'https://demo.kretiv.co']);
        (new AppServiceProvider(app()))->boot();
    }

    public function test_the_demo_company_is_complete_and_consistent(): void
    {
        Storage::fake('public');
        $this->seed(DemoSeeder::class);

        $this->assertSame(8, User::count());
        $this->assertTrue(User::where('email', 'boss@demo.kretiv.co')->exists());
        $this->assertSame(14, Job::count());

        $maju = Customer::where('company', 'Syarikat Maju Jaya')->first();
        $this->assertTrue(PaymentHistory::for($maju->id)['slow']);

        $expired = Job::where('job_id', 'KB-2026-002')->first();
        $this->assertTrue(JobDocument::where('job_id', $expired->id)->max('generated_at') <= now()->subDays(14));

        $project = JobDocument::whereIn('job_id', Job::where('project_id', 'PRJ-2026-001')->pluck('id'))->pluck('doc_number')->unique();
        $this->assertCount(1, $project);
        $this->assertMatchesRegularExpression('/^QTN\d{8}-PB$/', $project->first());

        $this->assertSame(1, Approval::where('status', 'sent')->count());
        $this->assertSame(1, Approval::where('status', 'approved')->count());
        $this->assertSame('finalized', PayrollRun::where('period', now()->startOfMonth()->subMonthNoOverflow()->format('Y-m'))->value('status'));
        foreach (JobDocument::all() as $doc) {
            Storage::disk('public')->assertExists($doc->storage_path);
        }
    }

    public function test_reset_refuses_anywhere_but_the_demo_install(): void
    {
        $this->artisan('demo:reset')->assertFailed();

        config(['demo.enabled' => true, 'app.url' => 'https://jobs.kretiv.co']);
        $this->artisan('demo:reset')->assertFailed();

        config(['app.url' => 'https://demo.kretiv.co']);
        User::factory()->create(['email' => 'afiq@kretiv.co']);
        $this->artisan('demo:reset')->assertFailed();
        $this->assertSame(1, User::count());
    }

    public function test_demo_mode_swaps_the_company_and_locks_the_shared_accounts(): void
    {
        $this->demoOn();
        $this->assertSame('Mirul Enterprise', config('kretivco.brand.name'));
        $this->assertNull(config('kretivco.brand.stamp'));
        $this->assertSame([], config('kretivco.bank_qr'));
        $this->assertSame('log', config('mail.default'));

        $this->get('/login')->assertSee('Try the KretivOS demo')->assertSee('boss@demo.kretiv.co', false);

        $boss = User::factory()->create(['email' => 'boss@demo.kretiv.co', 'role' => User::ROLE_BOD]);
        $this->actingAs($boss)->put(route('password.change.update'), ['password' => 'x'])->assertSessionHasErrors('demo');
        $this->actingAs($boss)->put(route('hr.staff.update', $boss), ['name' => 'Hacked'])->assertSessionHasErrors('demo');
        $this->assertSame('boss@demo.kretiv.co', $boss->fresh()->email);

        $other = User::factory()->create(['email' => 'someone@demo.kretiv.co']);
        $this->actingAs($boss)->put(route('hr.staff.update', $other), [])->assertSessionDoesntHaveErrors('demo');
    }
}
