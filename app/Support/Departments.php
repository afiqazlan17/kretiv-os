<?php

namespace App\Support;

// Every unit a staff member can belong to: the Jobs business units
// (config kretivco.departments) plus support units like Finance & Admin.
class Departments
{
    /** @return array<string, array{label: string, color: string}> */
    public static function all(): array
    {
        return config('kretivco.departments') + config('kretivco.support_units', []);
    }

    public static function label(?string $key): ?string
    {
        return $key ? (self::all()[$key]['label'] ?? ucfirst($key)) : null;
    }

    public static function color(?string $key): string
    {
        return self::all()[$key]['color'] ?? '#6B7280';
    }
}
