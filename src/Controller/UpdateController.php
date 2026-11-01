<?php

namespace App\Controller;

use App\Update\ReleaseChecker;
use App\Update\UpdateChannel;
use App\Update\UpdateChannelStore;
use App\Update\UpdatePolicy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class UpdateController extends AbstractController
{
    private const PROGRESS_PREFIX = '__BK_PROGRESS__|';

    #[Route(path: '/admin/update', methods: ['GET'], name: 'admin_update')]
    public function index(): Response
    {
        return $this->render('admin/update.html.twig');
    }

    #[Route(path: '/admin/update/status', methods: ['GET'], name: 'admin_update_status')]
    public function status(Request $request, ReleaseChecker $checker): Response
    {
        return $this->json($checker->status($request->query->getBoolean('refresh'))->toArray());
    }

    #[Route(path: '/admin/update/channel', methods: ['POST'], name: 'admin_update_channel')]
    public function saveChannel(Request $request, UpdateChannelStore $channels): Response
    {
        $this->assertCsrf($request);

        $channel = (string) $request->request->get('channel', '');
        if (!UpdateChannel::isValid($channel)) {
            throw new BadRequestHttpException('Unknown update channel.');
        }

        $channels->save($channel);

        return $this->json(['channel' => $channel]);
    }

    #[Route(path: '/admin/update', methods: ['POST'], name: 'admin_update_run')]
    public function run(Request $request, ReleaseChecker $checker, UpdatePolicy $policy): StreamedResponse
    {
        $this->assertCsrf($request);

        if ('prod' !== $this->getParameter('kernel.environment')) {
            throw new BadRequestHttpException('Updates can only run in the prod environment.');
        }

        $status = $checker->status(false);
        $version = $policy->versionToInstall($status);
        $currentVersion = $status->currentVersion;
        $process = $this->createUpdateProcess($version);

        $response = new StreamedResponse(function () use ($process, $version, $currentVersion): void {
            $this->prepareStream();

            $emit = function (array $payload): void {
                $this->emitStreamEvent($payload);
            };

            $emit([
                'type' => 'meta',
                'currentVersion' => $currentVersion,
                'targetVersion' => $version,
            ]);

            $buffers = [
                Process::OUT => '',
                Process::ERR => '',
            ];

            $process->run(function (string $type, string $chunk) use (&$buffers, $emit): void {
                $buffers[$type] .= $chunk;

                while (($position = strpos($buffers[$type], "\n")) !== false) {
                    $line = rtrim(substr($buffers[$type], 0, $position), "\r");
                    $buffers[$type] = substr($buffers[$type], $position + 1);
                    $this->streamProcessLine($line, $type, $emit);
                }
            });

            foreach ($buffers as $type => $buffer) {
                $line = trim($buffer);
                if ($line !== '') {
                    $this->streamProcessLine($line, $type, $emit);
                }
            }

            if ($process->isSuccessful()) {
                $emit([
                    'type' => 'complete',
                    'progress' => 100,
                    'step' => 'done',
                    'message' => 'Update completed.',
                ]);

                return;
            }

            $emit([
                'type' => 'failed',
                'progress' => 100,
                'message' => sprintf('Update failed with exit code %s.', $process->getExitCode() ?? 'unknown'),
            ]);
        });

        $response->headers->set('Content-Type', 'application/x-ndjson; charset=utf-8');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    private function assertCsrf(Request $request): void
    {
        if (!$this->isCsrfTokenValid('admin_update', (string) $request->request->get('token', ''))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
    }

    private function createUpdateProcess(string $version): Process
    {
        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $process = new Process(
            ['bash', $projectDir.'/Update.sh', '-v', $version],
            $projectDir
        );
        $process->setTimeout(null);

        return $process;
    }

    private function prepareStream(): void
    {
        ignore_user_abort(true);
        set_time_limit(0);

        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', 'off');

        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
    }

    private function streamProcessLine(string $line, string $channel, callable $emit): void
    {
        if (trim($line) === '') {
            return;
        }

        if (str_starts_with($line, self::PROGRESS_PREFIX)) {
            $parts = explode('|', $line, 4);
            if (count($parts) === 4 && is_numeric($parts[1])) {
                $emit([
                    'type' => 'progress',
                    'progress' => max(0, min(100, (int) $parts[1])),
                    'step' => $parts[2],
                    'message' => $parts[3],
                ]);

                return;
            }
        }

        $emit([
            'type' => 'output',
            'channel' => Process::ERR === $channel ? 'stderr' : 'stdout',
            'message' => $line,
        ]);
    }

    private function emitStreamEvent(array $payload): void
    {
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";

        @ob_flush();
        flush();
    }
}
