<?php

declare(strict_types=1);

namespace TakeoutRedate\Service;

use TakeoutRedate\Platform\FileTimestampAdapter;

class FileTimestampService
{
    public function __construct(
        private readonly FileTimestampAdapter $adapter,
    ) {
    }

    /**
     * Check if timestamps are close enough (within tolerance).
     */
    public function closeEnough(int $a, int $b, int $tol = 2): bool
    {
        return abs($a - $b) <= $tol;
    }

    /**
     * Get the birth/creation time of a file.
     */
    public function getBirthTime(string $path): ?int
    {
        return $this->adapter->getCreationTime($path);
    }

    /**
     * Get the modification time of a file.
     */
    public function getModificationTime(string $path): ?int
    {
        return $this->adapter->getModificationTime($path);
    }

    /**
     * Check if a file needs timestamp updates.
     *
     * @return bool True if the file needs to be updated
     */
    public function needsUpdate(string $path, ?int $takenTs, ?int $createdTs): bool
    {
        $birth = $this->getBirthTime($path);
        $mtime = $this->getModificationTime($path);

        $okBirth = (null === $takenTs) || (null !== $birth && $this->closeEnough($birth, $takenTs));
        $okMtime = (null === $createdTs) || (null !== $mtime && $this->closeEnough($mtime, $createdTs));

        return !($okBirth && $okMtime);
    }

    /**
     * Apply timestamps to a file.
     */
    public function applyTimestamps(string $path, ?int $creationTs, ?int $modTs): bool
    {
        $success = true;

        if (null !== $creationTs) {
            if (!$this->adapter->setCreationTime($path, $creationTs)) {
                $success = false;
            }
        }
        if (null !== $modTs) {
            if (!$this->adapter->setModificationTime($path, $modTs)) {
                $success = false;
            }
        }

        return $success;
    }
}
