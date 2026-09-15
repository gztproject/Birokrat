<?php

namespace App\Twig;

use App\Version\AppVersion;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

final class VersionExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private readonly AppVersion $appVersion)
    {
    }

    public function getGlobals(): array
    {
        return [
            'app_version' => $this->appVersion->current(),
        ];
    }
}
