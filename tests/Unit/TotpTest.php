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

    public function testQrCodeDataUriEncodesProvisioningUri(): void
    {
        $uri = Totp::provisioningUri('MFRGGZDFMZTWQ2LK', 'jane');
        $dataUri = Totp::qrCodeDataUri($uri);

        $this->assertStringStartsWith('data:image/svg+xml;base64,', $dataUri);
        $svg = base64_decode(substr($dataUri, strlen('data:image/svg+xml;base64,')), true);
        $this->assertIsString($svg);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('<rect', $svg);
    }
}
