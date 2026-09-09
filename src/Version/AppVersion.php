<?php

namespace App\Version;

final class AppVersion
{
    private readonly string $version;

    private readonly string $configuredVersion;

    public function __construct(
        private readonly string $projectDir,
        ?string $configuredVersion = null,
    ) {
        $this->configuredVersion = $configuredVersion ?? '';
        $this->version = $this->resolve();
    }

    public function current(): string
    {
        return $this->version;
    }

    private function resolve(): string
    {
        if ($this->configuredVersion !== '') {
            return $this->configuredVersion;
        }

        if (is_dir($this->projectDir.'/.git')) {
            $lines = [];
            $code = 0;
            exec(
                'git -C '.escapeshellarg($this->projectDir).' describe --tags --always 2>/dev/null',
                $lines,
                $code
            );
            if ($code === 0 && isset($lines[0]) && $lines[0] !== '') {
                return $lines[0];
            }
        }

        return 'dev';
    }
}
