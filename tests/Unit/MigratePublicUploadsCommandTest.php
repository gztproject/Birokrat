<?php

namespace App\Tests\Unit;

use App\Command\MigratePublicUploadsCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Composer auto-scripts must relocate legacy public uploads on the first hop
 * from a release whose Update.sh never called migrate_public_uploads.
 */
class MigratePublicUploadsCommandTest extends TestCase
{
    public function testComposerAutoScriptsInvokeTheCommand(): void
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('app:migrate-public-uploads', $composer['scripts']['auto-scripts'] ?? []);
        self::assertSame('symfony-cmd', $composer['scripts']['auto-scripts']['app:migrate-public-uploads']);
    }

    public function testCommandMovesLegacyPublicUploads(): void
    {
        $root = sys_get_temp_dir().'/birokrat-cmd-upload-'.bin2hex(random_bytes(4));
        mkdir($root.'/public/uploads/signatures', 0775, true);
        mkdir($root.'/public/uploads/incoming-invoices', 0775, true);
        file_put_contents($root.'/public/uploads/signatures/sig.png', 'sig');
        file_put_contents($root.'/public/uploads/incoming-invoices/scan.pdf', 'scan');

        $tester = new CommandTester(new MigratePublicUploadsCommand($root));
        self::assertSame(0, $tester->execute([]));

        self::assertFileExists($root.'/uploads/signatures/sig.png');
        self::assertFileExists($root.'/uploads/incoming-invoices/scan.pdf');
        self::assertSame('sig', file_get_contents($root.'/uploads/signatures/sig.png'));
        self::assertDirectoryDoesNotExist($root.'/public/uploads');

        self::assertSame(0, $tester->execute([]), 'Second run must stay idempotent.');

        array_map('unlink', glob($root.'/uploads/*/*') ?: []);
        @rmdir($root.'/uploads/signatures');
        @rmdir($root.'/uploads/incoming-invoices');
        @rmdir($root.'/uploads');
        @rmdir($root.'/public');
        @rmdir($root);
    }
}
