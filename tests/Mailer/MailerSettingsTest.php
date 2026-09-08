<?php

namespace App\Tests\Mailer;

use App\Mailer\MailerSettings;
use PHPUnit\Framework\TestCase;

class MailerSettingsTest extends TestCase
{
    public function testHidesSendInProdWhenTransportIsNull(): void
    {
        $settings = new MailerSettings('prod', 'null://null', 'me@example.com');

        $this->assertFalse($settings->isSendVisible());
        $this->assertFalse($settings->shouldRedirect());
    }

    public function testShowsSendInDevWithNullMockTransport(): void
    {
        $settings = new MailerSettings('dev', 'null://null', 'gasper@sensware.si');

        $this->assertTrue($settings->isSendVisible());
        $this->assertTrue($settings->shouldRedirect());
        $this->assertStringContainsString('would redirect to gasper@sensware.si', $settings->describeDelivery('client@example.com'));
    }

    public function testDescribesRealRedirect(): void
    {
        $settings = new MailerSettings('dev', 'smtp://localhost', 'gasper@sensware.si');

        $this->assertTrue($settings->isSendVisible());
        $this->assertSame(
            'Invoice sent to client@example.com (redirected to gasper@sensware.si)',
            $settings->describeDelivery('client@example.com'),
        );
    }
}
