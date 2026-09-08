<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\RedirectOutgoingMailSubscriber;
use App\Mailer\MailerSettings;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class RedirectOutgoingMailSubscriberTest extends TestCase
{
    public function testRewritesRecipientsInDev(): void
    {
        $email = (new Email())
            ->from('birokrat@gzt.si')
            ->to('client@example.com')
            ->subject('Invoice')
            ->text('body');
        $event = new MessageEvent(
            $email,
            new Envelope(new Address('birokrat@gzt.si'), [new Address('client@example.com')]),
            'null',
        );

        $subscriber = new RedirectOutgoingMailSubscriber(new MailerSettings('dev', 'null://null', 'gasper@sensware.si'));
        $subscriber->onMessage($event);

        $this->assertSame(['gasper@sensware.si'], array_map(
            static fn (Address $address): string => $address->getAddress(),
            $event->getEnvelope()->getRecipients(),
        ));
        $this->assertSame('client@example.com', $email->getHeaders()->get('X-Original-To')?->getBodyAsString());
        $this->assertSame('gasper@sensware.si', $email->getTo()[0]->getAddress());
    }

    public function testRecordsOriginalCcInDev(): void
    {
        $email = (new Email())
            ->from('birokrat@gzt.si')
            ->to('client@example.com')
            ->cc('acc@example.com')
            ->subject('Invoice')
            ->text('body');
        $event = new MessageEvent(
            $email,
            new Envelope(new Address('birokrat@gzt.si'), [
                new Address('client@example.com'),
                new Address('acc@example.com'),
            ]),
            'null',
        );

        $subscriber = new RedirectOutgoingMailSubscriber(new MailerSettings('dev', 'null://null', 'gasper@sensware.si'));
        $subscriber->onMessage($event);

        $this->assertSame(['gasper@sensware.si'], array_map(
            static fn (Address $address): string => $address->getAddress(),
            $event->getEnvelope()->getRecipients(),
        ));
        $this->assertStringContainsString('client@example.com', $email->getHeaders()->get('X-Original-To')?->getBodyAsString() ?? '');
        $this->assertStringContainsString('acc@example.com', $email->getHeaders()->get('X-Original-To')?->getBodyAsString() ?? '');
        $this->assertSame('gasper@sensware.si', $email->getTo()[0]->getAddress());
        $this->assertSame([], $email->getCc());
    }

    public function testDoesNotRewriteInProd(): void
    {
        $email = (new Email())->from('birokrat@gzt.si')->to('client@example.com')->subject('Invoice')->text('body');
        $event = new MessageEvent(
            $email,
            new Envelope(new Address('birokrat@gzt.si'), [new Address('client@example.com')]),
            'smtp',
        );

        $subscriber = new RedirectOutgoingMailSubscriber(new MailerSettings('prod', 'smtp://localhost', 'gasper@sensware.si'));
        $subscriber->onMessage($event);

        $this->assertSame('client@example.com', $event->getEnvelope()->getRecipients()[0]->getAddress());
        $this->assertSame('client@example.com', $email->getTo()[0]->getAddress());
    }
}
