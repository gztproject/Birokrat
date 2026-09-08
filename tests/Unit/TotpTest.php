<?php

namespace App\Tests\Unit;

use App\Security\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    public function testGeneratesAndVerifiesCurrentCode(): void
    {
        $secret = Totp::generateSecret();
        $code = Totp::at($secret, (int) floor(time() / 30));
        $this->assertTrue(Totp::verify($secret, $code));
        $this->assertFalse(Totp::verify($secret, '000000'));
    }
}
