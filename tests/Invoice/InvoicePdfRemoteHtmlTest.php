<?php

namespace App\Tests\Invoice;

use App\Entity\Geography\CreateAddressCommand;
use App\Entity\Geography\CreateCountryCommand;
use App\Entity\Geography\CreatePostCommand;
use App\Entity\Invoice\CreateInvoiceCommand;
use App\Entity\Invoice\Invoice;
use App\Entity\Invoice\InvoicePdfFactory;
use App\Entity\Organization\CreateOrganizationCommand;
use App\Entity\Organization\CreatePartnerCommand;
use App\Entity\User\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Qipsius\TCPDFBundle\Controller\TCPDFController;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Invoice PDFs must not let a company name or address make the server
 * request an address while the PDF is built.
 *
 * The download, print, and email paths all render through InvoicePdfFactory.
 * Street line 1, street line 2, and the post name are copied into the same
 * HTML block as the company name. A stylesheet link in that HTML is a second
 * fetch from the same sink.
 */
class InvoicePdfRemoteHtmlTest extends TestCase
{
    private static string $logFile;
    private static int $port;
    private static int $tlsPort;
    private static $server;
    private static $tlsServer;
    private static string $serverStdout;
    private static string $serverStderr;

    /** @var array<string, int> */
    private array $plainImageCounts = [];

    public static function setUpBeforeClass(): void
    {
        self::$logFile = tempnam(sys_get_temp_dir(), 'pdf-probe-');
        self::$port = self::reservePort();
        self::$tlsPort = self::reservePort();
        self::$serverStdout = tempnam(sys_get_temp_dir(), 'pdf-probe-out-');
        self::$serverStderr = tempnam(sys_get_temp_dir(), 'pdf-probe-err-');

        $env = [];
        foreach ($_SERVER as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $env[$key] = $value;
            }
        }
        $env['PROBE_LOG'] = self::$logFile;

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', self::$serverStdout, 'w'],
            2 => ['file', self::$serverStderr, 'w'],
        ];
        self::$server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:'.self::$port, dirname(__FILE__).'/pdf_probe_router.php'],
            $descriptors,
            $pipes,
            null,
            $env
        );
        if (!is_resource(self::$server)) {
            self::fail('Could not start the local probe server.');
        }
        fclose($pipes[0]);

        $deadline = microtime(true) + 5;
        $ready = false;
        while (microtime(true) < $deadline) {
            $socket = @fsockopen('127.0.0.1', self::$port, $errno, $error, 0.2);
            if (is_resource($socket)) {
                fclose($socket);
                $ready = true;
                break;
            }
            usleep(50000);
        }
        if (!$ready) {
            self::fail('Probe server did not accept connections: '.file_get_contents(self::$serverStderr));
        }

        self::$tlsServer = proc_open(
            [PHP_BINARY, dirname(__FILE__).'/pdf_tcp_probe.php', self::$logFile, (string) self::$tlsPort],
            $descriptors,
            $tlsPipes,
            null,
            $env
        );
        if (!is_resource(self::$tlsServer)) {
            self::fail('Could not start the TLS probe.');
        }
        fclose($tlsPipes[0]);
        $deadline = microtime(true) + 5;
        $ready = false;
        while (microtime(true) < $deadline) {
            $socket = @fsockopen('127.0.0.1', self::$tlsPort, $errno, $error, 0.2);
            if (is_resource($socket)) {
                fclose($socket);
                $ready = true;
                break;
            }
            usleep(50000);
        }
        if (!$ready) {
            self::fail('TLS probe did not accept connections.');
        }
        file_put_contents(self::$logFile, '');
    }

    public static function tearDownAfterClass(): void
    {
        foreach ([self::$server, self::$tlsServer] as $process) {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
        }
        @unlink(self::$logFile);
        @unlink(self::$serverStdout);
        @unlink(self::$serverStderr);
    }

    protected function setUp(): void
    {
        file_put_contents(self::$logFile, '');
    }

    public function testPlainCompanyDoesNotRequestAnything(): void
    {
        $pdf = $this->render($this->invoice('Acme d.o.o.', 'Cesta 1', null, 'Ljubljana'));

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame([], $this->probeRequests());
    }

    #[DataProvider('renderedCopies')]
    public function testCompanyNameImageIsNotRequested(string $destination, bool $print): void
    {
        $token = 'name-'.$destination.($print ? '-print' : '');
        $pdf = $this->render(
            $this->invoice($this->imageTag($token), 'Cesta 1', null, 'Ljubljana'),
            $destination,
            $print
        );

        $this->assertQuietPdf($pdf, $destination, $print, 'Rendering the company name must not request the image address.');
    }

    public function testStreetLineImageIsNotRequested(): void
    {
        $pdf = $this->render($this->invoice('Acme d.o.o.', $this->imageTag('line1'), null, 'Ljubljana'));

        $this->assertQuietPdf($pdf, 'S', false, 'Rendering the street must not request the image address.');
    }

    public function testSecondStreetLineImageIsNotRequested(): void
    {
        $pdf = $this->render($this->invoice('Acme d.o.o.', 'Cesta 1', $this->imageTag('line2'), 'Ljubljana'));

        $this->assertQuietPdf($pdf, 'S', false, 'Rendering the second street line must not request the image address.');
    }

    public function testPostNameImageIsNotRequested(): void
    {
        $pdf = $this->render($this->invoice('Acme d.o.o.', 'Cesta 1', null, $this->imageTag('post')));

        $this->assertQuietPdf($pdf, 'S', false, 'Rendering the post name copied onto the invoice must not request the image address.');
    }

    public function testCompanyNameStylesheetLinkIsNotRequested(): void
    {
        $url = $this->probeUrl('sheet');
        $name = '<link type="text/css" href="'.$url.'">';
        $pdf = $this->render($this->invoice($name, 'Cesta 1', null, 'Ljubljana'));

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame([], $this->probeRequests(), 'A stylesheet address in the company name must not be requested.');
    }

    public function testHttpsCompanyNameImageIsNotRequested(): void
    {
        $url = 'https://127.0.0.1:'.self::$tlsPort.'/https-name.png';
        $pdf = $this->render($this->invoice('<img src="'.$url.'">', 'Cesta 1', null, 'Ljubljana'));

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame([], $this->probeRequests(), 'An https image address in the company name must not be requested.');
    }

    public function testRecipientNameImageIsNotRequested(): void
    {
        $pdf = $this->render($this->invoice('Acme d.o.o.', 'Cesta 1', null, 'Ljubljana', $this->imageTag('recipient')));

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame([], $this->probeRequests());
    }

    public static function renderedCopies(): array
    {
        return [
            'download' => ['S', false],
            'print' => ['S', true],
            'email file' => ['F', false],
        ];
    }

    private function render(Invoice $invoice, string $destination = 'S', bool $print = false): string
    {
        $path = null;
        if ($destination === 'F') {
            $path = sys_get_temp_dir().'/invoice-pdf-'.bin2hex(random_bytes(4)).'/';
            mkdir($path, 0700, true);
        }

        $pdf = InvoicePdfFactory::factory(
            $invoice,
            new class implements TranslatorInterface {
                public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
                {
                    return $id;
                }

                public function getLocale(): string
                {
                    return 'en';
                }
            },
            new TCPDFController('TCPDF'),
            $destination,
            $path,
            $print
        )->generate();

        if ($destination === 'F') {
            $written = glob($path.'*.pdf') ?: [];
            $pdf = $written === [] ? (string) $pdf : (string) file_get_contents($written[0]);
            foreach ($written as $file) {
                @unlink($file);
            }
            @rmdir($path);
        }

        return (string) $pdf;
    }

    private function invoice(
        string $organizationName,
        string $line1,
        ?string $line2,
        string $postName,
        string $partnerName = 'Partner d.o.o.'
    ): Invoice {
        $user = $this->clerk();

        $countryCommand = new CreateCountryCommand();
        $countryCommand->name = 'Slovenia';
        $countryCommand->nameInt = 'Slovenia';
        $countryCommand->A2 = 'SI';
        $countryCommand->A3 = 'SVN';
        $countryCommand->N3 = 705;
        $country = $user->createCountry($countryCommand);

        $postCommand = new CreatePostCommand();
        $postCommand->code = '1000';
        $postCommand->codeInternational = 'SI-1000';
        $postCommand->name = $postName;
        $post = $country->createPost($postCommand, $user);

        $issuerAddress = new CreateAddressCommand();
        $issuerAddress->line1 = $line1;
        $issuerAddress->line2 = $line2;
        $issuerAddress = $post->createAddress($issuerAddress, $user);

        $partnerAddress = new CreateAddressCommand();
        $partnerAddress->line1 = 'Trg 2';
        $partnerAddress->line2 = null;
        $partnerAddress = $post->createAddress($partnerAddress, $user);

        $organizationCommand = new CreateOrganizationCommand();
        $organizationCommand->code = 'ORG1';
        $organizationCommand->name = $organizationName;
        $organizationCommand->taxNumber = '12345678';
        $organizationCommand->taxable = false;
        $organizationCommand->address = $issuerAddress;
        $organizationCommand->www = 'example.invalid';
        $organizationCommand->email = 'billing@example.invalid';
        $organizationCommand->mobile = '040000000';
        $organizationCommand->accountNumber = 'SI56000000000000000';
        $organizationCommand->bic = 'LJBASI2X';
        $organization = $user->createOrganization($organizationCommand);

        $partnerCommand = new CreatePartnerCommand();
        $partnerCommand->code = 'PAR1';
        $partnerCommand->name = $partnerName;
        $partnerCommand->taxNumber = '87654321';
        $partnerCommand->taxable = false;
        $partnerCommand->address = $partnerAddress;
        $partnerCommand->www = 'example.invalid';
        $partnerCommand->email = 'ap@example.invalid';
        $partnerCommand->mobile = '041000000';
        $partnerCommand->accountNumber = 'SI56000000000000001';
        $partnerCommand->bic = 'LJBASI2X';
        $partnerCommand->isClient = true;
        $partnerCommand->isSupplier = false;
        $partner = $user->createPartner($partnerCommand);

        $invoiceCommand = new CreateInvoiceCommand();
        $invoiceCommand->dateOfIssue = new \DateTime('2024-01-15');
        $invoiceCommand->dateServiceRenderedFrom = new \DateTime('2024-01-01');
        $invoiceCommand->dateServiceRenderedTo = new \DateTime('2024-01-14');
        $invoiceCommand->dueDate = new \DateTime('2024-01-25');
        $invoiceCommand->discount = 0;
        $invoiceCommand->number = '2019-0001';
        $invoiceCommand->issuer = $organization;
        $invoiceCommand->recepient = $partner;

        return $user->createInvoice($invoiceCommand);
    }

    private function clerk(): User
    {
        $user = (new \ReflectionClass(User::class))->newInstanceWithoutConstructor();
        $this->setPrivate($user, 'firstName', 'Ada');
        $this->setPrivate($user, 'lastName', 'Clerk');
        $this->setPrivate($user, 'signatureFilename', '');

        return $user;
    }

    private function setPrivate(object $object, string $property, mixed $value): void
    {
        $reflection = new \ReflectionProperty($object, $property);
        $reflection->setValue($object, $value);
    }

    private function imageTag(string $token): string
    {
        return '<img src="'.$this->probeUrl($token).'">';
    }

    private function probeUrl(string $token): string
    {
        return 'http://127.0.0.1:'.self::$port.'/'.$token.'.png';
    }

    /**
     * @return list<string>
     */
    private function probeRequests(): array
    {
        $raw = (string) file_get_contents(self::$logFile);
        $lines = array_values(array_filter(explode("\n", $raw), static fn (string $line): bool => $line !== ''));

        return $lines;
    }

    private function assertQuietPdf(string $pdf, string $destination, bool $print, string $message): void
    {
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame([], $this->probeRequests(), $message);
        $this->assertSame(
            $this->plainImageCount($destination, $print),
            $this->imageObjectCount($pdf),
            'The PDF must not gain an image from the company name or address.'
        );
    }

    private function plainImageCount(string $destination, bool $print): int
    {
        $key = $destination.(int) $print;
        if (!isset($this->plainImageCounts[$key])) {
            $pdf = $this->render($this->invoice('Acme d.o.o.', 'Cesta 1', null, 'Ljubljana'), $destination, $print);
            $this->plainImageCounts[$key] = $this->imageObjectCount($pdf);
        }

        return $this->plainImageCounts[$key];
    }

    private function imageObjectCount(string $pdf): int
    {
        return preg_match_all('#/Subtype\s*/Image\b#', $pdf) ?: 0;
    }

    private static function reservePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) {
            self::fail('Could not reserve a port: '.$error);
        }
        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr((string) $name, (int) strrpos((string) $name, ':') + 1);
    }
}
