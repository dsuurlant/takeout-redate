<?php

namespace App\Console;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'takeout:restore-imagedates',
    description: 'Restore filesystem timestamps for Google Photos Takeout media from JSON sidecars.',
    aliases: ['imgdates'],
)]
class RewriteV2Command extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('root', null, InputOption::VALUE_REQUIRED, 'Root directory to scan', '.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview only; do not modify files and do not delete JSONs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $root = rtrim((string) $input->getOption('root'), DIRECTORY_SEPARATOR);
        $dryRun = (bool) $input->getOption('dry-run');

        if (!is_dir($root)) {
            $io->error("Root directory not found: {$root}");

            return Command::FAILURE;
        }

        $isMac = 0 === stripos(PHP_OS_FAMILY, 'Darwin');
        $setFile = $this->findSetFile();
        if (!$dryRun && (!$isMac || null === $setFile)) {
            $io->error('SetFile not available (requires macOS + Xcode Command Line Tools). Run with --dry-run to preview.');

            return Command::FAILURE;
        }

        // PASS 1: count only *.json files for the progress max
        $total = 0;
        $it1 = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
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
        $it2 = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($it2 as $path => $info) {
            if (!$info->isFile() || !str_ends_with(strtolower((string) $path), '.json')) {
                continue; // <-- no progress advance for non-JSON files
            }

            $dir = dirname((string) $path);
            $dirCount[$dir] = $dirCount[$dir] ?? 0;

            [$takenTs, $createdTs] = $this->readTakeoutTimestamps((string) $path);
            [$mediaDir, $prefixName] = $this->derivePrefix((string) $path);
            $found = $this->resolveMediaPath($mediaDir, $prefixName);

            if (null !== $found && (null !== $takenTs || null !== $createdTs)) {
                // skip write if already matching (±2s)
                $needsWrite = true;
                $birth = $this->getBirthTimeSec($found);      // may be null
                $mtime = filemtime($found) ?: null;

                $okBirth = (null === $takenTs) || (null !== $birth && $this->closeEnough($birth, $takenTs));
                $okMtime = (null === $createdTs) || (null !== $mtime && $this->closeEnough($mtime, $createdTs));

                if ($okBirth && $okMtime) {
                    $needsWrite = false;
                }

                if (!$dryRun) {
                    if ($needsWrite) {
                        $this->applyTimestamps($found, $takenTs, $createdTs, $setFile);
                    }
                    // Either written or already matching -> delete JSON
                    @unlink((string) $path);
                }

                ++$dirCount[$dir]; // count only resolved items
            }

            $progress->advance(); // <-- advance exactly once per JSON processed
        }

        $progress->finish();
        $output->writeln('');

        ksort($dirCount);
        foreach ($dirCount as $d => $count) {
            $output->writeln($d . ' : ' . $count . ' file(s) processed');
        }

        return Command::SUCCESS;
    }

    private function findSetFile(): ?string
    {
        $candidates = ['/usr/bin/SetFile'];
        foreach ($candidates as $cand) {
            if (is_file($cand) && is_executable($cand)) {
                return $cand;
            }
        }
        $which = trim((string) shell_exec('command -v SetFile 2>/dev/null'));

        return '' !== $which ? $which : null;
    }

    /** @return array{0:int|null,1:int|null} [takenTs, createdTs] */
    private function readTakeoutTimestamps(string $jsonFile): array
    {
        $raw = @file_get_contents($jsonFile);
        if (false === $raw) {
            return [null, null];
        }

        $j = json_decode($raw, true);
        if (!is_array($j)) {
            return [null, null];
        }

        $taken = $j['photoTakenTime']['timestamp'] ?? null;
        $created = $j['creationTime']['timestamp'] ?? null;

        $taken = is_numeric($taken) ? (int) $taken : null;
        $created = is_numeric($created) ? (int) $created : null;

        return [$taken, $created];
    }

    private function derivePrefix(string $jsonPath): array
    {
        $dir = dirname($jsonPath);
        $file = basename($jsonPath);
        $nameNoJson = str_ends_with($file, '.json') ? substr($file, 0, -5) : pathinfo($file, PATHINFO_FILENAME);

        // Keep "<name>.<ext>" where ext is 3–4 alnum chars; ignore tails like ".supp..."
        if (preg_match('/^(.*\.[A-Za-z0-9]{3,4})(?:[^A-Za-z0-9].*)?$/', $nameNoJson, $m)) {
            $prefixName = $m[1];
        } else {
            $prefixName = $nameNoJson;
        }

        return [$dir, $prefixName];
    }

    private function resolveMediaPath(string $dir, string $prefixName): ?string
    {
        // Trim any lingering ".ext<non-alnum>..." tails
        $prefixName = preg_replace('/(\.[A-Za-z0-9]{3,4})[^A-Za-z0-9].*$/', '$1', $prefixName);

        // Fast path: exact name
        $prefixPath = $dir . DIRECTORY_SEPARATOR . $prefixName;
        if (is_file($prefixPath)) {
            return $prefixPath;
        }

        // Fallback: match by stem, accept any 3–4 char extension, pick shortest basename
        $prefixStem = pathinfo($prefixName, PATHINFO_FILENAME);
        $best = null;
        $bestLen = PHP_INT_MAX;

        if (($dh = @opendir($dir)) === false) {
            return null;
        }
        while (($entry = readdir($dh)) !== false) {
            if ('.' === $entry || '..' === $entry || str_ends_with($entry, '.json')) {
                continue;
            }

            if (0 === strncasecmp($entry, $prefixStem, strlen($prefixStem))) {
                $ext = pathinfo($entry, PATHINFO_EXTENSION);
                if ('' !== $ext && preg_match('/^[A-Za-z0-9]{3,4}$/', $ext)) {
                    $len = strlen(pathinfo($entry, PATHINFO_FILENAME));
                    if ($len < $bestLen) {
                        $best = $dir . DIRECTORY_SEPARATOR . $entry;
                        $bestLen = $len;
                    }
                }
            }
        }
        closedir($dh);

        return $best ?: null;
    }

    private function getBirthTimeSec(string $path): ?int
    {
        // stat -f %B -> birth time (creation) in seconds on macOS (0 if unavailable)
        $out = @shell_exec('stat -f %B ' . escapeshellarg($path) . ' 2>/dev/null');
        if (null === $out) {
            return null;
        }
        $val = trim($out);
        if ('' === $val || '0' === $val) {
            return null;
        }

        return ctype_digit($val) ? (int) $val : null;
    }

    private function closeEnough(int $a, int $b, int $tol = 2): bool
    {
        return abs($a - $b) <= $tol;
    }

    private function applyTimestamps(string $path, ?int $creationTs, ?int $modTs, ?string $setFile): void
    {
        if (null === $setFile) {
            return;
        }
        if (null !== $creationTs) {
            $this->runProcess([$setFile, '-d', $this->fmtSetFile($creationTs), $path]);
        }
        if (null !== $modTs) {
            $this->runProcess([$setFile, '-m', $this->fmtSetFile($modTs), $path]);
        }
    }

    private function runProcess(array $cmd): void
    {
        $p = new Process($cmd);
        $p->run();
        // optionally: if (!$p->isSuccessful()) throw new \RuntimeException($p->getErrorOutput() ?: 'SetFile failed');
    }

    private function fmtSetFile(int $ts): string
    {
        return date('m/d/Y H:i:s', $ts); // local time
    }
}
