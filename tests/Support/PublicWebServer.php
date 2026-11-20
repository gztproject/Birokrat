<?php

namespace App\Tests\Support;

/**
 * Serves this application's public directory with Apache, using the
 * .htaccess shipped in that directory (existing files are returned as-is,
 * everything else is rewritten to index.php).
 */
final class PublicWebServer
{
    private string $configPath;
    private string $errorLog;

    public function __construct(
        private readonly string $publicDir,
        private readonly int $port,
        private readonly string $runtimeDir,
    ) {
    }

    public static function start(string $publicDir): self
    {
        $runtimeDir = sys_get_temp_dir().'/birokrat-public-web-'.bin2hex(random_bytes(4));
        if (!mkdir($runtimeDir, 0775, true) && !is_dir($runtimeDir)) {
            throw new \RuntimeException('Could not create '.$runtimeDir);
        }

        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($socket === false) {
            throw new \RuntimeException('Could not reserve a port: '.$errstr);
        }
        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        $port = (int) substr((string) $name, strrpos((string) $name, ':') + 1);

        $server = new self($publicDir, $port, $runtimeDir);
        $server->boot();

        return $server;
    }

    public function baseUrl(): string
    {
        return 'http://127.0.0.1:'.$this->port;
    }

    public function stop(): void
    {
        if (!is_file($this->configPath)) {
            return;
        }
        $this->runApache('-k stop');
    }

    public function errorLog(): string
    {
        if (!is_file($this->errorLog)) {
            return '';
        }

        return (string) file_get_contents($this->errorLog);
    }

    /**
     * @param array<string, scalar|null> $fields
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    public function request(string $method, string $path, ?string $cookieJar = null, array $fields = []): array
    {
        $url = str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
            ? $path
            : $this->baseUrl().(str_starts_with($path, '/') ? $path : '/'.$path);

        $headerLines = [];
        $curl = curl_init($url);
        if ($curl === false) {
            throw new \RuntimeException('curl_init failed for '.$url);
        }

        $options = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headerLines): int {
                $headerLines[] = $line;

                return strlen($line);
            },
        ];
        if ($cookieJar !== null) {
            if (!is_file($cookieJar)) {
                touch($cookieJar);
            }
            $options[CURLOPT_COOKIEJAR] = $cookieJar;
            $options[CURLOPT_COOKIEFILE] = $cookieJar;
        }
        if ($method === 'POST') {
            $options[CURLOPT_POSTFIELDS] = http_build_query($fields);
        }
        curl_setopt_array($curl, $options);

        $body = curl_exec($curl);
        if ($body === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new \RuntimeException('Request to '.$url.' failed: '.$error);
        }
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        $headers = [];
        foreach ($headerLines as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $name = strtolower(trim($parts[0]));
            $value = trim($parts[1]);
            $headers[$name] = isset($headers[$name]) ? $headers[$name].', '.$value : $value;
        }

        return [
            'status' => $status,
            'body' => $body,
            'headers' => $headers,
        ];
    }

    private function boot(): void
    {
        $modules = '/usr/lib/apache2/modules';
        $phpModule = $this->phpModulePath($modules);
        $this->configPath = $this->runtimeDir.'/httpd.conf';
        $this->errorLog = $this->runtimeDir.'/error.log';
        $publicDir = $this->publicDir;

        $config = <<<CONF
ServerName localhost
ServerRoot /etc/apache2
Listen 127.0.0.1:{$this->port}
PidFile {$this->runtimeDir}/httpd.pid
ErrorLog {$this->errorLog}
Mutex file:{$this->runtimeDir} default
LoadModule mpm_prefork_module {$modules}/mod_mpm_prefork.so
LoadModule authz_core_module {$modules}/mod_authz_core.so
LoadModule authz_host_module {$modules}/mod_authz_host.so
LoadModule dir_module {$modules}/mod_dir.so
LoadModule mime_module {$modules}/mod_mime.so
LoadModule rewrite_module {$modules}/mod_rewrite.so
LoadModule env_module {$modules}/mod_env.so
LoadModule php_module {$phpModule}
TypesConfig /etc/mime.types
DirectoryIndex index.php
DocumentRoot {$publicDir}
<Directory />
    AllowOverride none
    Require all denied
</Directory>
<Directory {$publicDir}>
    Options FollowSymLinks
    AllowOverride All
    Require all granted
    DirectoryIndex index.php
</Directory>
<FilesMatch "\\.ph(ar|p|tml)\$">
    SetHandler application/x-httpd-php
</FilesMatch>
SetEnv APP_ENV test
SetEnv APP_DEBUG 1
CONF;
        file_put_contents($this->configPath, $config);

        $this->runApache('-k start');
        $deadline = microtime(true) + 5;
        while (microtime(true) < $deadline) {
            $socket = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.2);
            if ($socket !== false) {
                fclose($socket);

                return;
            }
            usleep(100000);
        }

        throw new \RuntimeException("Apache did not listen on {$this->port}.\n".$this->errorLog());
    }

    private function runApache(string $arguments): void
    {
        $command = 'apache2 -f '.escapeshellarg($this->configPath).' '.$arguments;
        $output = [];
        $exitCode = 0;
        exec($command.' 2>&1', $output, $exitCode);
        if ($exitCode !== 0 && !str_contains($arguments, 'stop')) {
            throw new \RuntimeException("Apache command failed ({$exitCode}): ".implode("\n", $output)."\n".$this->errorLog());
        }
    }

    private function phpModulePath(string $modules): string
    {
        $preferred = $modules.'/libphp'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'.so';
        if (is_file($preferred)) {
            return $preferred;
        }

        $matches = glob($modules.'/libphp*.so') ?: [];
        if ($matches === []) {
            throw new \RuntimeException('No Apache PHP module (libphp*.so) found in '.$modules);
        }

        rsort($matches);

        return $matches[0];
    }
}
