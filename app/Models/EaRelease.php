<?php

namespace App\Models;

use App\Models\Concerns\Audited;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['year', 'released_by'])]
class EaRelease extends Model
{
    use Audited;

    protected string $auditModule = 'hr';

    public function auditName(): string
    {
        return 'EA forms '.$this->year;
    }
}
