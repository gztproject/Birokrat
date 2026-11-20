<?php

namespace App\Tests\Web;

use App\Entity\IncomingInvoice\IncomingInvoice;
use App\Entity\User\User;
use App\Tests\Support\PublicWebServer;
use App\Tests\Support\SmokeFixtures;
use App\Tests\Support\UploadAuthorizationFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Uploaded invoice scans and signature images are written under the public
 * directory and linked from signed-in pages. These tests request those
 * published addresses through the Apache document root in public/.
 *
 * They fail while those files are returned without a session, and pass once
 * a signed-in user who is allowed to see that invoice or profile is required
 * and the file is sent as an attachment.
 */
class PublicUploadAuthorizationTest extends WebTestCase
{
    private static ?PublicWebServer $server = null;

    /** @var array<string, mixed>|null */
    private static ?array $documents = null;

    public static function setUpBeforeClass(): void
    {
        $projectDir = dirname(__DIR__, 2);
        self::$server = PublicWebServer::start($projectDir.'/public');
    }

    public static function tearDownAfterClass(): void
    {
        self::$server?->stop();
        self::$server = null;
        self::$documents = null;
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (self::$documents === null) {
            self::$documents = $this->seedDocuments();
        }
    }

    public function testSignedInPagesPublishTheStoredFilenames(): void
    {
        $documents = $this->documents();

        $scanPage = $this->server()->request('GET', $documents['scanUrlPath'], $documents['adminJar']);
        $signaturePage = $this->server()->request('GET', $documents['signatureUrlPath'], $documents['ownerJar']);

        $this->assertSame(200, $scanPage['status'], $this->excerpt($scanPage['body']));
        $this->assertStringContainsString($documents['scanHref'], $scanPage['body']);
        $this->assertSame(200, $signaturePage['status'], $this->excerpt($signaturePage['body']));
        $this->assertStringContainsString($documents['signatureHref'], $signaturePage['body']);
        $this->assertStringContainsString($documents['cloneHref'], $this->server()->request(
            'GET',
            $documents['cloneUrlPath'],
            $documents['adminJar'],
        )['body']);
    }

    public function testAnonymousRequestForPublishedScanDoesNotReturnTheFile(): void
    {
        $documents = $this->documents();
        $response = $this->server()->request('GET', $documents['scanHref']);

        $this->assertNotSame($documents['scanBytes'], $response['body'], 'Anonymous response status '.$response['status']);
    }

    public function testScanRemainsUnavailableAfterLogout(): void
    {
        $documents = $this->documents();
        $jar = $this->cookieJar();
        $this->login($jar, $documents['adminUsername'], SmokeFixtures::ADMIN_PASSWORD);
        $this->server()->request('GET', $documents['scanUrlPath'], $jar);
        $logout = $this->server()->request('GET', '/logout', $jar);
        $this->assertContains($logout['status'], [200, 302], $this->excerpt($logout['body']));

        $response = $this->server()->request('GET', $documents['scanHref'], $jar);

        $this->assertNotSame($documents['scanBytes'], $response['body'], 'Post-logout response status '.$response['status']);
    }

    public function testAnonymousRequestForPublishedSignatureDoesNotReturnTheFile(): void
    {
        $documents = $this->documents();
        $response = $this->server()->request('GET', $documents['signatureHref']);

        $this->assertNotSame($documents['signatureBytes'], $response['body'], 'Anonymous response status '.$response['status']);
    }

    public function testSignatureRemainsUnavailableAfterLogout(): void
    {
        $documents = $this->documents();
        $jar = $this->cookieJar();
        $this->login($jar, $documents['ownerUsername'], SmokeFixtures::USER_PASSWORD);
        $this->server()->request('GET', '/logout', $jar);

        $response = $this->server()->request('GET', $documents['signatureHref'], $jar);

        $this->assertNotSame($documents['signatureBytes'], $response['body'], 'Post-logout response status '.$response['status']);
    }

    public function testUserWhoCannotSeeTheProfileDoesNotReceiveTheSignature(): void
    {
        $documents = $this->documents();
        $response = $this->server()->request('GET', $documents['signatureHref'], $documents['otherJar']);

        $this->assertNotSame($documents['signatureBytes'], $response['body'], 'Other-user response status '.$response['status']);
    }

    public function testPublicUploadPathsDoNotReturnStoredBytes(): void
    {
        $documents = $this->documents();
        $paths = [
            '/uploads/incoming-invoices/'.$documents['scanFilename'],
            '/uploads/incoming-invoices/'.$documents['cloneFilename'],
            '/uploads/signatures/'.$documents['signatureFilename'],
        ];

        foreach ($paths as $path) {
            $anonymous = $this->server()->request('GET', $path);
            $this->assertNotSame($documents['bytesByPath'][$path], $anonymous['body'], 'Anonymous '.$path.' status '.$anonymous['status']);

            $authenticated = $this->server()->request('GET', $path, $documents['adminJar']);
            $this->assertNotSame($documents['bytesByPath'][$path], $authenticated['body'], 'Authenticated '.$path.' status '.$authenticated['status']);
        }
    }

    public function testReplacingTheFilenameDoesNotReturnTheOtherScan(): void
    {
        $documents = $this->documents();
        $swapped = str_replace($documents['scanFilename'], $documents['cloneFilename'], $documents['scanHref']);
        $swappedUrl = $swapped === $documents['scanHref']
            ? '/uploads/incoming-invoices/'.$documents['cloneFilename']
            : $swapped;

        $response = $this->server()->request('GET', $swappedUrl, $documents['adminJar']);
        $this->assertNotSame($documents['cloneBytes'], $response['body'], 'Swapped-path status '.$response['status']);

        $separator = str_contains($documents['scanHref'], '?') ? '&' : '?';
        $withOtherName = $documents['scanHref'].$separator.'file='.rawurlencode($documents['cloneFilename']);
        $queryResponse = $this->server()->request('GET', $withOtherName, $documents['adminJar']);
        $this->assertNotSame($documents['cloneBytes'], $queryResponse['body'], 'Query-swap status '.$queryResponse['status']);
    }

    public function testReplacingTheSignatureFilenameDoesNotReturnTheOtherSignature(): void
    {
        $documents = $this->documents();
        $edited = $this->replaceSignature();
        $swapped = str_replace($documents['signatureFilename'], $edited['filename'], $documents['signatureHref']);
        $swappedUrl = $swapped === $documents['signatureHref']
            ? '/uploads/signatures/'.$edited['filename']
            : $swapped;

        $response = $this->server()->request('GET', $swappedUrl, $documents['ownerJar']);
        $this->assertNotSame($edited['bytes'], $response['body'], 'Swapped signature status '.$response['status']);

        $asOtherOwner = $this->server()->request('GET', $edited['href'], $documents['ownerJar']);
        $this->assertNotSame($edited['bytes'], $asOtherOwner['body'], 'Other profile status '.$asOtherOwner['status']);
    }

    public function testCloneScanIsNotReturnedWithoutASession(): void
    {
        $documents = $this->documents();
        $response = $this->server()->request('GET', $documents['cloneHref']);

        $this->assertNotSame($documents['cloneBytes'], $response['body'], 'Anonymous clone status '.$response['status']);
    }

    public function testEditedSignatureIsNotReturnedWithoutASession(): void
    {
        $edited = $this->replaceSignature();
        $response = $this->server()->request('GET', $edited['href']);

        $this->assertNotSame($edited['bytes'], $response['body'], 'Anonymous edited signature status '.$response['status']);
    }

    public function testAllowedUserReceivesTheScanAsAnAttachment(): void
    {
        $documents = $this->documents();
        $response = $this->server()->request('GET', $documents['scanHref'], $documents['adminJar']);

        $this->assertSame(200, $response['status'], $this->excerpt($response['body']));
        $this->assertSame($documents['scanBytes'], $response['body']);
        $this->assertStringContainsStringIgnoringCase('attachment', $response['headers']['content-disposition'] ?? '');
    }

    public function testAllowedUserReceivesOwnSignatureAsAnAttachment(): void
    {
        $documents = $this->documents();
        $response = $this->server()->request('GET', $documents['signatureHref'], $documents['ownerJar']);

        $this->assertSame(200, $response['status'], $this->excerpt($response['body']));
        $this->assertSame($documents['signatureBytes'], $response['body']);
        $this->assertStringContainsStringIgnoringCase('attachment', $response['headers']['content-disposition'] ?? '');
    }

    public function testAllowedUserReceivesTheCloneScanAsAnAttachment(): void
    {
        $documents = $this->documents();
        $response = $this->server()->request('GET', $documents['cloneHref'], $documents['adminJar']);

        $this->assertSame(200, $response['status'], $this->excerpt($response['body']));
        $this->assertSame($documents['cloneBytes'], $response['body']);
        $this->assertStringContainsStringIgnoringCase('attachment', $response['headers']['content-disposition'] ?? '');
    }

    /**
     * @return array<string, mixed>
     */
    private function documents(): array
    {
        if (self::$documents === null) {
            $this->fail('Documents were not seeded.');
        }

        return self::$documents;
    }

    private function server(): PublicWebServer
    {
        if (self::$server === null) {
            $this->fail('The public web server is not running.');
        }

        return self::$server;
    }

    /**
     * @return array<string, mixed>
     */
    private function seedDocuments(): array
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $suffix = bin2hex(random_bytes(3));
        $adminUsername = 'upload-admin-'.$suffix;
        $otherUsername = 'upload-other-'.$suffix;
        $ownerUsername = 'upload-owner-'.$suffix;

        $fixtures = (new UploadAuthorizationFixtures(
            $em,
            $container->get(UserPasswordHasherInterface::class),
        ))->load($adminUsername, $otherUsername);

        $client->loginUser($fixtures['admin']);

        $ownerPng = $this->png(20, 40, 60);
        $this->submitUser($client, [
            'username' => $ownerUsername,
            'email' => $ownerUsername.'@example.test',
            'firstName' => 'Sign',
            'lastName' => 'Owner',
            'password' => SmokeFixtures::USER_PASSWORD,
        ], $ownerPng);

        $em->clear();
        $owner = $em->getRepository(User::class)->findOneBy(['username' => $ownerUsername]);
        $this->assertInstanceOf(User::class, $owner);
        $signature = $this->storedFile('signatures_directory', (string) $owner->getSignatureFilename());

        $scanPng = $this->png(200, 10, 10);
        $invoiceId = $this->submitIncomingInvoice($client, $fixtures, 'SCAN-'.$suffix, $scanPng);
        $em->clear();
        $invoice = $em->getRepository(IncomingInvoice::class)->find($invoiceId);
        $this->assertInstanceOf(IncomingInvoice::class, $invoice);
        $scan = $this->storedFile('incoming_scans_directory', (string) $invoice->getScanFilename());

        $clonePng = $this->png(10, 180, 20);
        $cloneId = $this->submitClone($client, $fixtures, (string) $invoice->getId(), 'CLONE-'.$suffix, $clonePng);
        $em->clear();
        $clone = $em->getRepository(IncomingInvoice::class)->find($cloneId);
        $this->assertInstanceOf(IncomingInvoice::class, $clone);
        $cloneScan = $this->storedFile('incoming_scans_directory', (string) $clone->getScanFilename());

        $adminJar = $this->cookieJar();
        $ownerJar = $this->cookieJar();
        $otherJar = $this->cookieJar();
        $this->login($adminJar, $adminUsername, SmokeFixtures::ADMIN_PASSWORD);
        $this->login($ownerJar, $ownerUsername, SmokeFixtures::USER_PASSWORD);
        $this->login($otherJar, $otherUsername, SmokeFixtures::USER_PASSWORD);

        $client->request('GET', '/dashboard/incomingInvoice/'.$invoice->getId().'/show');
        $this->assertResponseIsSuccessful();
        $scanHref = $this->publishedAddress((string) $client->getResponse()->getContent(), $scan['filename'], $scan['bytes'], $adminJar, (string) $invoice->getId());

        $client->request('GET', '/dashboard/incomingInvoice/'.$clone->getId().'/show');
        $this->assertResponseIsSuccessful();
        $cloneHref = $this->publishedAddress((string) $client->getResponse()->getContent(), $cloneScan['filename'], $cloneScan['bytes'], $adminJar, (string) $clone->getId());

        $client->request('GET', '/admin/user/'.$owner->getId());
        $this->assertResponseIsSuccessful();
        $signatureHref = $this->publishedAddress((string) $client->getResponse()->getContent(), $signature['filename'], $signature['bytes'], $ownerJar, (string) $owner->getId());

        $liveScan = $this->server()->request('GET', '/dashboard/incomingInvoice/'.$invoice->getId().'/show', $adminJar);
        $this->assertSame(200, $liveScan['status'], $this->excerpt($liveScan['body']));
        $this->assertStringContainsString($scan['filename'], $liveScan['body']);
        $liveSignature = $this->server()->request('GET', '/user/'.$owner->getId(), $ownerJar);
        $this->assertSame(200, $liveSignature['status'], $this->excerpt($liveSignature['body']));
        $this->assertStringContainsString($signature['filename'], $liveSignature['body']);

        return [
            'adminUsername' => $adminUsername,
            'ownerUsername' => $ownerUsername,
            'adminJar' => $adminJar,
            'ownerJar' => $ownerJar,
            'otherJar' => $otherJar,
            'ownerId' => (string) $owner->getId(),
            'scanFilename' => $scan['filename'],
            'scanBytes' => $scan['bytes'],
            'scanHref' => $scanHref,
            'scanUrlPath' => '/dashboard/incomingInvoice/'.$invoice->getId().'/show',
            'cloneFilename' => $cloneScan['filename'],
            'cloneBytes' => $cloneScan['bytes'],
            'cloneHref' => $cloneHref,
            'cloneUrlPath' => '/dashboard/incomingInvoice/'.$clone->getId().'/show',
            'signatureFilename' => $signature['filename'],
            'signatureBytes' => $signature['bytes'],
            'signatureHref' => $signatureHref,
            'signatureUrlPath' => '/user/'.$owner->getId(),
            'bytesByPath' => [
                '/uploads/incoming-invoices/'.$scan['filename'] => $scan['bytes'],
                '/uploads/incoming-invoices/'.$cloneScan['filename'] => $cloneScan['bytes'],
                '/uploads/signatures/'.$signature['filename'] => $signature['bytes'],
            ],
        ];
    }

    /**
     * @param array{admin: User, other: User, organization: mixed, partner: mixed, debit: mixed} $fixtures
     */
    private function submitIncomingInvoice(KernelBrowser $client, array $fixtures, string $number, string $pngPath): string
    {
        $client->request('GET', '/dashboard/incomingInvoice/new');
        $this->assertResponseIsSuccessful();
        $client->request('POST', '/dashboard/incomingInvoice/new', [
            'incoming_invoice' => [
                '_token' => $this->formToken((string) $client->getResponse()->getContent(), 'incoming_invoice'),
                'issuer' => (string) $fixtures['partner']->getId(),
                'recepient' => (string) $fixtures['organization']->getId(),
                'debitKonto' => (string) $fixtures['debit']->getId(),
                'number' => $number,
                'price' => '12.50',
                'dateOfIssue' => '2026-11-01',
                'dueDate' => '2026-11-15',
            ],
        ], [
            'incoming_invoice' => [
                'scan' => new UploadedFile($pngPath, 'supplier-scan.png', 'image/png', null, true),
            ],
        ]);
        $this->assertResponseRedirects(null, null, $this->excerpt((string) $client->getResponse()->getContent()));
        $location = (string) $client->getResponse()->headers->get('Location');
        $this->assertMatchesRegularExpression('#/dashboard/incomingInvoice/([0-9a-fA-F-]{36})/show$#', $location);
        preg_match('#/dashboard/incomingInvoice/([0-9a-fA-F-]{36})/show$#', $location, $matches);

        return $matches[1];
    }

    /**
     * @param array{admin: User, other: User, organization: mixed, partner: mixed, debit: mixed} $fixtures
     */
    private function submitClone(KernelBrowser $client, array $fixtures, string $invoiceId, string $number, string $pngPath): string
    {
        $client->request('GET', '/dashboard/incomingInvoice/'.$invoiceId.'/clone');
        $this->assertResponseIsSuccessful();
        $client->request('POST', '/dashboard/incomingInvoice/'.$invoiceId.'/clone', [
            'incoming_invoice' => [
                '_token' => $this->formToken((string) $client->getResponse()->getContent(), 'incoming_invoice'),
                'issuer' => (string) $fixtures['partner']->getId(),
                'recepient' => (string) $fixtures['organization']->getId(),
                'debitKonto' => (string) $fixtures['debit']->getId(),
                'number' => $number,
                'price' => '12.50',
                'dateOfIssue' => '2026-11-01',
                'dueDate' => '2026-11-15',
            ],
        ], [
            'incoming_invoice' => [
                'scan' => new UploadedFile($pngPath, 'cloned-scan.png', 'image/png', null, true),
            ],
        ]);
        $this->assertResponseRedirects(null, null, $this->excerpt((string) $client->getResponse()->getContent()));
        $location = (string) $client->getResponse()->headers->get('Location');
        preg_match('#/dashboard/incomingInvoice/([0-9a-fA-F-]{36})/show$#', $location, $matches);
        $this->assertArrayHasKey(1, $matches, $location);

        return $matches[1];
    }

    /**
     * @param array{username: string, email: string, firstName: string, lastName: string, password: string} $fields
     */
    private function submitUser(KernelBrowser $client, array $fields, string $pngPath): void
    {
        $crawler = $client->request('GET', '/admin/user/new');
        $this->assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="user"]')->form();
        $form['user[username]'] = $fields['username'];
        $form['user[email]'] = $fields['email'];
        $form['user[firstName]'] = $fields['firstName'];
        $form['user[lastName]'] = $fields['lastName'];
        $form['user[password][first]'] = $fields['password'];
        $form['user[password][second]'] = $fields['password'];
        $form['user[signature]']->upload(new UploadedFile($pngPath, 'signature.png', 'image/png', null, true));
        $client->submit($form);
        $this->assertResponseRedirects(null, null, $this->excerpt((string) $client->getResponse()->getContent()));
    }

    /**
     * @return array{filename: string, bytes: string, href: string}
     */
    private function replaceSignature(): array
    {
        if (isset(self::$documents['editedSignature'])) {
            return self::$documents['editedSignature'];
        }

        $documents = $this->documents();
        $client = static::createClient();
        $client->loginUser($this->user($documents['adminUsername']));
        $editedUsername = 'upload-edited-'.bin2hex(random_bytes(3));
        $this->submitUser($client, [
            'username' => $editedUsername,
            'email' => $editedUsername.'@example.test',
            'firstName' => 'Edited',
            'lastName' => 'Signer',
            'password' => SmokeFixtures::USER_PASSWORD,
        ], $this->png(5, 5, 5));
        $editedUser = $this->user($editedUsername);

        $png = $this->png(30, 90, 200);
        $crawler = $client->request('GET', '/admin/user/'.$editedUser->getId().'/edit');
        $this->assertResponseIsSuccessful();
        $form = $crawler->filter('form[name="user"]')->form();
        $form['user[signature]']->upload(new UploadedFile($png, 'replacement-signature.png', 'image/png', null, true));
        $client->submit($form);
        $this->assertResponseRedirects(null, null, $this->excerpt((string) $client->getResponse()->getContent()));

        $editedUser = $this->user($editedUsername);
        $stored = $this->storedFile('signatures_directory', (string) $editedUser->getSignatureFilename());
        $this->assertNotSame($documents['signatureFilename'], $stored['filename']);

        $client->request('GET', '/admin/user/'.$editedUser->getId());
        $this->assertResponseIsSuccessful();
        $href = $this->publishedAddress(
            (string) $client->getResponse()->getContent(),
            $stored['filename'],
            $stored['bytes'],
            $documents['adminJar'],
            (string) $editedUser->getId(),
        );

        self::$documents['editedSignature'] = [
            'filename' => $stored['filename'],
            'bytes' => $stored['bytes'],
            'href' => $href,
        ];

        return self::$documents['editedSignature'];
    }

    private function user(string $username): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $user = $em->getRepository(User::class)->findOneBy(['username' => $username]);
        $this->assertInstanceOf(User::class, $user);

        return $user;
    }

    /**
     * @return array{filename: string, bytes: string}
     */
    private function storedFile(string $parameter, string $filename): array
    {
        $this->assertNotSame('', $filename);
        $directory = (string) static::getContainer()->getParameter($parameter);
        $path = $directory.'/'.$filename;
        $this->assertFileExists($path);
        $bytes = file_get_contents($path);
        $this->assertIsString($bytes);
        $this->assertNotSame('', $bytes);

        return ['filename' => $filename, 'bytes' => $bytes];
    }

    private function formToken(string $html, string $formName): string
    {
        preg_match('/name="'.preg_quote($formName, '/').'\[_token\]"[^>]*value="([^"]+)"/', $html, $matches);
        $this->assertArrayHasKey(1, $matches, 'Form '.$formName.' did not include a CSRF token.');

        return html_entity_decode($matches[1]);
    }

    private function publishedAddress(string $html, string $filename, string $bytes, string $jar, string $id): string
    {
        preg_match_all('#(?:href|src)="([^"]+)"#', $html, $matches);
        $candidates = [];
        foreach ($matches[1] ?? [] as $url) {
            $url = html_entity_decode($url);
            $namesThisFile = str_contains($url, $filename);
            $namesThisRecord = str_contains($url, $id);
            if ($namesThisFile || $namesThisRecord) {
                $candidates[] = $url;
            }
        }

        usort($candidates, static function (string $left, string $right) use ($filename): int {
            return (str_contains($right, $filename) <=> str_contains($left, $filename));
        });

        foreach (array_unique($candidates) as $url) {
            $response = $this->server()->request('GET', $url, $jar);
            if ($response['body'] === $bytes) {
                return $url;
            }
        }

        $this->fail('Signed-in page did not publish an address that returns the stored file. Candidates: '.implode(', ', $candidates));
    }

    private function login(string $jar, string $username, string $password): void
    {
        $page = $this->server()->request('GET', '/login', $jar);
        $this->assertSame(200, $page['status'], $this->excerpt($page['body']));
        preg_match('/name="_csrf_token" value="([^"]+)"/', $page['body'], $matches);
        $this->assertArrayHasKey(1, $matches, 'Login form did not include a CSRF token.');

        $submitted = $this->server()->request('POST', '/login', $jar, [
            '_username' => $username,
            '_password' => $password,
            '_csrf_token' => html_entity_decode($matches[1]),
        ]);
        $this->assertContains($submitted['status'], [302, 303], $this->excerpt($submitted['body']));
    }

    private function png(int $red, int $green, int $blue): string
    {
        $image = imagecreatetruecolor(16, 16);
        $color = imagecolorallocate($image, $red, $green, $blue);
        imagefilledrectangle($image, 0, 0, 15, 15, $color);
        $path = tempnam(sys_get_temp_dir(), 'upload');
        if ($path === false) {
            $this->fail('Could not create a temporary image.');
        }
        $png = $path.'.png';
        rename($path, $png);
        imagepng($image, $png);
        imagedestroy($image);

        return $png;
    }

    private function cookieJar(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'jar');
        if ($path === false) {
            $this->fail('Could not create a cookie jar.');
        }

        return $path;
    }

    private function excerpt(string $body): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '');

        return substr($text, 0, 500);
    }
}
