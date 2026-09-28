<?php

namespace Tests\Unit;

use App\Support\Phone;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    public function test_numbers_are_kept_as_plus_sixty(): void
    {
        $this->assertSame('+60123456789', Phone::normalize('012-345 6789'));
        $this->assertSame('+60123456789', Phone::normalize('60123456789'));
        $this->assertSame('+60123456789', Phone::normalize('+60 12-345 6789'));
        $this->assertSame('+60123456789', Phone::normalize('123456789'));
        $this->assertSame('+6591234567', Phone::normalize('+65 9123 4567'));
        $this->assertNull(Phone::normalize('  '));
        $this->assertSame('60123456789', Phone::whatsapp('012-3456789'));
    }
}
