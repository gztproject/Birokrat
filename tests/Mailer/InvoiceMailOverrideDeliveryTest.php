<?php

namespace App\Tests\Mailer;

use PHPUnit\Framework\TestCase;

/**
 * Invoice mail must be delivered to the addresses the sender wrote on the message.
 *
 * These tests boot the same runtime as public/index.php and hand the message to the
 * real mailer. They fail while a committed override replaces the envelope
 * recipients before SMTP RCPT TO.
 */
class InvoiceMailOverrideDeliveryTest extends TestCase
{
    private const CUSTOMER = 'customer@example.test';
    private const COPY = 'accounts@example.test';
    private const PDF_MARKER = 'invoice-pdf-marker';

    public function testCommittedEnvWithRealDsnDeliversInvoiceToAddressedCustomers(): void
    {
        $delivery = $this->deliverInvoice('committed');

        $this->assertInvoiceDeliveredToCustomers($delivery);
    }

    public function testDevWithRealDsnDeliversInvoiceToAddressedCustomers(): void
    {
        $delivery = $this->deliverInvoice('dev');

        $this->assertSame('dev', $delivery['app_env']);
        $this->assertInvoiceDeliveredToCustomers($delivery);
    }

    public function testServiceDefaultDoesNotReplaceInvoiceRecipientsInDev(): void
    {
        $delivery = $this->deliverInvoice('service-default');

        $this->assertSame('dev', $delivery['app_env']);
        $this->assertInvoiceDeliveredToCustomers($delivery);
    }

    /**
     * @param array{app_env: string, rcpt_to: list<string>, data: string, smtp_completed: bool, queued: bool} $delivery
     */
    private function assertInvoiceDeliveredToCustomers(array $delivery): void
    {
        $this->assertTrue($delivery['smtp_completed'], 'SMTP did not accept the invoice message.');
        $this->assertFalse($delivery['queued'], 'The invoice mailer queued the message instead of delivering it.');
        $this->assertTrue(
            str_contains($delivery['data'], self::PDF_MARKER)
            || str_contains($delivery['data'], base64_encode(self::PDF_MARKER)),
            'The invoice attachment must be part of the delivered message.',
        );
        $this->assertTrue(
            (bool) preg_match('/^To: '.preg_quote(self::CUSTOMER, '/').'/m', $delivery['data']),
            'The delivered To header must stay the customer address.',
        );
        $this->assertTrue(
            (bool) preg_match('/^Cc: '.preg_quote(self::COPY, '/').'/m', $delivery['data']),
            'The delivered Cc header must stay the copy address.',
        );
        $this->assertFalse(
            str_contains($delivery['data'], 'X-Original-To:'),
            'The addressed customers must not be moved to an extra header.',
        );
        $this->assertTrue(
            $delivery['rcpt_to'] === [self::CUSTOMER, self::COPY],
            'SMTP recipients must be the customer and the copy.',
        );
    }

    /**
     * @return array{app_env: string, rcpt_to: list<string>, data: string, smtp_completed: bool, queued: bool}
     */
    private function deliverInvoice(string $mode): array
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $this->assertIsResource($server, $errstr);
        $address = stream_socket_get_name($server, false);
        $this->assertIsString($address);
        $port = (int) substr($address, (int) strrpos($address, ':') + 1);

        $stderrFile = tempnam(sys_get_temp_dir(), 'invoice-mail-');
        $this->assertIsString($stderrFile);

        $env = [
            'PATH' => (string) getenv('PATH'),
            'HOME' => (string) (getenv('HOME') ?: '/tmp'),
            'LANG' => (string) (getenv('LANG') ?: 'C.UTF-8'),
            'APP_SECRET' => 'test-secret',
            'DATABASE_URL' => 'mysql://app:app@127.0.0.1:3306/birokrat?serverVersion=8.0.36&charset=utf8mb4',
            'MAILER_DSN' => 'smtp://127.0.0.1:'.$port.'?auto_tls=false',
            'PROBE_MODE' => $mode,
        ];
        if ('committed' !== $mode) {
            $env['APP_ENV'] = 'dev';
        }

        $pipes = [];
        $process = proc_open([PHP_BINARY, dirname(__DIR__, 2).'/tests/Mailer/invoice_mail_delivery_probe.php'], [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['file', $stderrFile, 'w'],
        ], $pipes, dirname(__DIR__, 2), $env);
        $this->assertIsResource($process);
        fclose($pipes[0]);
        stream_set_blocking($server, false);

        $client = null;
        $deadline = microtime(true) + 90;
        while (microtime(true) < $deadline && !is_resource($client)) {
            $client = @stream_socket_accept($server, 0);
            if (is_resource($client)) {
                break;
            }
            $status = proc_get_status($process);
            if (!$status['running']) {
                break;
            }
            usleep(50000);
        }

        $transcript = ['rcpt_to' => [], 'data' => '', 'completed' => false];
        if (is_resource($client)) {
            $transcript = $this->acceptSmtpDelivery($client);
            fclose($client);
        }
        fclose($server);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $exit = proc_close($process);
        $stderr = (string) file_get_contents($stderrFile);
        unlink($stderrFile);

        $this->assertSame(0, $exit, "Probe failed.\n".$stderr."\n".$stdout);
        $decoded = json_decode((string) $stdout, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);
        $this->assertArrayNotHasKey('error', $decoded, (string) ($decoded['error'] ?? ''));

        return [
            'app_env' => (string) $decoded['app_env'],
            'rcpt_to' => $transcript['rcpt_to'],
            'data' => $transcript['data'],
            'smtp_completed' => $transcript['completed'],
            'queued' => (bool) $decoded['queued'],
        ];
    }

    /**
     * @param resource $client
     *
     * @return array{rcpt_to: list<string>, data: string, completed: bool}
     */
    private function acceptSmtpDelivery($client): array
    {
        stream_set_timeout($client, 30);
        fwrite($client, "220 localhost ESMTP\r\n");

        $rcpt = [];
        $data = '';
        $readingData = false;
        $completed = false;

        while (!feof($client)) {
            $line = fgets($client);
            if (!is_string($line)) {
                break;
            }
            if ($readingData) {
                $data .= $line;
                if ('.' === rtrim($line, "\r\n")) {
                    $readingData = false;
                    $completed = true;
                    fwrite($client, "250 OK\r\n");
                }
                continue;
            }

            $command = strtoupper(substr(ltrim($line), 0, 4));
            if ('EHLO' === $command || 'HELO' === $command) {
                fwrite($client, "250 localhost\r\n");
            } elseif ('RCPT' === $command && preg_match('/<([^>]+)>/', $line, $matches)) {
                $rcpt[] = $matches[1];
                fwrite($client, "250 OK\r\n");
            } elseif ('DATA' === $command) {
                $readingData = true;
                fwrite($client, "354 Go ahead\r\n");
            } elseif ('QUIT' === $command) {
                fwrite($client, "221 Bye\r\n");
                break;
            } else {
                fwrite($client, "250 OK\r\n");
            }
        }

        return ['rcpt_to' => $rcpt, 'data' => $data, 'completed' => $completed];
    }
}
