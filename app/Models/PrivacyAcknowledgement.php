<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrivacyAcknowledgement extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'version', 'ip', 'user_agent', 'acknowledged_at'];

    protected function casts(): array
    {
        return ['acknowledged_at' => 'datetime'];
    }
}
