<?php

declare(strict_types=1);

namespace TakeoutRedate\Service;

class TakeoutJsonReader
{
    /**
     * Read timestamps from a Google Takeout JSON file.
     *
     * @return array{0:int|null,1:int|null} [takenTs, createdTs]
     */
    public function readTimestamps(string $jsonFile): array
    {
        if (!is_readable($jsonFile)) {
            return [null, null];
        }

        $raw = file_get_contents($jsonFile);
        if (false === $raw) {
            return [null, null];
        }

        $j = json_decode($raw, true);
        if (!\is_array($j)) {
            return [null, null];
        }

        $taken = $j['photoTakenTime']['timestamp'] ?? null;
        $created = $j['creationTime']['timestamp'] ?? null;

        $taken = is_numeric($taken) ? (int) $taken : null;
        $created = is_numeric($created) ? (int) $created : null;

        return [$taken, $created];
    }
}
