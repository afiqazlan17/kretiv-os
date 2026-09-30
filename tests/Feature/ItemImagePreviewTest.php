<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Support\ItemImages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ItemImagePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_prints_the_picture(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $c = Customer::create(['customer_id' => 'C001', 'name' => 'Acme']);
        $job = Job::create(['job_id' => 'KP-2026-001', 'customer_id' => $c->id, 'department' => 'print', 'job_type' => 'Banner', 'job_type_category' => 'client_project', 'status' => Job::STATUS_POTENTIAL, 'estimation_value' => 1000]);
        $name = ItemImages::store(UploadedFile::fake()->image('a.png', 800, 600), $job);

        $pdf = $this->actingAs($bod)->postJson(route('jobs.documents.preview', [$job, 'quotation']), [
            'title' => 'Banner', 'items' => [['item' => 'Flag', 'desc' => '', 'qty' => 1, 'price' => 10, 'image' => $name]],
        ])->assertOk()->getContent();

        $this->assertStringContainsString('/Subtype /Image', $pdf);
    }
}
