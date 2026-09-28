<?php

namespace Tests\Feature;

use App\Models\PublicHoliday;
use App\Models\User;
use App\Support\Greeting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GreetingTest extends TestCase
{
    use RefreshDatabase;

    public function test_greeting_follows_the_time_of_day_and_special_days(): void
    {
        $user = User::factory()->create(['name' => 'Afiq Azlan']);
        $at = fn (string $t) => Greeting::for($user, Carbon::parse($t));

        $this->assertSame('Working late, Afiq', $at('2026-09-29 00:15')['title']);   // a Tuesday
        $this->assertSame('Good morning, Afiq', $at('2026-09-29 09:00')['title']);
        $this->assertSame('Good afternoon, Afiq', $at('2026-09-29 13:00')['title']);
        $this->assertSame('Good evening, Afiq', $at('2026-09-29 20:00')['title']);
        $this->assertSame($at('2026-09-29 09:00')['line'], $at('2026-09-29 11:30')['line']); // same all morning

        $this->assertSame("It's Friday. Finish strong.", $at('2026-10-02 10:00')['line']);
        $this->assertSame('New week, new wins.', $at('2026-10-05 09:00')['line']);
        $this->assertSame('Payday. Well earned.', $at('2026-09-25 09:00')['line']);

        PublicHoliday::create(['date' => '2026-10-20', 'name' => 'Test']);
        $this->assertSame('Enjoy the holiday.', $at('2026-10-20 10:00')['line']);

        $user->employee()->create(['date_of_birth' => '1995-10-21']);
        $this->assertSame('Happy birthday.', Greeting::for($user->refresh(), Carbon::parse('2026-10-21 10:00'))['line']);
    }
}
