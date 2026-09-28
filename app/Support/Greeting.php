<?php

namespace App\Support;

use App\Models\PublicHoliday;
use App\Models\User;
use Carbon\CarbonInterface;

// The KretivOS home greeting: a short hello by time of day, plus a light
// one-liner that changes daily (and on Mondays, Fridays, payday, public
// holidays and birthdays). Kept short so it fits the card on a phone.
class Greeting
{
    /** [until hour (exclusive), title, one-liners] */
    private const SLOTS = [
        [5, 'Working late', ['Get some sleep soon.', 'The work can wait.', 'Night owl mode.']],
        [12, 'Good morning', ['Coffee first, then conquer.', 'Fresh day, fresh ideas.', "Let's make today count."]],
        [15, 'Good afternoon', ['Lunch sorted?', 'Halfway there.', 'Keep the momentum going.']],
        [19, 'Good afternoon', ['Final stretch.', 'Teh tarik break?', 'Wrap it up strong.']],
        [24, 'Good evening', ["Don't forget to rest.", 'Still going? Respect.', 'Almost time to switch off.']],
    ];

    /** @return array{title: string, line: string} */
    public static function for(User $user, CarbonInterface $now): array
    {
        [, $title, $lines] = collect(self::SLOTS)->first(fn ($s) => $now->hour < $s[0]);
        $name = $user->shortName();

        $dob = $user->employee?->date_of_birth;
        $line = match (true) {
            $dob && $dob->format('m-d') === $now->format('m-d') => 'Happy birthday.',
            PublicHoliday::whereDate('date', $now->toDateString())->exists() => 'Enjoy the holiday.',
            $now->day === (int) config('kretivco.payroll.pay_day') => 'Payday. Well earned.',
            $now->isFriday() && $now->hour >= 5 => "It's Friday. Finish strong.",
            $now->isMonday() && $now->hour >= 5 && $now->hour < 15 => 'New week, new wins.',
            // Same line all day for a person, a different one tomorrow.
            default => $lines[crc32($now->toDateString().'|'.$user->id.'|'.$title) % count($lines)],
        };

        return ['title' => "{$title}, {$name}", 'line' => $line];
    }
}
