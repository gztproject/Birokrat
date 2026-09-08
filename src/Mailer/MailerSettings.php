<?php

namespace App\Mailer;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class MailerSettings
{
    public function __construct(
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        #[Autowire('%env(MAILER_DSN)%')]
        private readonly string $dsn,
        #[Autowire('%env(default::MAILER_OVERRIDE_TO)%')]
        private readonly string $overrideTo = '',
    ) {
    }

    public function isSendVisible(): bool
    {
        if ($this->dsn === '') {
            return false;
        }

        return !($this->isNullTransport() && $this->environment === 'prod');
    }

    public function isNullTransport(): bool
    {
        return str_starts_with(strtolower(trim($this->dsn)), 'null:');
    }

    public function overrideTo(): string
    {
        return trim($this->overrideTo);
    }

    public function shouldRedirect(): bool
    {
        return $this->overrideTo() !== '' && \in_array($this->environment, ['dev', 'test'], true);
    }

    public function describeDelivery(string $intendedRecipient, string $cc = ''): string
    {
        $message = 'Invoice sent to '.$intendedRecipient;
        if (trim($cc) !== '') {
            $message .= ' (CC: '.$cc.')';
        }
        if ($this->shouldRedirect()) {
            $message .= $this->isNullTransport()
                ? ' (not delivered: MAILER_DSN is null; would redirect to '.$this->overrideTo().')'
                : ' (redirected to '.$this->overrideTo().')';
        } elseif ($this->isNullTransport()) {
            $message .= ' (not delivered: MAILER_DSN is null)';
        }

        return $message;
    }
}
