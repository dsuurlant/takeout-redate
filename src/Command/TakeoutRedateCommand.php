<?php

declare(strict_types=1);

namespace TakeoutRedate\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TakeoutRedate\Platform\FileTimestampAdapterFactory;
use TakeoutRedate\Service\FileTimestampService;
use TakeoutRedate\Service\MediaFileResolver;
use TakeoutRedate\Service\TakeoutJsonReader;

#[AsCommand(
    name: 'takeout-redate',
    description: 'Restore filesystem timestamps for Google Takeout media from JSON metadata.',
    aliases: ['redate'],
)]
class TakeoutRedateCommand extends Command
{
    public function __construct(
        private readonly FileTimestampAdapterFactory $adapterFactory,
        private readonly TakeoutJsonReader $jsonReader,
        private readonly MediaFileResolver $mediaResolver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Directory path to scan', '.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview only; do not modify files and do not delete JSONs')
            ->addOption('no-delete', null, InputOption::VALUE_NONE, 'Do not delete JSON files after processing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $path = rtrim((string) $input->getOption('path'), \DIRECTORY_SEPARATOR);
        $dryRun = (bool) $input->getOption('dry-run');
        $noDelete = (bool) $input->getOption('no-delete');

        if (!is_dir($path)) {
            $io->error("Directory not found: {$path}");

            return Command::FAILURE;
        }

        $adapter = $this->adapterFactory->create();
        if (!$dryRun && !$adapter->isAvailable()) {
            $io->error('File timestamp adapter not available on this platform. Run with --dry-run to preview.');

            return Command::FAILURE;
        }

        $timestampService = new FileTimestampService($adapter);

        // PASS 1: count only *.json files for the progress max
        $total = 0;
        $it1 = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it1 as $p => $info) {
            if ($info->isFile() && str_ends_with(strtolower((string) $p), '.json')) {
                ++$total;
            }
        }

        $progress = new ProgressBar($output, $total);
        $progress->start();

        // Per-subdirectory counters
        $dirCount = [];

        // PASS 2: process only *.json and advance once per JSON
        $it2 = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($it2 as $path => $info) {
            if (!$info->isFile() || !str_ends_with(strtolower((string) $path), '.json')) {
                continue; // <-- no progress advance for non-JSON files
            }

            $dir = \dirname((string) $path);
            $dirCount[$dir] = $dirCount[$dir] ?? 0;

            [$takenTs, $createdTs] = $this->jsonReader->readTimestamps((string) $path);
            [$mediaDir, $prefixName] = $this->mediaResolver->derivePrefix((string) $path);
            $found = $this->mediaResolver->resolveMediaPath($mediaDir, $prefixName);

            if (null !== $found && (null !== $takenTs || null !== $createdTs)) {
                $needsWrite = $timestampService->needsUpdate($found, $takenTs, $createdTs);

                if (!$dryRun) {
                    if ($needsWrite) {
                        $timestampService->applyTimestamps($found, $takenTs, $createdTs);
                    }
                    // Either written or already matching -> delete JSON (unless --no-delete is set)
                    if (!$noDelete) {
                        if (file_exists((string) $path)) {
                            unlink((string) $path);
                        }
                    }
                }

                ++$dirCount[$dir]; // count only resolved items
            }

            $progress->advance(); // <-- advance exactly once per JSON processed
        }

        $progress->finish();
        $output->writeln('');

        ksort($dirCount);
        foreach ($dirCount as $d => $count) {
            $output->writeln($d.' : '.$count.' file(s) processed');
        }

        return Command::SUCCESS;
    }
}
