<?php

namespace App\Update;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class UpdateChannelStore
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function current(): string
    {
        if (!is_file($this->path())) {
            return UpdateChannel::STABLE;
        }

        $value = trim((string) file_get_contents($this->path()));

        return UpdateChannel::isValid($value) ? $value : UpdateChannel::STABLE;
    }

    public function save(string $channel): void
    {
        if (!UpdateChannel::isValid($channel)) {
            throw new \InvalidArgumentException('Unknown update channel.');
        }

        $directory = dirname($this->path());
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create the update channel directory.');
        }

        if (file_put_contents($this->path(), $channel) === false) {
            throw new \RuntimeException('Cannot save the update channel.');
        }
    }

    private function path(): string
    {
        return $this->projectDir.'/var/update-channel';
    }
}
