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

        $this->actingAs($mirul)->postJson(route('radar.store'), [
            'body' => "Seafic merchandise 5 items\n50 pcs each", 'department' => 'print',
            'photo' => UploadedFile::fake()->image('sample.jpg', 1200, 900),
        ])->assertOk()->assertJsonPath('count', 1);

        $note = RadarItem::first();
        $this->assertSame('Seafic merchandise 5 items', $note->headline());
        Storage::disk('public')->assertExists($note->image_path);

        $this->actingAs($ila)->get(route('radar.index'))->assertOk()->assertSee('Seafic merchandise');
        $this->actingAs($ila)->get(route('os.home'))->assertOk()->assertSee('radar-orb', false);
        // Radar lives only in KretivOS: no sidebar entry or dashboard card in Jobs.
        $this->actingAs($ila)->get(route('dashboard'))->assertOk()->assertDontSee(route('radar.index'));

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
        $this->actingAs($staff)->postJson(route('radar.store'), ['body' => 'x'])->assertForbidden();
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

    public function test_radar_signals_only_on_the_orb_and_reminders_clear_once_seen(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $this->actingAs($bod)->postJson(route('radar.store'), ['body' => 'SSM renewal', 'type' => 'renewal', 'due_date' => today()->addDays(20)->toDateString()])->assertOk();
        $this->actingAs($bod)->postJson(route('radar.store'), ['body' => 'Lead with no date'])->assertOk();
        $ssm = RadarItem::where('body', 'SSM renewal')->first();
        $ssm->update(['status' => 'taken', 'taken_by' => $bod->id]);

        // Nothing in the bell.
        NotificationCenter::flush();
        $all = collect(NotificationCenter::for($bod))->only(['actions', 'updates'])->flatten(1);
        $this->assertFalse($all->contains(fn ($n) => str_contains($n['text'] ?? '', 'SSM')));

        // The orb: the untaken lead, plus the 30-day reminder until Radar is opened.
        $this->assertSame(30, $ssm->reminderStage());
        $this->assertSame(2, RadarItem::attentionFor($bod)->count());
        $this->actingAs($bod)->get(route('radar.index', ['tab' => 'taken']))->assertOk()->assertSee('Due in 20 days');
        $this->assertSame(1, RadarItem::attentionFor($bod)->count());
        $this->assertSame('SSM renewal', RadarItem::byUrgency()->first()->body);

        // 14 days before: lights up again.
        $this->travelTo(today()->addDays(6));
        $this->assertSame(14, $ssm->fresh()->reminderStage());
        $this->assertSame(2, RadarItem::attentionFor($bod)->count());

        // Overdue: stays lit even after opening Radar.
        $this->travelTo(today()->addDays(16));
        $this->actingAs($bod)->get(route('radar.index'));
        $this->assertTrue(RadarItem::attentionFor($bod)->contains('id', $ssm->id));
        $this->actingAs($bod)->get(route('os.home'))->assertSee('radar-orb--urgent', false);

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
