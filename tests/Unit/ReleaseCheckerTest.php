<?php

namespace App\Tests\Unit;

use App\Update\ReleaseChecker;
use App\Update\UpdateChannel;
use App\Update\UpdateChannelStore;
use App\Utils\Markdown;
use App\Version\VersionSource;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ReleaseCheckerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/birokrat-release-'.bin2hex(random_bytes(4));
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

    public function testStableChannelIgnoresANewerPrerelease(): void
    {
        $urls = [];
        $checker = $this->checker('1.2.0', UpdateChannel::STABLE, function (string $url) use (&$urls) {
            $urls[] = $url;
            if (str_contains($url, '/releases/latest')) {
                return $this->releaseResponse('1.2.0', 'Stable notes');
            }

            throw new \RuntimeException('Stable channel must not read the pre-release list.');
        });

        $status = $checker->status();

        $this->assertSame('1.2.0', $status->latestVersion);
        $this->assertFalse($status->updateAvailable);
        $this->assertFalse($status->prerelease);
        $this->assertNull($status->error);
        $this->assertCount(1, $urls);
    }

    public function testStableChannelDropsAPrereleasePresentedAsLatest(): void
    {
        $checker = $this->checker('1.2.0', UpdateChannel::STABLE, function () {
            return $this->releaseResponse('1.4.0-rc.1', 'Not for the stable channel');
        });

        $status = $checker->status();

        $this->assertNull($status->latestVersion);
        $this->assertFalse($status->updateAvailable);
        $this->assertNull($status->error);
    }

    public function testStableChannelDoesNotOfferAnOlderReleaseThanTheInstalledPrerelease(): void
    {
        $checker = $this->checker('1.3.0-rc.1', UpdateChannel::STABLE, function () {
            return $this->releaseResponse('1.2.0', 'Older stable');
        });

        $status = $checker->status();

        $this->assertSame('1.2.0', $status->latestVersion);
        $this->assertFalse($status->updateAvailable);
    }

    public function testPrereleaseChannelOffersANewerCandidate(): void
    {
        $checker = $this->checker('1.2.0', UpdateChannel::PRERELEASE, function () {
            return $this->listResponse([
                $this->release('1.2.0', 'Stable', false),
                $this->release('1.3.0-rc.1', "Candidate\n\n<script>alert(1)</script>", true),
                $this->release('9.0.0', 'Draft', false, true),
            ]);
        });

        $status = $checker->status();

        $this->assertSame('1.3.0-rc.1', $status->latestVersion);
        $this->assertTrue($status->updateAvailable);
        $this->assertTrue($status->prerelease);
        $this->assertStringContainsString('Candidate', $status->notesHtml);
        $this->assertStringNotContainsString('<script', $status->notesHtml);
    }

    public function testPrereleaseChannelPrefersANewerStableTag(): void
    {
        $checker = $this->checker('1.3.0-rc.1', UpdateChannel::PRERELEASE, function () {
            return $this->listResponse([
                $this->release('1.3.0-rc.2', 'Newer candidate', true),
                $this->release('1.3.0', 'Final', false),
            ]);
        });

        $status = $checker->status();

        $this->assertSame('1.3.0', $status->latestVersion);
        $this->assertTrue($status->updateAvailable);
        $this->assertFalse($status->prerelease);
    }

    public function testFailedCheckIsReportedAndNotCached(): void
    {
        $calls = 0;
        $checker = $this->checker('1.0.0', UpdateChannel::STABLE, function () use (&$calls) {
            ++$calls;

            return new MockResponse('unavailable', ['http_code' => 500]);
        });

        $first = $checker->status();
        $second = $checker->status();

        $this->assertSame('check_failed', $first->error);
        $this->assertFalse($first->updateAvailable);
        $this->assertSame('check_failed', $second->error);
        $this->assertSame(2, $calls);
    }

    public function testSuccessfulCheckIsCachedUntilRefresh(): void
    {
        $calls = 0;
        $checker = $this->checker('1.0.0', UpdateChannel::STABLE, function () use (&$calls) {
            ++$calls;

            return $this->releaseResponse('1.2.0', 'Notes');
        });

        $this->assertTrue($checker->status()->updateAvailable);
        $this->assertTrue($checker->status()->updateAvailable);
        $this->assertTrue($checker->status(true)->updateAvailable);

        $this->assertSame(2, $calls);
    }

    public function testCanUpdateOnlyInProduction(): void
    {
        $prod = $this->checker('1.0.0', UpdateChannel::STABLE, function () {
            return $this->releaseResponse('1.2.0', 'Notes');
        }, 'prod');
        $test = $this->checker('1.0.0', UpdateChannel::STABLE, function () {
            return $this->releaseResponse('1.2.0', 'Notes');
        }, 'test');

        $this->assertTrue($prod->status()->canUpdate);
        $this->assertFalse($test->status()->canUpdate);
    }

    private function checker(string $installed, string $channel, callable $responder, string $environment = 'test'): ReleaseChecker
    {
        $store = new UpdateChannelStore($this->directory);
        if ($channel !== UpdateChannel::STABLE) {
            $store->save($channel);
        }

        $client = new MockHttpClient(function (string $method, string $url) use ($responder) {
            return $responder($url);
        });

        return new ReleaseChecker(
            $client,
            new ArrayAdapter(),
            new class($installed) implements VersionSource {
                public function __construct(private readonly string $version)
                {
                }

                public function current(): string
                {
                    return $this->version;
                }
            },
            $store,
            new Markdown(),
            $environment,
        );
    }

    private function releaseResponse(string $tag, string $body): MockResponse
    {
        return new MockResponse(json_encode($this->release($tag, $body, str_contains($tag, '-')), JSON_THROW_ON_ERROR));
    }

    /**
     * @param list<array<string, mixed>> $releases
     */
    private function listResponse(array $releases): MockResponse
    {
        return new MockResponse(json_encode($releases, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function release(string $tag, string $body, bool $prerelease, bool $draft = false): array
    {
        return [
            'tag_name' => $tag,
            'html_url' => 'https://github.com/gztproject/Birokrat/releases/tag/'.$tag,
            'body' => $body,
            'draft' => $draft,
            'prerelease' => $prerelease,
        ];
    }
}
