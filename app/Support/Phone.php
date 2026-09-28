<?php

namespace App\Support;

// Phone numbers are kept in one format, +60 followed by digits
// (e.g. +60123456789), whatever way they were typed: 012-345 6789,
// 60123456789 or +6012 3456789. Numbers already given with another
// country code (+65...) are kept as typed, digits only.
class Phone
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $foreign = str_starts_with(trim($value), '+') && ! str_starts_with(preg_replace('/[\s\-()]/', '', $value), '+60');
        $digits = preg_replace('/\D/', '', $value);
        if ($digits === '') {
            return trim($value);
        }
        if ($foreign) {
            return '+'.$digits;
        }
        if (str_starts_with($digits, '60')) {
            return '+'.$digits;
        }
        if (str_starts_with($digits, '0')) {
            return '+60'.substr($digits, 1);
        }

        return '+60'.$digits;
    }

    /** Digits only, for wa.me links. */
    public static function whatsapp(?string $value): string
    {
        return preg_replace('/\D/', '', (string) self::normalize($value));
    }
}
