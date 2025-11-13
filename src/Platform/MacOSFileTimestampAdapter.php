<?php

declare(strict_types=1);

namespace TakeoutRedate\Platform;

use Symfony\Component\Process\Process;

class MacOSFileTimestampAdapter implements FileTimestampAdapter
{
    private ?string $setFile = null;

    public function __construct()
    {
        $this->setFile = $this->findSetFile();
    }

    public function isAvailable(): bool
    {
        return null !== $this->setFile;
    }

    public function getCreationTime(string $path): ?int
    {
        // stat -f %B -> birth time (creation) in seconds on macOS (0 if unavailable)
        $out = @shell_exec('stat -f %B '.escapeshellarg($path).' 2>/dev/null');
        if (null === $out || false === $out) {
            return null;
        }
        $val = trim($out);
        if ('' === $val || '0' === $val) {
            return null;
        }

        return ctype_digit($val) ? (int) $val : null;
    }

    public function getModificationTime(string $path): ?int
    {
        $mtime = filemtime($path);

        return false !== $mtime ? $mtime : null;
    }

    public function setCreationTime(string $path, ?int $timestamp): bool
    {
        if (null === $timestamp || null === $this->setFile) {
            return false;
        }

        $p = new Process([$this->setFile, '-d', $this->formatTimestamp($timestamp), $path]);
        $p->run();

        return $p->isSuccessful();
    }

    public function setModificationTime(string $path, ?int $timestamp): bool
    {
        if (null === $timestamp || null === $this->setFile) {
            return false;
        }

        $p = new Process([$this->setFile, '-m', $this->formatTimestamp($timestamp), $path]);
        $p->run();

        return $p->isSuccessful();
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

    private function formatTimestamp(int $ts): string
    {
        return date('m/d/Y H:i:s', $ts); // local time
    }
}
