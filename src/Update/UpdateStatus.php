<?php

namespace App\Update;

final readonly class UpdateStatus
{
    public function __construct(
        public string $channel,
        public string $currentVersion,
        public ?string $latestVersion,
        public bool $updateAvailable,
        public bool $prerelease,
        public ?string $releaseUrl,
        public string $notesHtml,
        public bool $canUpdate,
        public ?string $error,
    ) {
    }

    public function toArray(): array
    {
        return [
            'channel' => $this->channel,
            'currentVersion' => $this->currentVersion,
            'latestVersion' => $this->latestVersion,
            'updateAvailable' => $this->updateAvailable,
            'prerelease' => $this->prerelease,
            'releaseUrl' => $this->releaseUrl,
            'notesHtml' => $this->notesHtml,
            'canUpdate' => $this->canUpdate,
            'error' => $this->error,
        ];
    }
}
