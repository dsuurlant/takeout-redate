<?php

declare(strict_types=1);

namespace TakeoutRedate\Platform;

interface FileTimestampAdapter
{
    /**
     * Check if this adapter is available on the current system.
     */
    public function isAvailable(): bool;

    /**
     * Get the creation/birth time of a file in seconds since Unix epoch.
     *
     * @return int|null The timestamp, or null if unavailable
     */
    public function getCreationTime(string $path): ?int;

    /**
     * Get the modification time of a file in seconds since Unix epoch.
     *
     * @return int|null The timestamp, or null if unavailable
     */
    public function getModificationTime(string $path): ?int;

    /**
     * Set the creation/birth time of a file.
     *
     * @param int|null $timestamp Unix timestamp, or null to skip
     */
    public function setCreationTime(string $path, ?int $timestamp): bool;

    /**
     * Set the modification time of a file.
     *
     * @param int|null $timestamp Unix timestamp, or null to skip
     */
    public function setModificationTime(string $path, ?int $timestamp): bool;
}
