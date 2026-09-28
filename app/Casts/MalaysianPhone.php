<?php

namespace App\Casts;

use App\Support\Phone;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/** Stores phone numbers as +60XXXXXXXXX (see App\Support\Phone). */
class MalaysianPhone implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return Phone::normalize($value);
    }
}
