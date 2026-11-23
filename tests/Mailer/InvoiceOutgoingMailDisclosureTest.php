<?php

namespace App\Tests\Mailer;

use App\EventSubscriber\RedirectOutgoingMailSubscriber;
use App\Mailer\MailerSettings;
use App\Mailer\RecipientListParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mailer\EventListener\EnvelopeListener;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\AbstractStream;
use Symfony\Component\Mailer\Transport\Transports;
use Symfony\Component\Mime\Email;
use Symfony\Component\Yaml\Yaml;

/**
 * Invoice mail must be delivered to the addresses the signed-in user entered.
 * The shipped .env leaves APP_ENV unset (Symfony boots dev) and sets a mail
 * override, and config/services.yaml repeats that override as the parameter
 * used when the variable is absent. An operator who then points MAILER_DSN at
 * a real SMTP server still has every invoice, including its PDF, sent there.
 */
class InvoiceOutgoingMailDisclosureTest extends TestCase
{
    private const REAL_DSN = 'smtp://mail.example.test';

    public function testShippedEnvironmentDeliversInvoiceToAddressesTheUserEntered(): void
    {
        $shipped = $this->shippedDotenv();
        $settings = new MailerSettings($shipped['appEnv'], self::REAL_DSN, $shipped['overrideTo']);

        $transcript = $this->deliverInvoice($settings, 'customer@example.com', 'accounts@example.com', 'invoice-body-marker');

        $this->assertSame(
            ['accounts@example.com', 'customer@example.com'],
            $this->rcptTo($transcript),
            'Invoice SMTP recipients must be the addresses the user entered.',
        );
        $this->assertMatchesRegularExpression('/^To: customer@example.com/m', $transcript);
        $this->assertMatchesRegularExpression('/^Cc: accounts@example.com/m', $transcript);
        $this->assertStringContainsString('invoice-body-marker', $transcript);
        $this->assertStringContainsString('filename=invoice-1.pdf', $transcript);
    }

    public function testServicesYamlOverrideDoesNotReplaceInvoiceRecipientsInDevelopment(): void
    {
        $parameters = Yaml::parseFile(dirname(__DIR__, 2).'/config/services.yaml')['parameters'];
        $fallback = (string) ($parameters['env(MAILER_OVERRIDE_TO)'] ?? '');
        $settings = new MailerSettings('dev', self::REAL_DSN, $fallback);

        $transcript = $this->deliverInvoice($settings, 'customer@example.com', 'accounts@example.com', 'invoice-body-marker');

        $this->assertSame(
            ['accounts@example.com', 'customer@example.com'],
            $this->rcptTo($transcript),
            'The services.yaml mail override must not become the SMTP recipient in the development environment.',
        );
        $this->assertMatchesRegularExpression('/^To: customer@example.com/m', $transcript);
        $this->assertMatchesRegularExpression('/^Cc: accounts@example.com/m', $transcript);
    }

    private function deliverInvoice(MailerSettings $settings, string $to, string $cc, string $bodyMarker): string
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber(new RedirectOutgoingMailSubscriber($settings));
        $dispatcher->addSubscriber(new EnvelopeListener());

        $stream = new ScriptedSmtpStream();
        $transport = new SmtpTransport($stream, $dispatcher);
        $transport->setPingThreshold(PHP_INT_MAX);
        $mailer = new Mailer(new Transports(['main' => $transport]), null, $dispatcher);

        $parser = new RecipientListParser();
        $pdf = tempnam(sys_get_temp_dir(), 'invoice');
        file_put_contents($pdf, 'invoice-pdf-bytes');
        try {
            $email = (new Email())
                ->from('birokrat@gzt.si')
                ->to(...$parser->parse($to))
                ->subject('Invoice 1')
                ->html('<p>'.$bodyMarker.'</p>')
                ->attachFromPath($pdf, 'invoice-1.pdf');
            $ccAddresses = $parser->parse($cc);
            if ($ccAddresses !== []) {
                $email->cc(...$ccAddresses);
            }

            $mailer->send($email);
        } finally {
            unlink($pdf);
        }

        return $stream->written();
    }

    /**
     * @return list<string>
     */
    private function rcptTo(string $transcript): array
    {
        preg_match_all('/^RCPT TO:<([^>]+)>/m', $transcript, $matches);
        $recipients = $matches[1];
        sort($recipients);

        return $recipients;
    }

    /**
     * @return array{appEnv: string, mailerDsn: string, overrideTo: string}
     */
    private function shippedDotenv(): array
    {
        $projectDir = dirname(__DIR__, 2);
        $script = <<<'PHP'
<?php
require $argv[1];
foreach (['APP_ENV', 'APP_DEBUG', 'MAILER_DSN', 'MAILER_OVERRIDE_TO', 'SYMFONY_DOTENV_VARS'] as $name) {
    unset($_SERVER[$name], $_ENV[$name]);
    putenv($name);
}
(new Symfony\Component\Dotenv\Dotenv())->bootEnv($argv[2], 'dev', ['test'], false);
$override = $_ENV['MAILER_OVERRIDE_TO'] ?? $_SERVER['MAILER_OVERRIDE_TO'] ?? '';
echo json_encode([
    'appEnv' => (string) ($_SERVER['APP_ENV'] ?? ''),
    'mailerDsn' => (string) ($_ENV['MAILER_DSN'] ?? $_SERVER['MAILER_DSN'] ?? ''),
    'overrideTo' => is_string($override) ? $override : '',
], JSON_THROW_ON_ERROR);
PHP;
        $file = tempnam(sys_get_temp_dir(), 'shipped-dotenv');
        file_put_contents($file, $script);
        $command = [
            PHP_BINARY,
            $file,
            $projectDir.'/vendor/autoload.php',
            $projectDir.'/.env',
        ];
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        unlink($file);

        $this->assertSame(0, $exit, $stderr);
        $decoded = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return [
            'appEnv' => (string) $decoded['appEnv'],
            'mailerDsn' => (string) $decoded['mailerDsn'],
            'overrideTo' => (string) $decoded['overrideTo'],
        ];
    }
}

/**
 * SMTP peer that answers each command the way SmtpTransport::assertResponseCode requires.
 * Bytes written are the commands and message data produced by SmtpTransport::doSend.
 */
final class ScriptedSmtpStream extends AbstractStream
{
    private string $written = '';
    private string $lastCommand = '';
    private bool $greeted = false;

    public function initialize(): void
    {
        $this->in = fopen('php://temp', 'w+');
        $this->out = fopen('php://temp', 'w+');
    }

    public function write(string $bytes, bool $debug = true): void
    {
        $this->written .= $bytes;
        if ($debug) {
            $this->lastCommand = $bytes;
        }
        parent::write($bytes, $debug);
    }

    public function readLine(): string
    {
        if (!$this->greeted) {
            $this->greeted = true;

            return "220 ready\r\n";
        }

        $command = strtoupper(trim($this->lastCommand));
        $this->lastCommand = '';
        if (str_starts_with($command, 'DATA')) {
            return "354 go\r\n";
        }
        if (str_starts_with($command, 'QUIT')) {
            return "221 bye\r\n";
        }

        return "250 ok\r\n";
    }

    public function written(): string
    {
        return $this->written;
    }

    protected function getReadConnectionDescription(): string
    {
        return 'scripted-smtp';
    }
}
