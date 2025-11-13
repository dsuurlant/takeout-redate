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

    public function getPlatform(): Platform
    {
        if ($this->isMac()) {
            return Platform::MACOS;
        }
        if ($this->isWindows()) {
            return Platform::WINDOWS;
        }
        if ($this->isLinux()) {
            return Platform::LINUX;
        }

        return Platform::UNKNOWN;
    }
}
