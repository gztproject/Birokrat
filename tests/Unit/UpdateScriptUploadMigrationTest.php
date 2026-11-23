<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * /admin/update runs Update.sh -v, which must relocate legacy public uploads.
 */
class UpdateScriptUploadMigrationTest extends TestCase
{
    public function testUnattendedPathCallsSharedUploadMigration(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2).'/Update.sh');
        self::assertNotFalse($script);

        self::assertMatchesRegularExpression(
            '/^migrate_public_uploads\(\) \{/m',
            $script,
            'Update.sh must define a shared migrate_public_uploads helper.',
        );

        self::assertMatchesRegularExpression(
            '/run_unattended\(\) \{[\s\S]*?migrate_public_uploads\b[\s\S]*?^\}/m',
            $script,
            'Unattended updates (/admin/update) must call migrate_public_uploads.',
        );
    }

    public function testMigratePublicUploadsMovesLegacyFiles(): void
    {
        $root = sys_get_temp_dir().'/birokrat-upload-migrate-'.bin2hex(random_bytes(4));
        mkdir($root.'/public/uploads/signatures', 0775, true);
        mkdir($root.'/public/uploads/incoming-invoices', 0775, true);
        file_put_contents($root.'/public/uploads/signatures/sig.png', 'sig');
        file_put_contents($root.'/public/uploads/incoming-invoices/scan.pdf', 'scan');

        $updateSh = escapeshellarg(dirname(__DIR__, 2).'/Update.sh');
        $rootArg = escapeshellarg($root);
        $script = <<<BASH
set -euo pipefail
eval "\$(sed -n '/^migrate_public_uploads()/,/^}/p' {$updateSh})"
cd {$rootArg}
migrate_public_uploads
BASH;

        $process = proc_open(['bash', '-c', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        self::assertSame(0, $code, "stdout=$stdout stderr=$stderr");
        self::assertFileExists($root.'/uploads/signatures/sig.png');
        self::assertFileExists($root.'/uploads/incoming-invoices/scan.pdf');
        self::assertSame('sig', file_get_contents($root.'/uploads/signatures/sig.png'));
        self::assertSame('scan', file_get_contents($root.'/uploads/incoming-invoices/scan.pdf'));
        self::assertDirectoryDoesNotExist($root.'/public/uploads');

        array_map('unlink', glob($root.'/uploads/*/*') ?: []);
        @rmdir($root.'/uploads/signatures');
        @rmdir($root.'/uploads/incoming-invoices');
        @rmdir($root.'/uploads');
        @rmdir($root.'/public');
        @rmdir($root);
    }
}
