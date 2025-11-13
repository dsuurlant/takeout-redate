<?php

declare(strict_types=1);

namespace TakeoutRedate\Platform;

class WindowsFileTimestampAdapter implements FileTimestampAdapter
{
    public function isAvailable(): bool
    {
        // PHP's built-in functions work on Windows
        return true;
    }

    public function getCreationTime(string $path): ?int
    {
        $ctime = filectime($path);

        return false !== $ctime ? $ctime : null;
    }

    public function getModificationTime(string $path): ?int
    {
        $mtime = filemtime($path);

        return false !== $mtime ? $mtime : null;
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
        $command = \sprintf('powershell -NoProfile -Command "%s"', $psCommand);
        @exec($command, $output, $returnCode);

        return 0 === $returnCode;
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
