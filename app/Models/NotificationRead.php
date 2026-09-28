<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'key', 'read_at'])]
class NotificationRead extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
