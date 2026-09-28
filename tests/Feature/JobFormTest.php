<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\JobForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_department_gets_its_own_form_saved_on_the_job_and_printed(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $event = Job::create(['job_id' => 'KE-2026-010', 'department' => 'event', 'job_type' => 'Annual dinner', 'job_type_category' => 'client_project', 'status' => 'confirmed', 'pic' => $bod->name]);
        $tech = Job::create(['job_id' => 'KT-2026-010', 'department' => 'tech', 'job_type' => 'Website', 'job_type_category' => 'client_project', 'status' => 'in_progress', 'pic' => $bod->name]);

        $this->actingAs($bod)->get(route('jobs.show', $event))->assertSee('Event Run Sheet')->assertDontSee('Creative Brief');
        $this->actingAs($bod)->get(route('jobs.show', $tech))->assertSee('UAT / Go-Live Sign-off');

        $this->actingAs($bod)->put(route('jobs.forms.update', [$event, 'runsheet']), ['data' => [
            'event_name' => 'Annual Dinner 2026', 'event_date' => '2026-12-12',
            'programme' => [['7:00pm', 'Guests arrive', 'Crew', ''], ['', '', '', ''], ['8:00pm', 'Dinner', 'Caterer', 'Halal']],
            'hacker' => 'ignored',
        ]])->assertRedirect();
        $form = JobForm::first();
        $this->assertSame('Annual Dinner 2026', $form->data['event_name']);
        $this->assertCount(2, $form->data['programme']);   // empty row dropped
        $this->assertArrayNotHasKey('hacker', $form->data);

        $this->actingAs($bod)->put(route('jobs.forms.update', [$tech, 'uat']), ['data' => ['checks' => ['SSL (https) active, no browser warnings', 'made up'], 'result' => 'Passed']]);
        $this->assertSame(['SSL (https) active, no browser warnings'], JobForm::where('form_key', 'uat')->first()->data['checks']);

        $this->actingAs($bod)->get(route('jobs.forms.pdf', [$event, 'runsheet']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($bod)->get(route('jobs.forms.edit', [$event, 'nope']))->assertNotFound();
    }
}
