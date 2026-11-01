<?php

namespace App\Update;

use App\Utils\Markdown;
use App\Version\VersionSource;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ReleaseChecker
{
    private const LATEST_URL = 'https://api.github.com/repos/gztproject/Birokrat/releases/latest';

    private const LIST_URL = 'https://api.github.com/repos/gztproject/Birokrat/releases?per_page=100';

    private const RELEASE_URL_PREFIX = 'https://github.com/gztproject/Birokrat/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(service: 'cache.app')]
        private readonly CacheInterface $cache,
        private readonly VersionSource $versions,
        private readonly UpdateChannelStore $channels,
        private readonly Markdown $markdown,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    public function status(bool $refresh = false): UpdateStatus
    {
        $channel = $this->channels->current();
        $current = $this->versions->current();

        try {
            $release = $this->release($channel, $refresh);
        } catch (\Throwable) {
            return $this->failed($channel, $current);
        }

        if ($release === null) {
            return new UpdateStatus(
                channel: $channel,
                currentVersion: $current,
                latestVersion: null,
                updateAvailable: false,
                prerelease: false,
                releaseUrl: null,
                notesHtml: '',
                canUpdate: $this->canUpdate(),
                error: null,
            );
        }

        $notes = trim($release['notes']);

        return new UpdateStatus(
            channel: $channel,
            currentVersion: $current,
            latestVersion: $release['version'],
            updateAvailable: VersionComparator::isNewer($current, $release['version']),
            prerelease: $release['prerelease'],
            releaseUrl: $this->releaseUrl($release['url']),
            notesHtml: $notes === '' ? '' : $this->markdown->toHtml($notes),
            canUpdate: $this->canUpdate(),
            error: null,
        );
    }

    private function failed(string $channel, string $current): UpdateStatus
    {
        return new UpdateStatus(
            channel: $channel,
            currentVersion: $current,
            latestVersion: null,
            updateAvailable: false,
            prerelease: false,
            releaseUrl: null,
            notesHtml: '',
            canUpdate: $this->canUpdate(),
            error: 'check_failed',
        );
    }

    private function canUpdate(): bool
    {
        return $this->environment === 'prod';
    }

    /**
     * @return array{version: string, url: string, notes: string, prerelease: bool}|null
     */
    private function release(string $channel, bool $refresh): ?array
    {
        $key = 'app.update.release.'.$channel;
        if ($refresh) {
            $this->cache->delete($key);
        }

        $cached = $this->cache->get($key, function (ItemInterface $item) use ($channel): array {
            $item->expiresAfter(3600);

            return $this->load($channel) ?? ['empty' => true];
        });

        if (($cached['empty'] ?? false) === true && !isset($cached['version'])) {
            return null;
        }

        return $cached;
    }

    /**
     * @return array{version: string, url: string, notes: string, prerelease: bool}|null
     */
    private function load(string $channel): ?array
    {
        if ($channel === UpdateChannel::PRERELEASE) {
            $data = $this->requestJson(self::LIST_URL);
            if (isset($data['missing'])) {
                throw new \RuntimeException('GitHub release check failed.');
            }

            return $this->highest($data);
        }

        $data = $this->requestJson(self::LATEST_URL);
        if (isset($data['missing'])) {
            return null;
        }

        return $this->stableRelease($data);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestJson(string $url): array
    {
        try {
            $response = $this->httpClient->request('GET', $url, [
                'timeout' => 10,
                'max_duration' => 10,
                'headers' => [
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'Birokrat-Updater',
                ],
            ]);
            $status = $response->getStatusCode();
        } catch (ExceptionInterface $exception) {
            throw new \RuntimeException('GitHub release check failed.', 0, $exception);
        }

        if ($status === 404) {
            return ['missing' => true];
        }

        if ($status >= 400) {
            throw new \RuntimeException('GitHub release check failed.');
        }

        try {
            $data = $response->toArray();
        } catch (ExceptionInterface $exception) {
            throw new \RuntimeException('GitHub release check failed.', 0, $exception);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array{version: string, url: string, notes: string, prerelease: bool}|null
     */
    private function stableRelease(array $data): ?array
    {
        $tag = trim((string) ($data['tag_name'] ?? ''));
        if (!VersionComparator::isStable($tag)) {
            return null;
        }

        return $this->describe($data, false);
    }

    /**
     * @param array<mixed> $releases
     *
     * @return array{version: string, url: string, notes: string, prerelease: bool}|null
     */
    private function highest(array $releases): ?array
    {
        if (!array_is_list($releases)) {
            throw new \RuntimeException('GitHub release check failed.');
        }

        $best = null;
        foreach ($releases as $release) {
            if (!is_array($release) || ($release['draft'] ?? false) === true) {
                continue;
            }

            $tag = trim((string) ($release['tag_name'] ?? ''));
            if (!VersionComparator::isSelectable($tag)) {
                continue;
            }

            if ($best === null || VersionComparator::isNewer($best['version'], $tag)) {
                $best = $this->describe($release, VersionComparator::isPrerelease($tag));
            }
        }

        return $best;
    }

    /**
     * @param array<string, mixed> $release
     *
     * @return array{version: string, url: string, notes: string, prerelease: bool}
     */
    private function describe(array $release, bool $prerelease): array
    {
        return [
            'version' => trim((string) ($release['tag_name'] ?? '')),
            'url' => (string) ($release['html_url'] ?? ''),
            'notes' => (string) ($release['body'] ?? ''),
            'prerelease' => $prerelease,
        ];
    }

    private function releaseUrl(string $url): ?string
    {
        return str_starts_with($url, self::RELEASE_URL_PREFIX) ? $url : null;
    }
}
