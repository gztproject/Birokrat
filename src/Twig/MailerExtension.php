<?php

namespace App\Twig;

use App\Mailer\MailerSettings;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class MailerExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private readonly MailerSettings $settings)
    {
    }

    public function getGlobals(): array
    {
        return [
            'mailer_enabled' => $this->settings->isSendVisible(),
            'mailer_override_to' => $this->settings->shouldRedirect() ? $this->settings->overrideTo() : '',
        ];
    }
}
