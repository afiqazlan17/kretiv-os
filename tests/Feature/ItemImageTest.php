<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Job;
use App\Models\User;
use App\Support\DocumentData;
use App\Support\ItemImages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ItemImageTest extends TestCase
{
    use RefreshDatabase;

    private function job(string $code = 'KP-2026-001'): Job
    {
        $customer = Customer::firstOrCreate(['customer_id' => 'C001'], ['name' => 'Acme']);

        return Job::create([
            'job_id' => $code, 'customer_id' => $customer->id, 'department' => 'print',
            'job_type' => 'Banner', 'job_type_category' => 'client_project', 'status' => Job::STATUS_POTENTIAL,
            'estimation_value' => 1000,
        ]);
    }

    public function test_upload_resizes_to_jpeg_and_saving_keeps_it_on_the_item(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        $res = $this->actingAs($bod)->post(route('jobs.item-images.store', $job), ['file' => UploadedFile::fake()->image('mockup.png', 3000, 1500)], ['Accept' => 'application/json']);
        $res->assertOk();
        $name = $res->json('image');
        $this->assertMatchesRegularExpression(ItemImages::NAME, $name);
        [$w, $h] = getimagesize(Storage::disk('public')->path("KP-2026-001/item-images/{$name}"));
        $this->assertSame([1600, 800], [$w, $h]);

        $this->actingAs($bod)->get(route('jobs.item-images.show', [$job, $name]))->assertOk();

        $this->actingAs($bod)->postJson(route('jobs.documents.save', [$job, 'quotation']), [
            'title' => 'Banner', 'items' => [['item' => 'Wind flag', 'desc' => '', 'qty' => 2, 'price' => 250, 'image' => $name]],
        ])->assertOk();
        $this->assertSame($name, $job->fresh()->line_items[0]['image']);

        $doc = DocumentData::build($job->fresh(), 'quotation', [], 'QTN1', 'Afiq');
        $this->assertStringEndsWith($name, $doc['items'][0]['image_file']);
        $this->assertStringStartsWith('%PDF', DocumentData::pdf($doc));
    }

    public function test_image_names_cannot_point_outside_the_job_folder(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();

        $this->actingAs($bod)->postJson(route('jobs.documents.preview', [$job, 'quotation']), [
            'title' => 'Banner', 'items' => [['item' => 'X', 'qty' => 1, 'price' => 1, 'image' => '../../.env']],
        ])->assertStatus(422);

        // Another job's picture name resolves inside this job's folder only, so it prints nothing.
        $other = $this->job('KP-2026-002');
        $name = ItemImages::store(UploadedFile::fake()->image('a.jpg', 100, 100), $other);
        $items = ItemImages::resolve([['item' => 'X', 'image' => $name]], $job);
        $this->assertArrayNotHasKey('image_file', $items[0]);

        $this->actingAs($bod)->get(route('jobs.item-images.show', [$job, $name]))->assertNotFound();
    }

    public function test_upload_refuses_non_images(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);

        $this->actingAs($bod)->post(route('jobs.item-images.store', $this->job()), ['file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_duplicate_job_copies_the_pictures(): void
    {
        Storage::fake('public');
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $job = $this->job();
        $name = ItemImages::store(UploadedFile::fake()->image('a.jpg', 100, 100), $job);
        $job->update(['line_items' => [['item' => 'Flag', 'desc' => '', 'qty' => 1, 'price' => 10, 'image' => $name]]]);

        $this->actingAs($bod)->post(route('jobs.duplicate', $job));

        $copy = Job::where('id', '!=', $job->id)->latest('id')->first();
        Storage::disk('public')->assertExists("{$copy->job_id}/item-images/{$name}");
    }
}
