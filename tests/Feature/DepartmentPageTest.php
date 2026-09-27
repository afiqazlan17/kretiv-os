<?php

namespace Tests\Feature;

use App\Models\Job;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentPageTest extends TestCase
{
    use RefreshDatabase;

    private function job(string $id, string $dept, string $status, float $value = 0): void
    {
        Job::create(['job_id' => $id, 'department' => $dept, 'job_type' => 'X', 'job_type_category' => 'client_project', 'status' => $status, 'estimation_value' => $value]);
    }

    public function test_it_shows_each_department_with_counts_and_pipeline_value(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        $this->job('KP-2026-001', 'print', Job::STATUS_IN_PROGRESS, 100);
        $this->job('KP-2026-002', 'print', Job::STATUS_POTENTIAL, 50);
        $this->job('KP-2026-003', 'print', Job::STATUS_COMPLETED, 900);

        $response = $this->actingAs($bod)->get(route('departments.index'))->assertOk();

        $response->assertSee('Large Format: banner, bunting, backdrop');
        $response->assertSee('Undangan.my: Digital Wedding Invitation');
        $print = $response->viewData('departments')['print'];
        $this->assertSame(2, $print['active']);
        $this->assertSame(1, $print['completed']);
        $this->assertEquals(150, $print['pipeline']);
    }

    public function test_non_bod_users_only_see_their_departments(): void
    {
        $head = User::factory()->create(['role' => User::ROLE_DEPT_HEAD, 'department' => 'tech']);

        $keys = $this->actingAs($head)->get(route('departments.index'))->viewData('departments')->keys()->all();

        $this->assertSame(['tech'], $keys);
    }
}
