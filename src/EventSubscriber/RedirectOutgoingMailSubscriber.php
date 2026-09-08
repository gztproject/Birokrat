<?php

namespace App\EventSubscriber;

use App\Mailer\MailerSettings;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class RedirectOutgoingMailSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly MailerSettings $settings)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [MessageEvent::class => ['onMessage', -255]];
    }

    public function onMessage(MessageEvent $event): void
    {
        if ($event->isQueued() || !$this->settings->shouldRedirect()) {
            return;
        }

        $override = new Address($this->settings->overrideTo());
        $event->getEnvelope()->setRecipients([$override]);

        $message = $event->getMessage();
        if (!$message instanceof Email) {
            return;
        }

        $original = implode(', ', array_map(
            static fn (Address $address): string => $address->toString(),
            $message->getTo(),
        ));
        if ($original !== '') {
            $message->getHeaders()->addTextHeader('X-Original-To', $original);
        }
        $message->to($override);
    }
}
