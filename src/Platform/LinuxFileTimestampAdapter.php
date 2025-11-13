<?php

declare(strict_types=1);

namespace TakeoutRedate\Platform;

use Symfony\Component\Process\Process;

class LinuxFileTimestampAdapter implements FileTimestampAdapter
{
    public function isAvailable(): bool
    {
        // touch and stat are always available on Linux
        return true;
    }

    public function getCreationTime(string $path): ?int
    {
        // Linux doesn't have a reliable creation time in stat
        // We can try to get it from statx if available, otherwise return null
        // For now, we'll use the modification time as a fallback
        $process = new Process(['stat', '-c', '%W', $path]);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        $val = trim($process->getOutput());
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
        if (null === $timestamp) {
            return false;
        }

        // Linux doesn't reliably support setting birth/creation time via standard tools
        // Some filesystems support it via statx, but it's not universally available
        // We'll attempt to use touch, but creation time may not be settable on all filesystems
        $formatted = date('YmdHis', $timestamp);
        $p = new Process(['touch', '-t', $formatted, $path]);
        $p->run();

        return $p->isSuccessful();
    }

    public function setModificationTime(string $path, ?int $timestamp): bool
    {
        if (null === $timestamp) {
            return false;
        }

        // Use PHP's touch() function which is more reliable
        return touch($path, $timestamp);
    }
}
