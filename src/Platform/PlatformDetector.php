<?php

declare(strict_types=1);

namespace TakeoutRedate\Platform;

class PlatformDetector
{
    public function isMac(): bool
    {
        return 0 === stripos(\PHP_OS_FAMILY, 'Darwin');
    }

    public function isWindows(): bool
    {
        return 0 === stripos(\PHP_OS_FAMILY, 'Windows');
    }

    public function isLinux(): bool
    {
        return 0 === stripos(\PHP_OS_FAMILY, 'Linux');
    }

    public function getPlatform(): string
    {
        if ($this->isMac()) {
            return 'macos';
        }
        if ($this->isWindows()) {
            return 'windows';
        }
        if ($this->isLinux()) {
            return 'linux';
        }

        return 'unknown';
    }
}
