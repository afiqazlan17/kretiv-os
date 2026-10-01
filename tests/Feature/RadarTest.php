<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\RadarItem;
use App\Models\User;
use App\Services\NotificationCenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RadarTest extends TestCase
{
    use RefreshDatabase;

    public function test_bod_writes_takes_updates_and_closes_notes(): void
    {
        Storage::fake('public');
        $mirul = User::factory()->create(['role' => User::ROLE_BOD, 'name' => 'Mirul']);
        $ila = User::factory()->create(['role' => User::ROLE_BOD, 'name' => 'Ila']);

        $this->actingAs($mirul)->postJson(route('os.radar.store'), [
            'body' => "Seafic merchandise 5 items\n50 pcs each", 'department' => 'print',
            'photo' => UploadedFile::fake()->image('sample.jpg', 1200, 900),
        ])->assertOk()->assertJsonPath('count', 1);

        $note = RadarItem::first();
        $this->assertSame('Seafic merchandise 5 items', $note->headline());
        Storage::disk('public')->assertExists($note->image_path);

        $this->actingAs($ila)->get(route('radar.index'))->assertOk()->assertSee('Seafic merchandise');
        $this->actingAs($ila)->get(route('os.home'))->assertOk()->assertSee('radar-orb', false);
        $this->actingAs($ila)->get(route('dashboard'))->assertOk()->assertSee('not taken');

        $this->actingAs($ila)->post(route('radar.take', $note));
        $this->assertSame('taken', $note->fresh()->status);
        $this->assertSame($ila->id, $note->fresh()->taken_by);

        $this->actingAs($ila)->post(route('radar.reply', $note), ['body' => 'Quote sent']);
        $this->assertSame('Quote sent', $note->replies()->first()->body);

        $this->actingAs($ila)->post(route('radar.done', $note), ['reason' => 'Went elsewhere']);
        $this->assertSame('done', $note->fresh()->status);
        $this->assertSame('dropped', $note->fresh()->outcome);

        $this->actingAs($ila)->post(route('radar.reopen', $note));
        $this->assertSame('taken', $note->fresh()->status);
    }

    public function test_staff_cannot_see_radar(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print']);
        $this->actingAs($staff)->get(route('radar.index'))->assertForbidden();
        $this->actingAs($staff)->postJson(route('os.radar.store'), ['body' => 'x'])->assertForbidden();
        $this->actingAs($staff)->get(route('os.home'))->assertOk()->assertDontSee('radar-orb', false);
    }

    public function test_create_job_from_a_note_links_it(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $customer = Customer::create(['customer_id' => 'C001', 'name' => 'Kak Yan']);
        $note = RadarItem::create(['body' => "Non woven bag 200pcs\nLogo EMS", 'department' => 'print', 'status' => 'open', 'created_by' => $bod->id]);

        $this->actingAs($bod)->get(route('jobs.create', ['radar' => $note->id]))->assertOk()->assertSee('From Radar')->assertSee('Non woven bag 200pcs');

        $this->actingAs($bod)->post(route('jobs.store'), [
            'customer_id' => $customer->id, 'departments' => ['print'], 'radar_item_id' => $note->id,
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
        $note = RadarItem::create(['body' => 'LGM medal', 'status' => 'open', 'created_by' => $bod->id]);
        $note->forceFill(['created_at' => now()->subDays(3)])->save();

        NotificationCenter::flush();
        $texts = collect(NotificationCenter::for($bod)['actions'])->pluck('text');
        $this->assertTrue($texts->contains(fn ($t) => str_contains($t, 'Radar item not taken')));
    }

    public function test_dated_items_remind_at_30_14_7_3_and_0_days_and_sort_first(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $this->actingAs($bod)->postJson(route('radar.store'), ['body' => 'SSM renewal', 'type' => 'renewal', 'due_date' => today()->addDays(20)->toDateString()])->assertOk();
        $this->actingAs($bod)->postJson(route('radar.store'), ['body' => 'Lead with no date'])->assertOk();
        $ssm = RadarItem::where('body', 'SSM renewal')->first();

        $this->assertSame(30, $ssm->reminderStage());
        $this->assertSame('SSM renewal', RadarItem::byUrgency()->first()->body);
        $this->actingAs($bod)->get(route('radar.index'))->assertSee('Due in 20 days');

        NotificationCenter::flush();
        $this->assertTrue(collect(NotificationCenter::for($bod)['updates'])->contains(fn ($u) => $u['key'] === "radar:{$ssm->id}:30"));

        $this->travelTo(today()->addDays(17)); // 3 days left
        $this->assertSame(3, $ssm->fresh()->reminderStage());
        $this->assertTrue(RadarItem::needsAttention()->whereKey($ssm->id)->exists() || $ssm->fresh()->status === 'open');

        $this->travelTo(today()->addDays(5)); // overdue
        NotificationCenter::flush();
        $this->assertTrue(collect(NotificationCenter::for($bod)['actions'])->contains(fn ($a) => str_contains($a['text'], 'overdue by 2 days')));

        $this->actingAs($bod)->patch(route('radar.update', $ssm), ['type' => 'renewal', 'due_date' => null]);
        $this->assertNull($ssm->fresh()->due_date);
    }

    public function test_renewals_do_not_offer_create_job(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        RadarItem::create(['body' => 'Renew domain glambooth.my', 'type' => 'renewal', 'status' => 'open', 'created_by' => $bod->id]);

        $this->actingAs($bod)->get(route('radar.index'))->assertSee('Renew domain')->assertDontSee('Create job');
    }
}
