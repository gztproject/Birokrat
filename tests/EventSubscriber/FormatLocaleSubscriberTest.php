<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\FormatLocaleSubscriber;
use App\Formatting\SlovenianFormat;
use PHPUnit\Framework\TestCase;

class FormatLocaleSubscriberTest extends TestCase
{
    public function testOverridesPhpLocaleAfterUiLocaleIsSet(): void
    {
        if (!\extension_loaded('intl')) {
            $this->markTestSkipped('intl extension is required');
        }

        \Locale::setDefault('en_US');

        $subscriber = new FormatLocaleSubscriber();
        $subscriber->onKernelRequest();

        $this->assertSame(SlovenianFormat::LOCALE, \Locale::getDefault());
    }
}
