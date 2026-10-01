<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\RizqNote;
use App\Models\User;
use App\Services\NotificationCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RizqTest extends TestCase
{
    use RefreshDatabase;

    public function test_bod_writes_takes_updates_and_closes_notes(): void
    {
        Storage::fake('public');
        $mirul = User::factory()->create(['role' => User::ROLE_BOD, 'name' => 'Mirul']);
        $ila = User::factory()->create(['role' => User::ROLE_BOD, 'name' => 'Ila']);

        $this->actingAs($mirul)->postJson(route('os.rizq.store'), [
            'body' => "Seafic merchandise 5 items\n50 pcs each", 'department' => 'print',
            'photo' => UploadedFile::fake()->image('sample.jpg', 1200, 900),
        ])->assertOk()->assertJsonPath('open', 1);

        $note = RizqNote::first();
        $this->assertSame('Seafic merchandise 5 items', $note->headline());
        Storage::disk('public')->assertExists($note->image_path);

        $this->actingAs($ila)->get(route('rizq.index'))->assertOk()->assertSee('Seafic merchandise');
        $this->actingAs($ila)->get(route('os.home'))->assertOk()->assertSee('rizq-orb', false);
        $this->actingAs($ila)->get(route('dashboard'))->assertOk()->assertSee('not taken');

        $this->actingAs($ila)->post(route('rizq.take', $note));
        $this->assertSame('taken', $note->fresh()->status);
        $this->assertSame($ila->id, $note->fresh()->taken_by);

        $this->actingAs($ila)->post(route('rizq.reply', $note), ['body' => 'Quote sent']);
        $this->assertSame('Quote sent', $note->replies()->first()->body);

        $this->actingAs($ila)->post(route('rizq.done', $note), ['reason' => 'Went elsewhere']);
        $this->assertSame('done', $note->fresh()->status);
        $this->assertSame('dropped', $note->fresh()->outcome);

        $this->actingAs($ila)->post(route('rizq.reopen', $note));
        $this->assertSame('taken', $note->fresh()->status);
    }

    public function test_staff_cannot_see_rizq(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);
        $this->actingAs($staff)->get(route('rizq.index'))->assertForbidden();
        $this->actingAs($staff)->postJson(route('os.rizq.store'), ['body' => 'x'])->assertForbidden();
        $this->actingAs($staff)->get(route('os.home'))->assertOk()->assertDontSee('rizq-orb', false);
    }

    public function test_create_job_from_a_note_links_it(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $customer = Customer::create(['customer_id' => 'C001', 'name' => 'Kak Yan']);
        $note = RizqNote::create(['body' => "Non woven bag 200pcs\nLogo EMS", 'department' => 'print', 'status' => 'open', 'created_by' => $bod->id]);

        $this->actingAs($bod)->get(route('jobs.create', ['rizq' => $note->id]))->assertOk()->assertSee('From Rizq')->assertSee('Non woven bag 200pcs');

        $this->actingAs($bod)->post(route('jobs.store'), [
            'customer_id' => $customer->id, 'departments' => ['print'], 'rizq_note_id' => $note->id,
            'per_dept' => ['print' => ['job_type' => 'Non woven bag 200pcs', 'job_type_category' => 'client_project', 'bank' => 'mbb', 'line_items' => [['item' => 'Bag', 'qty' => 200, 'price' => 3.19]]]],
        ])->assertRedirect();

        $note->refresh();
        $this->assertSame('done', $note->status);
        $this->assertSame('job', $note->outcome);
        $this->assertNotNull($note->job_id);
    }

    public function test_old_untaken_notes_show_in_notifications(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $note = RizqNote::create(['body' => 'LGM medal', 'status' => 'open', 'created_by' => $bod->id]);
        $note->forceFill(['created_at' => now()->subDays(3)])->save();

        NotificationCenter::flush();
        $texts = collect(NotificationCenter::for($bod)['actions'])->pluck('text');
        $this->assertTrue($texts->contains(fn ($t) => str_contains($t, 'Rizq note not taken')));
    }
}
