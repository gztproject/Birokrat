<?php

namespace App\Update;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class UpdatePolicy
{
    public function versionToInstall(UpdateStatus $status): string
    {
        $version = $status->latestVersion;
        if ($status->error !== null || !$status->updateAvailable || $version === null || !VersionComparator::isSelectable($version)) {
            throw new BadRequestHttpException('Requested version must be newer than the installed version.');
        }

        return $version;
    }
}
