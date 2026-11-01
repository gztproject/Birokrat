<?php

namespace App\Tests\Unit;

use App\Update\UpdateChannel;
use App\Update\UpdatePolicy;
use App\Update\UpdateStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class UpdatePolicyTest extends TestCase
{
    public function testRejectsAVersionThatIsNotNewer(): void
    {
        $policy = new UpdatePolicy();

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('newer');
        $policy->versionToInstall($this->updateStatus('1.2.0', false));
    }

    public function testRejectsAFailedCheck(): void
    {
        $policy = new UpdatePolicy();
        $status = new UpdateStatus(UpdateChannel::STABLE, '1.0.0', null, false, false, null, '', true, 'check_failed');

        $this->expectException(BadRequestHttpException::class);
        $policy->versionToInstall($status);
    }

    public function testReturnsANewerPrerelease(): void
    {
        $policy = new UpdatePolicy();

        $this->assertSame('1.3.0-rc.1', $policy->versionToInstall($this->updateStatus('1.3.0-rc.1', true, true)));
    }

    private function updateStatus(string $latest, bool $available, bool $prerelease = false): UpdateStatus
    {
        return new UpdateStatus(
            channel: $prerelease ? UpdateChannel::PRERELEASE : UpdateChannel::STABLE,
            currentVersion: '1.2.0',
            latestVersion: $latest,
            updateAvailable: $available,
            prerelease: $prerelease,
            releaseUrl: null,
            notesHtml: '',
            canUpdate: true,
            error: null,
        );
    }
}
