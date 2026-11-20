<?php

namespace App\Tests\Web;

use App\Tests\Support\SmokeFixtures;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\Event\SentMessageEvent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\AbstractMultipartPart;
use Symfony\Component\Mime\Part\AbstractPart;
use Symfony\Component\Mime\Part\TextPart;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * The invoice send action must deliver the typed message without letting the
 * recipient's mail client treat it as HTML. These assertions fail while the
 * posted body is concatenated into the HTML part.
 */
class InvoiceEmailHtmlInjectionTest extends WebTestCase
{
    private const RECIPIENT = 'recipient@attacker.example';
    private const COPY = 'copy@attacker.example';
    private const SENTENCE = 'Please review this invoice.';
    private const LINK = '<a href="https://attacker.example/invoice">Review this invoice</a>';
    private const IMAGE = '<img src="https://attacker.example/pixel.png" alt="invoice-pixel">';

    private KernelBrowser $client;
    private array $fixtures = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Production does not rewrite recipients. .env.test sets MAILER_OVERRIDE_TO,
        // which would hide the address this request submits. An empty value is
        // treated as unset and becomes null; a space trims to no override.
        $_SERVER['MAILER_OVERRIDE_TO'] = ' ';
        $_ENV['MAILER_OVERRIDE_TO'] = ' ';
        putenv('MAILER_OVERRIDE_TO= ');

        try {
            $this->client = static::createClient();
            $container = static::getContainer();
            $em = $container->get(EntityManagerInterface::class);
            $em->getConnection()->executeQuery('SELECT 1');
            $this->fixtures = (new SmokeFixtures(
                $em,
                $container->get(UserPasswordHasherInterface::class),
            ))->load();
        } catch (DbalException|\PDOException|\Throwable $e) {
            $this->markTestSkipped('Database is not available for application tests: '.$e->getMessage());
        }
    }

    public function testOrdinaryUserMessageIsNotActiveHtmlInTheSentInvoice(): void
    {
        $user = $this->fixtures['user'];
        $this->assertNotContains('ROLE_ADMIN', $user->getRoles());
        $this->assertFalse($user->getIsRoleAdmin());

        $this->client->loginUser($user);

        $sent = [];
        static::getContainer()->get('event_dispatcher')->addListener(
            SentMessageEvent::class,
            static function (SentMessageEvent $event) use (&$sent): void {
                $message = $event->getMessage();
                // The controller deletes the PDF attachment as soon as send() returns.
                // Serialize while that file is still on disk; this is the body the transport was given.
                $sent[] = [
                    'message' => $message,
                    'raw' => $message->toString(),
                ];
            },
        );

        $body = self::SENTENCE."\n</p>".self::LINK.self::IMAGE.'<p>';
        $invoiceId = $this->fixtures['invoice']->getId()->toString();
        $this->client->request('POST', '/dashboard/invoice/send', [
            'id' => $invoiceId,
            'email' => self::RECIPIENT,
            'cc' => self::COPY,
            'subject' => 'Invoice notice',
            'body' => $body,
        ]);

        $this->assertResponseIsSuccessful();
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        $this->assertIsArray($payload);
        $this->assertSame('ok', $payload[0]['status'] ?? null, (string) ($payload[0]['data'][0] ?? ''));

        $this->assertCount(1, $sent, 'The send action did not hand a message to the mailer.');
        $message = $sent[0]['message'];
        $raw = $sent[0]['raw'];
        $email = $message->getOriginalMessage();
        $this->assertInstanceOf(Email::class, $email);
        $this->assertMatchesRegularExpression('/^From:.*birokrat@gzt\.si/m', $raw);
        $this->assertStringContainsString(self::RECIPIENT, $raw);
        $this->assertStringContainsString(self::COPY, $raw);
        $this->assertSame(
            [self::RECIPIENT, self::COPY],
            array_map(static fn (Address $address): string => $address->getAddress(), $message->getEnvelope()->getRecipients()),
        );

        $htmlParts = $this->htmlParts($email->getBody());
        if ($htmlParts === []) {
            $text = $email->getTextBody();
            $this->assertIsString($text);
            $this->assertStringContainsString(self::SENTENCE, $text);
            $this->assertDoesNotMatchRegularExpression('/Content-Type:\s*text\/html/i', $raw);

            return;
        }

        $this->assertStringContainsString('Content-Type: text/html', $raw);
        foreach ($htmlParts as $part) {
            $encoded = $part->bodyToString();
            $this->assertNotSame('', $encoded);
            $this->assertStringContainsString($encoded, $raw);
            $decoded = quoted_printable_decode($encoded);
            $this->assertStringContainsString(self::SENTENCE, $decoded);
            $active = [];
            if (preg_match('/<\s*a\b/i', $decoded) === 1) {
                $active[] = 'link';
            }
            if (preg_match('/<\s*img\b/i', $decoded) === 1) {
                $active[] = 'image';
            }
            $this->assertSame(
                [],
                $active,
                'The text/html part still contains user markup ('.implode(', ', $active).'). Decoded part: '.$decoded,
            );
        }
    }

    /**
     * @return list<TextPart>
     */
    private function htmlParts(AbstractPart $part): array
    {
        if ($part instanceof TextPart && $part->getMediaSubtype() === 'html') {
            return [$part];
        }
        if (!$part instanceof AbstractMultipartPart) {
            return [];
        }

        $html = [];
        foreach ($part->getParts() as $child) {
            $html = array_merge($html, $this->htmlParts($child));
        }

        return $html;
    }
}
