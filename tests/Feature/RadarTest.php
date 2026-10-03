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
            'body' => "Seafic merchandise 5 items\n50 pcs each", 'type' => 'enquiry',
            'photo' => UploadedFile::fake()->image('sample.jpg', 1200, 900),
        ])->assertOk()->assertJsonPath('count', 1); // one blip per open item

        $note = RadarItem::first();
        $this->assertSame('Seafic merchandise 5 items', $note->headline());
        Storage::disk('public')->assertExists($note->image_path);

        $this->actingAs($ila)->get(route('radar.index'))->assertOk()->assertSee('Seafic merchandise')->assertSee('Convert to Job')->assertDontSee('Take it');
        $this->actingAs($ila)->get(route('os.home'))->assertOk()->assertSee('radar-orb', false);
        // Radar lives only in KretivOS: no sidebar entry or dashboard card in Jobs.
        $this->actingAs($ila)->get(route('dashboard'))->assertOk()->assertDontSee(route('radar.index'));

        $this->actingAs($ila)->post(route('radar.reply', $note), ['body' => 'Quote sent']);
        $this->assertSame('Quote sent', $note->replies()->first()->body);

        $this->actingAs($ila)->post(route('radar.done', $note), ['reason' => 'Went elsewhere']);
        $this->assertSame('done', $note->fresh()->status);
        $this->assertSame('dropped', $note->fresh()->outcome);

        $this->actingAs($ila)->post(route('radar.reopen', $note));
        $this->assertSame('open', $note->fresh()->status);

        // PDFs attach as they are.
        $this->actingAs($mirul)->postJson(route('radar.store'), ['body' => 'Meeting brief', 'type' => 'meeting', 'photo' => UploadedFile::fake()->create('brief.pdf', 20, 'application/pdf')])->assertOk();
        $this->assertStringEndsWith('.pdf', RadarItem::where('body', 'Meeting brief')->first()->image_path);
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

    public function test_orb_has_a_blip_per_open_item_turning_red_after_3_days_without_action(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $this->actingAs($bod)->postJson(route('radar.store'), ['body' => 'Renew SSM', 'type' => 'todo'])->assertOk();
        $this->actingAs($bod)->postJson(route('radar.store'), ['body' => 'Call Kastam'])->assertOk();
        $ssm = RadarItem::where('body', 'Renew SSM')->first();

        // Nothing in the bell; two amber blips.
        NotificationCenter::flush();
        $all = collect(NotificationCenter::for($bod))->only(['actions', 'updates'])->flatten(1);
        $this->assertFalse($all->contains(fn ($n) => str_contains($n['text'] ?? '', 'SSM')));
        $this->assertSame(2, RadarItem::attentionFor($bod)->count());
        $this->assertFalse(RadarItem::attentionFor($bod)->contains->isRed());
        $this->actingAs($bod)->get(route('os.home'))->assertDontSee('radar-orb radar-orb--urgent', false);

        // Three days with no action: red. An update resets it.
        $this->travelTo(now()->addDays(4));
        $this->assertTrue($ssm->fresh()->isRed());
        $this->actingAs($bod)->get(route('os.home'))->assertSee('radar-orb radar-orb--urgent', false);
        $this->actingAs($bod)->post(route('radar.reply', $ssm), ['body' => 'Paid the fee']);
        $this->assertFalse($ssm->fresh()->isRed());

        // Closed items leave the radar.
        $this->actingAs($bod)->post(route('radar.done', $ssm));
        $this->assertSame(1, RadarItem::attentionFor($bod)->count());
    }

    public function test_only_job_enquiries_offer_convert_to_job(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        RadarItem::create(['body' => 'Company profile Kretivco', 'type' => 'todo', 'status' => 'open', 'created_by' => $bod->id]);

        $this->actingAs($bod)->get(route('radar.index'))->assertSee('Company profile')->assertDontSee('Convert to Job')
            ->assertSee("Under Board Of Director's Radar", false);
    }
}
