<?php

namespace App\Version;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AppVersion
{
    private readonly string $version;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        $this->version = $this->resolve();
    }

    public function current(): string
    {
        return $this->version;
    }

    private function resolve(): string
    {
        if (!is_dir($this->projectDir.'/.git')) {
            return 'dev';
        }

        $lines = [];
        $code = 0;
        exec(
            'git -C '.escapeshellarg($this->projectDir).' describe --tags --abbrev=0 2>/dev/null',
            $lines,
            $code
        );

        if ($code === 0 && isset($lines[0]) && $lines[0] !== '') {
            return $lines[0];
        }

        return 'dev';
    }
}
