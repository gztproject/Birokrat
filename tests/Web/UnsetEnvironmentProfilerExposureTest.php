<?php

namespace App\Tests\Web;

use PHPUnit\Framework\TestCase;

/**
 * The shipped .env leaves APP_ENV and APP_DEBUG unset. These tests boot the
 * real front controller and console runtime with those variables absent and
 * require that an anonymous client cannot read profiler data for a signed-in
 * accounting session.
 *
 * They fail while that default selects the dev profiler. Run:
 * php vendor/bin/phpunit tests/Web/UnsetEnvironmentProfilerExposureTest.php --no-coverage
 */
final class UnsetEnvironmentProfilerExposureTest extends TestCase
{
    private const PLACEHOLDER_APP_SECRET = 'placeholder-app-secret';

    private static string $projectDir;
    private static string $databaseUrl;
    private static array $fixture;
    /** @var resource|null */
    private static $serverProcess;
    private static int $port;
    private static string $serverLog;

    public static function setUpBeforeClass(): void
    {
        self::$projectDir = dirname(__DIR__, 2);
        $databasePath = tempnam(sys_get_temp_dir(), 'birokrat-profiler-');
        if ($databasePath === false) {
            self::fail('Could not allocate a temporary database file.');
        }
        unlink($databasePath);
        self::$databaseUrl = 'sqlite:///'.$databasePath;
        self::writeAssetStubs();
        self::runConsole(['doctrine:schema:create', '--no-interaction']);
        self::$fixture = self::seedFixture();
        self::startServer();
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$serverProcess)) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
        }
    }

    public function testUnsetEnvironmentDoesNotBootTheDevProfiler(): void
    {
        $about = self::runConsole(['about']);

        $this->assertFalse(
            preg_match('/Environment\s+dev\b/', $about) === 1,
            'Unset APP_ENV booted the dev environment.'
        );
        $this->assertFalse(
            preg_match('/Debug\s+true\b/', $about) === 1,
            'Unset APP_DEBUG enabled debugging.'
        );
        $this->assertSame('prod', self::$fixture['app_env']);
        $this->assertFalse(self::$fixture['app_debug']);
    }

    public function testAnonymousClientCannotReadSignedInProfilerData(): void
    {
        $victim = $this->signInAndOpenAccountingPages();
        $sessionId = $victim['session_id'];
        $this->assertNotSame('', $sessionId);

        $anonymousBodies = $this->anonymousProfilerBodies($victim['tokens']);
        $combined = implode("\n", $anonymousBodies);
        $plain = strtolower(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($combined), ENT_QUOTES | ENT_HTML5)));
        $leakedSession = $this->sessionIdAppearingIn($combined);
        $replayShow = $leakedSession === null
            ? ['status' => 0, 'body' => '', 'headers' => '']
            : $this->request('GET', '/dashboard/partner/'.self::$fixture['partner_id'], [
                'Cookie: PHPSESSID='.$leakedSession,
            ]);
        $replayList = $leakedSession === null
            ? ['status' => 0, 'body' => '', 'headers' => '']
            : $this->request('GET', '/dashboard/partner', [
                'Cookie: PHPSESSID='.$leakedSession,
            ]);
        $replayInvoices = $leakedSession === null
            ? ['status' => 0, 'body' => '', 'headers' => '']
            : $this->request('GET', '/dashboard/invoice', [
                'Cookie: PHPSESSID='.$leakedSession,
            ]);

        $phpinfo = $this->request('GET', '/_profiler/phpinfo');
        $toolbar = $this->request('GET', '/_wdt/styles');
        $open = $this->request('GET', '/_profiler/open?file=config/packages/security.yaml&line=1');
        $font = $this->request('GET', '/_profiler/font/JetBrainsMono.woff2');

        $this->assertFalse(
            str_contains($combined, $sessionId),
            'An anonymous profiler response included the signed-in session cookie.'
        );
        $this->assertFalse(
            $victim['tokens']['partner'] !== '' && in_array($victim['tokens']['partner'], $this->tokensLinkedFrom($combined), true),
            'The profiler index listed the signed-in partner request.'
        );
        $this->assertFalse(str_contains($replayList['body'], self::$fixture['tax_number']), 'Replaying the profiler session cookie returned the partner tax number.');
        $this->assertFalse(str_contains($replayShow['body'], self::$fixture['account_number']), 'Replaying the profiler session cookie returned the partner account number.');
        $this->assertFalse(str_contains($replayInvoices['body'], self::$fixture['partner_short_name']), 'Replaying the profiler session cookie returned the invoice list.');
        $this->assertFalse(str_contains($plain, 'from partner'), 'An anonymous profiler response included partner queries.');
        $this->assertFalse(str_contains($plain, 'from invoice'), 'An anonymous profiler response included invoice queries.');

        $this->assertNotSame(200, $phpinfo['status'], 'Profiler phpinfo must not be anonymously reachable.');
        $this->assertNotSame(200, $toolbar['status'], 'Profiler toolbar assets must not be anonymously reachable.');
        $this->assertFalse(str_contains($open['body'], 'security: false'), 'Anonymous profiler open action returned application source.');
        $this->assertNotSame(200, $font['status'], 'Profiler font must not be anonymously reachable.');
    }

    /**
     * @return array{session_id: string, tokens: array<string, string>}
     */
    private function signInAndOpenAccountingPages(): array
    {
        $login = $this->request('GET', '/login');
        $sessionId = $this->sessionIdFrom($login['headers']);
        $csrf = $this->inputValue($login['body'], '_csrf_token');
        $this->assertNotSame('', $sessionId, 'Login did not start a session. Body starts: '.substr($login['body'], 0, 400));
        $this->assertNotSame('', $csrf, 'Login form did not render a CSRF token. Status '.$login['status']);

        $submitted = $this->request('POST', '/login', [
            'Cookie: PHPSESSID='.$sessionId,
            'Content-Type: application/x-www-form-urlencoded',
        ], http_build_query([
            '_username' => self::$fixture['username'],
            '_password' => self::$fixture['password'],
            '_csrf_token' => $csrf,
        ]));
        $sessionId = $this->sessionIdFrom($submitted['headers']) ?: $sessionId;
        $this->assertContains($submitted['status'], [302, 303], 'Login did not redirect. Status '.$submitted['status'].' body '.substr($submitted['body'], 0, 300));

        $cookie = ['Cookie: PHPSESSID='.$sessionId];
        $partnerList = $this->request('GET', '/dashboard/partner', $cookie);
        $partnerShow = $this->request('GET', '/dashboard/partner/'.self::$fixture['partner_id'], $cookie);
        $invoices = $this->request('GET', '/dashboard/invoice', $cookie);

        $this->assertSame(200, $partnerList['status'], substr($partnerList['body'], 0, 400));
        $this->assertStringContainsString(self::$fixture['tax_number'], $partnerList['body']);
        $this->assertSame(200, $partnerShow['status'], substr($partnerShow['body'], 0, 400));
        $this->assertStringContainsString(self::$fixture['account_number'], $partnerShow['body']);
        $this->assertSame(200, $invoices['status'], substr($invoices['body'], 0, 400));
        $this->assertStringContainsString(self::$fixture['partner_short_name'], $invoices['body']);

        return [
            'session_id' => $sessionId,
            'tokens' => [
                'partner' => $this->headerValue($partnerList['headers'], 'X-Debug-Token'),
                'partner_show' => $this->headerValue($partnerShow['headers'], 'X-Debug-Token'),
                'invoice' => $this->headerValue($invoices['headers'], 'X-Debug-Token'),
            ],
        ];
    }

    /**
     * @param array<string, string> $victimTokens
     * @return list<string>
     */
    private function anonymousProfilerBodies(array $victimTokens): array
    {
        $bodies = [];
        $paths = [
            '/_profiler/',
            '/_profiler/search?limit=10',
            '/_profiler/search?url=/dashboard/partner&limit=10',
            '/_profiler/search?url=/dashboard/invoice&limit=10',
            '/_profiler/latest?panel=request',
        ];
        foreach ($victimTokens as $token) {
            if ($token !== '') {
                $paths[] = '/_profiler/'.$token.'?panel=request';
                $paths[] = '/_profiler/'.$token.'?panel=db';
                $paths[] = '/_profiler/'.$token.'/router';
                $paths[] = '/_profiler/'.$token.'/exception';
                $paths[] = '/_wdt/'.$token;
            }
        }

        foreach ($paths as $path) {
            $response = $this->follow($path);
            $bodies[] = $response['body'];
            foreach ($this->tokensLinkedFrom($response['body']) as $token) {
                foreach (['request', 'db'] as $panel) {
                    $panelResponse = $this->request('GET', '/_profiler/'.$token.'?panel='.$panel);
                    $bodies[] = $panelResponse['body'];
                }
            }
        }

        return $bodies;
    }

    /**
     * @param list<string> $headers
     */
    private function request(string $method, string $path, array $headers = [], ?string $body = null): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $body ?? '',
                'ignore_errors' => true,
                'follow_location' => 0,
                'timeout' => 30,
            ],
        ]);
        $url = 'http://127.0.0.1:'.self::$port.$path;
        $responseBody = file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];

        return [
            'status' => $this->statusFrom($responseHeaders),
            'headers' => implode("\n", $responseHeaders),
            'body' => $responseBody === false ? '' : $responseBody,
        ];
    }

    private function follow(string $path): array
    {
        $combined = '';
        $status = 0;
        $headers = '';
        for ($hop = 0; $hop < 5; ++$hop) {
            $response = $this->request('GET', $path);
            $status = $response['status'];
            $headers = $response['headers'];
            $combined .= $response['body'];
            if (!in_array($status, [301, 302, 303, 307, 308], true)) {
                break;
            }
            $location = $this->headerValue($headers, 'Location');
            if ($location === '') {
                break;
            }
            $parts = parse_url($location);
            $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        return ['status' => $status, 'headers' => $headers, 'body' => $combined];
    }

    private function statusFrom(array $headers): int
    {
        if ($headers === [] || !preg_match('/\s(\d{3})\s/', $headers[0], $match)) {
            return 0;
        }

        return (int) $match[1];
    }

    private function headerValue(string $headers, string $name): string
    {
        if (!preg_match('/^'.preg_quote($name, '/').':\s*(.+)$/mi', $headers, $match)) {
            return '';
        }

        return trim($match[1]);
    }

    private function sessionIdFrom(string $headers): string
    {
        if (!preg_match('/^Set-Cookie:\s*PHPSESSID=([^;]+)/mi', $headers, $match)) {
            return '';
        }

        return $match[1];
    }

    private function sessionIdAppearingIn(string $body): ?string
    {
        if (preg_match('/PHPSESSID[=\\\\"\s:]+([A-Za-z0-9,-]{16,})/', $body, $match)) {
            return $match[1];
        }

        return null;
    }

    private function inputValue(string $html, string $name): string
    {
        if (!preg_match('/name="'.preg_quote($name, '/').'"[^>]*value="([^"]*)"/', $html, $match)
            && !preg_match('/value="([^"]*)"[^>]*name="'.preg_quote($name, '/').'"/', $html, $match)) {
            return '';
        }

        return html_entity_decode($match[1], ENT_QUOTES);
    }

    /**
     * @return list<string>
     */
    private function tokensLinkedFrom(string $html): array
    {
        preg_match_all('#/_profiler/([A-Za-z0-9]{6,})#', $html, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    private static function seedFixture(): array
    {
        $output = tempnam(sys_get_temp_dir(), 'birokrat-fixture-');
        if ($output === false) {
            self::fail('Could not allocate a fixture output file.');
        }
        self::runPhp(self::$projectDir.'/tests/Support/seed_unset_env_profiler_fixture.php', [$output]);
        $decoded = json_decode((string) file_get_contents($output), true, 512, JSON_THROW_ON_ERROR);
        unlink($output);

        return $decoded;
    }

    private static function startServer(): void
    {
        self::$port = self::freePort();
        self::$serverLog = tempnam(sys_get_temp_dir(), 'birokrat-server-');
        $command = [
            PHP_BINARY,
            '-S',
            '127.0.0.1:'.self::$port,
            '-t',
            self::$projectDir.'/public',
            self::$projectDir.'/public/index.php',
        ];
        $log = fopen(self::$serverLog, 'wb');
        self::$serverProcess = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => $log,
            2 => $log,
        ], $pipes, self::$projectDir, self::runtimeEnv());
        if (!is_resource(self::$serverProcess)) {
            self::fail('Could not start the front controller.');
        }
        fclose($pipes[0]);

        $deadline = microtime(true) + 10;
        while (microtime(true) < $deadline) {
            $socket = @fsockopen('127.0.0.1', self::$port, $errno, $error, 0.2);
            if (is_resource($socket)) {
                fclose($socket);

                return;
            }
            usleep(100000);
        }

        self::fail('Front controller did not accept connections. '.file_get_contents(self::$serverLog));
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if (!is_resource($socket)) {
            self::fail('Could not reserve a port: '.$error);
        }
        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr((string) $name, strrpos((string) $name, ':') + 1);
    }

    /**
     * @param list<string> $arguments
     */
    private static function runConsole(array $arguments): string
    {
        return self::runPhp(self::$projectDir.'/bin/console', $arguments);
    }

    /**
     * @param list<string> $arguments
     */
    private static function runPhp(string $script, array $arguments): string
    {
        $command = array_merge([PHP_BINARY, $script], $arguments);
        $pipes = [];
        $process = proc_open($command, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, self::$projectDir, self::runtimeEnv());
        if (!is_resource($process)) {
            self::fail('Could not run '.$script);
        }
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit !== 0) {
            self::fail($script.' exited '.$exit."\n".$stdout."\n".$stderr);
        }

        return $stdout.$stderr;
    }

    private static function runtimeEnv(): array
    {
        $env = [];
        foreach (['PATH', 'HOME', 'LANG', 'LC_ALL', 'TZ', 'SYSTEMROOT'] as $name) {
            $value = getenv($name);
            if (is_string($value) && $value !== '') {
                $env[$name] = $value;
            }
        }
        $env['APP_SECRET'] = self::PLACEHOLDER_APP_SECRET;
        $env['DATABASE_URL'] = self::$databaseUrl;

        return $env;
    }

    private static function writeAssetStubs(): void
    {
        $build = self::$projectDir.'/public/build';
        if (!is_dir($build) && !mkdir($build, 0777, true) && !is_dir($build)) {
            self::fail('Could not create public/build.');
        }
        $entries = ['css/app', 'css/copyleft', 'css/dashboard', 'css/invoice', 'css/report', 'css/admin', 'js/app'];
        $entrypoints = $build.'/entrypoints.json';
        if (!is_file($entrypoints)) {
            file_put_contents($entrypoints, json_encode([
                'entrypoints' => array_fill_keys($entries, ['js' => [], 'css' => []]),
            ], JSON_THROW_ON_ERROR));
        }
        $manifest = $build.'/manifest.json';
        if (!is_file($manifest)) {
            file_put_contents($manifest, '{}');
        }
    }
}
