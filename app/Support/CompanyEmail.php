<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

// Suggests a company email for a new joiner: first name first
// (aisyah@kretiv.co), then first.second, first.last, then a number, skipping
// ones already taken. Malay/Indian patronymic words (bin, binti, a/l ...)
// are ignored so "Nur Aisyah binti Ahmad" gives nur@, nur.aisyah@, nur.ahmad@.
class CompanyEmail
{
    private const SKIP = ['bin', 'binti', 'bt', 'bte', 'b', 'a/l', 'a/p', 'al', 'ap', 'anak', 'md', 'mohd', 'muhammad', 'muhd', '@'];

    public static function domain(): string
    {
        return config('kretivco.company_email_domain', 'kretiv.co');
    }

    public static function suggest(string $name, ?int $ignoreUserId = null): ?string
    {
        $words = collect(preg_split('/\s+/', Str::lower(Str::ascii(trim($name)))))
            ->map(fn ($w) => trim($w, " .,'"))
            ->reject(fn ($w) => $w === '' || in_array($w, self::SKIP, true))
            ->map(fn ($w) => preg_replace('/[^a-z0-9]/', '', $w))
            ->filter()->values();

        if ($words->isEmpty()) {
            return null;
        }

        $first = $words[0];
        $candidates = collect([$first, $words->count() > 1 ? "{$first}.{$words[1]}" : null, $words->count() > 2 ? "{$first}.{$words->last()}" : null])
            ->filter()->merge(collect(range(2, 9))->map(fn ($n) => $first.$n))->unique();

        foreach ($candidates as $local) {
            $email = "{$local}@".self::domain();
            if (! User::where('email', $email)->when($ignoreUserId, fn ($q) => $q->where('id', '!=', $ignoreUserId))->exists()) {
                return $email;
            }
        }

        return null;
    }
}
