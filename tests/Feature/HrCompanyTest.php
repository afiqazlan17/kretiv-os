<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_everyone_sees_the_org_chart_with_board_tiers_and_units(): void
    {
        $ceo = User::factory()->create(['role' => User::ROLE_BOD, 'name' => 'Amirul Hafiz', 'title' => 'Chief Executive Officer (CEO)']);
        $coo = User::factory()->create(['role' => User::ROLE_BOD, 'name' => 'Afiq Azlan', 'title' => 'Chief Operation Officer (COO)']);
        $coo->employee()->create(['reports_to_user_id' => $ceo->id]);
        $designer = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'print', 'name' => 'Aina Sofea']);
        $admin = User::factory()->create(['role' => User::ROLE_FINANCE, 'department' => 'admin', 'name' => 'Siti Kewangan']);
        Department::ordered();
        Department::where('key', 'brand')->update(['head_user_id' => $coo->id, 'head_interim' => true]);

        $page = $this->actingAs($designer)->get(route('hr.org-chart'))->assertOk()
            ->assertSee('Amirul Hafiz')->assertSee('Finance &amp; Admin', false)->assertSee('Siti Kewangan')->assertSee('Interim head');
        $this->assertTrue($page->viewData('top')->contains($ceo));
        $this->assertTrue($page->viewData('second')->contains($coo));
        $this->assertTrue($page->viewData('units')->firstWhere('dept.key', 'print')['members']->contains($designer));
        $this->assertTrue($page->viewData('others')->isEmpty());
    }

    public function test_only_hr_edits_departments_and_old_jobs_link_redirects(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'department' => 'tech']);
        $hr = User::factory()->create(['role' => User::ROLE_HR]);
        $dept = Department::ordered()->firstWhere('key', 'tech');

        $this->actingAs($staff)->get(route('hr.departments'))->assertOk()->assertSee('KretivTech')->assertDontSee('Products (one per line)');
        $this->actingAs($staff)->put(route('hr.departments.update', $dept), ['services' => 'X'])->assertForbidden();
        $this->actingAs($hr)->put(route('hr.departments.update', $dept), ['head_user_id' => $staff->id, 'services' => "Apps\n\nWebsites\n"])->assertRedirect();
        $this->assertSame(['Apps', 'Websites'], $dept->refresh()->services);

        $this->actingAs($staff)->get(route('departments.index'))->assertRedirect(route('hr.departments'));
    }
}
