<?php

declare(strict_types=1);

namespace TakeoutRedate\Service;

class MediaFileResolver
{
    /**
     * Derive the prefix name from a JSON file path.
     *
     * @return array{0:string,1:string} [mediaDir, prefixName]
     */
    public function derivePrefix(string $jsonPath): array
    {
        $dir = \dirname($jsonPath);
        $file = basename($jsonPath);
        $nameNoJson = str_ends_with($file, '.json') ? substr($file, 0, -5) : pathinfo($file, \PATHINFO_FILENAME);

        // Keep "<name>.<ext>" where ext is 3–4 alnum chars; ignore tails like ".supp..."
        if (preg_match('/^(.*\.[A-Za-z0-9]{3,4})(?:[^A-Za-z0-9].*)?$/', $nameNoJson, $m)) {
            $prefixName = $m[1];
        } else {
            $prefixName = $nameNoJson;
        }

        return [$dir, $prefixName];
    }

    /**
     * Resolve the media file path from a directory and prefix name.
     */
    public function resolveMediaPath(string $dir, string $prefixName): ?string
    {
        // Trim any lingering ".ext<non-alnum>..." tails
        $prefixName = preg_replace('/(\.[A-Za-z0-9]{3,4})[^A-Za-z0-9].*$/', '$1', $prefixName);
        if (!\is_string($prefixName)) {
            return null;
        }

        // Fast path: exact name
        $prefixPath = $dir.\DIRECTORY_SEPARATOR.$prefixName;
        if (is_file($prefixPath)) {
            return $prefixPath;
        }

        // Fallback: match by stem, accept any 3–4 char extension, pick shortest basename
        $prefixStem = pathinfo($prefixName, \PATHINFO_FILENAME);
        if (!\is_string($prefixStem)) {
            return null;
        }
        $best = null;
        $bestLen = \PHP_INT_MAX;

        if (($dh = @opendir($dir)) === false) {
            return null;
        }
        while (($entry = readdir($dh)) !== false) {
            if ('.' === $entry || '..' === $entry || str_ends_with($entry, '.json')) {
                continue;
            }

            if (0 === strncasecmp($entry, $prefixStem, \strlen($prefixStem))) {
                $ext = pathinfo($entry, \PATHINFO_EXTENSION);
                if (\is_string($ext) && '' !== $ext && preg_match('/^[A-Za-z0-9]{3,4}$/', $ext)) {
                    $filename = pathinfo($entry, \PATHINFO_FILENAME);
                    $len = \strlen((string) $filename);
                    if ($len < $bestLen) {
                        $best = $dir.\DIRECTORY_SEPARATOR.$entry;
                        $bestLen = $len;
                    }
                }
            }
        }
        closedir($dh);

        return $best ?: null;
    }
}
