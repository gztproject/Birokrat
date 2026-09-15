<?php

namespace App\EventSubscriber;

use App\Formatting\SlovenianFormat;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Keeps PHP/Intl number and date formatting on sl_SI after the UI locale is applied.
 */
final class FormatLocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly string $formatLocale = SlovenianFormat::LOCALE,
    ) {
    }

    public function onKernelRequest(): void
    {
        if (\extension_loaded('intl')) {
            \Locale::setDefault($this->formatLocale);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // After Symfony's LocaleListener (16) and LocaleAwareListener (15).
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }
}
