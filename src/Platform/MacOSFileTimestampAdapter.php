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
        return $this->safeFileTime(fn (string $p) => filemtime($p), $path);
    }

    /**
     * Safely get file timestamp, converting warnings to null.
     */
    private function safeFileTime(callable $getter, string $path): ?int
    {
        set_error_handler(
            static function (int $severity, string $message, string $file, int $line): bool {
                if (\E_WARNING === $severity) {
                    throw new \ErrorException($message, 0, $severity, $file, $line);
                }

                return false;
            },
            \E_WARNING
        );

        try {
            $result = $getter($path);

            return false !== $result ? (int) $result : null;
        } catch (\Throwable $e) {
            return null;
        } finally {
            restore_error_handler();
        }
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
