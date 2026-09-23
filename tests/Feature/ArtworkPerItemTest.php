<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ArtworkPerItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_artwork_is_grouped_per_line_item_and_design(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $customer = Customer::create(['customer_id' => 'C001', 'name' => 'Acme']);
        $job = Job::create([
            'job_id' => 'KP-2026-001', 'customer_id' => $customer->id, 'department' => 'print', 'job_type' => 'Banner',
            'job_type_category' => 'client_project', 'status' => Job::STATUS_IN_PROGRESS, 'estimation_value' => 100,
            'line_items' => [['item' => 'Banner', 'qty' => 1, 'price' => 50], ['item' => 'Sticker', 'qty' => 1, 'price' => 50]],
        ]);

        $this->actingAs($bod)->post(route('jobs.attachments.store', $job), [
            'kind' => 'artwork', 'line_item_id' => '1', 'design' => 2, 'file' => UploadedFile::fake()->image('d2.png'),
        ])->assertSessionHasNoErrors();

        $att = $job->fresh()->attachments[0];
        $this->assertSame('1', $att['line_item_id']);
        $this->assertSame(2, $att['design']);

        $this->actingAs($bod)->get(route('jobs.show', $job))
            ->assertOk()->assertSee('📎 Sticker')->assertSee('+ Add another design')->assertSee('d2.png');
    }

    public function test_departments_match_the_official_site_four_divisions(): void
    {
        $this->assertSame(['print', 'brand', 'tech', 'event'], array_keys(config('kretivco.departments')));
        $this->assertArrayNotHasKey('machine', config('kretivco.departments'));
        $this->assertArrayNotHasKey('wisb', config('kretivco.departments'));
        $this->assertSame('KretivBrand', config('kretivco.departments.brand.label'));
    }
}
