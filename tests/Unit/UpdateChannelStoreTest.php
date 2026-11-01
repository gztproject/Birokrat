<?php

namespace App\Tests\Unit;

use App\Update\UpdateChannel;
use App\Update\UpdateChannelStore;
use PHPUnit\Framework\TestCase;

class UpdateChannelStoreTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/birokrat-channel-'.bin2hex(random_bytes(4));
        mkdir($this->directory, 0775, true);
    }

    protected function tearDown(): void
    {
        $path = $this->directory.'/var/update-channel';
        if (is_file($path)) {
            unlink($path);
        }
        if (is_dir($this->directory.'/var')) {
            rmdir($this->directory.'/var');
        }
        if (is_dir($this->directory)) {
            rmdir($this->directory);
        }
    }

    public function testMissingFileMeansStable(): void
    {
        $store = new UpdateChannelStore($this->directory);

        $this->assertSame(UpdateChannel::STABLE, $store->current());
    }

    public function testSavesAKnownChannel(): void
    {
        $store = new UpdateChannelStore($this->directory);
        $store->save(UpdateChannel::PRERELEASE);

        $this->assertSame(UpdateChannel::PRERELEASE, $store->current());
        $this->assertSame(UpdateChannel::PRERELEASE, trim((string) file_get_contents($this->directory.'/var/update-channel')));
    }

    public function testInvalidFileContentsFallBackToStable(): void
    {
        mkdir($this->directory.'/var', 0775, true);
        file_put_contents($this->directory.'/var/update-channel', 'beta');

        $store = new UpdateChannelStore($this->directory);

        $this->assertSame(UpdateChannel::STABLE, $store->current());
    }

    public function testUnknownChannelIsRejected(): void
    {
        $store = new UpdateChannelStore($this->directory);

        $this->expectException(\InvalidArgumentException::class);
        $store->save('beta');
    }
}
