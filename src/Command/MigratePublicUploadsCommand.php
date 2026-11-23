<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Moves legacy public/uploads files into the private uploads/ tree.
 *
 * Must run from composer auto-scripts so the first /admin/update from an older
 * release (whose Update.sh lacks this step) still relocates files after checkout.
 */
#[AsCommand(
    name: 'app:migrate-public-uploads',
    description: 'Move legacy public/uploads documents into the private uploads directory',
)]
class MigratePublicUploadsCommand extends Command
{
    private const DIRS = ['signatures', 'incoming-invoices'];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $moved = 0;

        foreach (self::DIRS as $dir) {
            $from = $this->projectDir.'/public/uploads/'.$dir;
            $to = $this->projectDir.'/uploads/'.$dir;
            if (!is_dir($from)) {
                continue;
            }

            if (!is_dir($to) && !mkdir($to, 0775, true) && !is_dir($to)) {
                $io->error(sprintf('Could not create %s.', $to));

                return Command::FAILURE;
            }

            foreach (scandir($from) ?: [] as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $source = $from.'/'.$name;
                if (!is_file($source)) {
                    continue;
                }
                $destination = $to.'/'.$name;
                if (is_file($destination)) {
                    continue;
                }
                if (!rename($source, $destination)) {
                    $io->error(sprintf('Could not move %s to %s.', $source, $destination));

                    return Command::FAILURE;
                }
                ++$moved;
            }

            $this->removeEmptyDir($from);
        }

        $this->removeEmptyDir($this->projectDir.'/public/uploads');

        if ($moved > 0) {
            $io->success(sprintf('Moved %d uploaded file(s) out of public/uploads.', $moved));
        } else {
            $io->writeln('No legacy public uploads to move.');
        }

        return Command::SUCCESS;
    }

    private function removeEmptyDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $entries = array_diff(scandir($path) ?: [], ['.', '..']);
        if ($entries === []) {
            @rmdir($path);
        }
    }
}
