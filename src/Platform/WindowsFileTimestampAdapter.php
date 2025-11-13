<?php

declare(strict_types=1);

namespace TakeoutRedate\Platform;

use Symfony\Component\Process\Process;

class WindowsFileTimestampAdapter implements FileTimestampAdapter
{
    public function isAvailable(): bool
    {
        // PHP's built-in functions work on Windows
        return true;
    }

    public function getCreationTime(string $path): ?int
    {
        return $this->safeFileTime(fn (string $p) => filectime($p), $path);
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

        // On Windows, use PowerShell to set creation time
        // Escape the path properly for PowerShell
        $escapedPath = str_replace('"', '""', $path);
        $formatted = date('Y-m-d H:i:s', $timestamp);
        $psCommand = \sprintf(
            '(Get-Item -LiteralPath "%s").CreationTime = [DateTime]::ParseExact("%s", "yyyy-MM-dd HH:mm:ss", $null)',
            $escapedPath,
            $formatted
        );

        $command = ['powershell', '-NoProfile', '-Command', $psCommand];
        $process = new Process($command);
        $process->setTimeout(30);
        $process->run();

        return $process->isSuccessful();
    }

    public function setModificationTime(string $path, ?int $timestamp): bool
    {
        if (null === $timestamp) {
            return false;
        }

        // Use PHP's touch() function which works on Windows
        return touch($path, $timestamp);
    }
}
