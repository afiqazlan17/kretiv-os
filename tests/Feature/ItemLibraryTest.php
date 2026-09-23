<?php

namespace Tests\Feature;

use App\Models\ItemLibrary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_any_staff_can_add_an_item_and_duplicates_are_rejected(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);

        $this->actingAs($staff)->post(route('items.store'), ['department' => 'print', 'item_name' => 'Kad Kahwin', 'price' => 1.5, 'description' => 'Art card 310gsm'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('item_library', ['item_name' => 'Kad Kahwin', 'price' => 1.5, 'active' => true]);

        $this->actingAs($staff)->post(route('items.store'), ['department' => 'print', 'item_name' => 'Kad Kahwin'])
            ->assertSessionHasErrors('item_name');
        $this->assertSame(1, ItemLibrary::count());
    }

    public function test_search_matches_name_or_description_hides_inactive_and_puts_own_department_first(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_STAFF]);
        ItemLibrary::create(['department' => 'brand', 'item_name' => 'Banner Design', 'price' => 80]);
        ItemLibrary::create(['department' => 'print', 'item_name' => 'Banner Print', 'price' => 30]);
        ItemLibrary::create(['department' => 'print', 'item_name' => 'Banner Old', 'active' => false]);
        ItemLibrary::create(['department' => 'print', 'item_name' => 'Sticker', 'description' => 'Vinyl banner-grade']);

        $names = $this->actingAs($user)->getJson(route('items.search', ['q' => 'banner', 'dept' => 'print']))->assertOk()->json('*.name');

        $this->assertSame(['Banner Print', 'Sticker', 'Banner Design'], $names);
    }

    public function test_only_bod_or_dept_head_can_edit_and_hiding_works(): void
    {
        $item = ItemLibrary::create(['department' => 'print', 'item_name' => 'Flyer', 'price' => 10]);
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);

        $this->actingAs($staff)->put(route('items.update', $item), ['department' => 'print', 'item_name' => 'Flyer X'])->assertForbidden();

        $this->actingAs($bod)->put(route('items.update', $item), ['department' => 'print', 'item_name' => 'Flyer', 'price' => 12])->assertSessionHasNoErrors();
        $item->refresh();
        $this->assertFalse($item->active);
        $this->assertEquals(12, $item->price);
    }

    public function test_items_page_and_dropdown_wiring_render(): void
    {
        $bod = User::factory()->create(['role' => User::ROLE_BOD]);
        ItemLibrary::create(['department' => 'print', 'item_name' => 'Roll Up Banner']);

        $this->actingAs($bod)->get(route('items.index'))->assertOk()->assertSee('Roll Up Banner');
        $this->actingAs($bod)->get(route('jobs.create'))->assertOk()->assertSee('Manage library');
    }
}
