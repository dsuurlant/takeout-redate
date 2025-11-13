<?php

declare(strict_types=1);

namespace TakeoutRedate\Platform;

class FileTimestampAdapterFactory
{
    public function create(): FileTimestampAdapter
    {
        $detector = new PlatformDetector();

        if ($detector->isMac()) {
            $adapter = new MacOSFileTimestampAdapter();
            if ($adapter->isAvailable()) {
                return $adapter;
            }
        }

        if ($detector->isWindows()) {
            $adapter = new WindowsFileTimestampAdapter();
            if ($adapter->isAvailable()) {
                return $adapter;
            }
        }

        if ($detector->isLinux()) {
            $adapter = new LinuxFileTimestampAdapter();
            if ($adapter->isAvailable()) {
                return $adapter;
            }
        }

        // Fallback to Linux adapter (most compatible)
        return new LinuxFileTimestampAdapter();
    }
}
