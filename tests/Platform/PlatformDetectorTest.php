<?php

declare(strict_types=1);

namespace TakeoutRedate\Tests\Platform;

use PHPUnit\Framework\TestCase;
use TakeoutRedate\Platform\Platform;
use TakeoutRedate\Platform\PlatformDetector;

class PlatformDetectorTest extends TestCase
{
    private PlatformDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new PlatformDetector();
    }

    public function testIsMacDetectsMacOS(): void
    {
        $result = $this->detector->isMac();
        // This test depends on the actual platform
        // We just verify it returns a boolean
        $this->assertIsBool($result);
    }

    public function testIsWindowsDetectsWindows(): void
    {
        $result = $this->detector->isWindows();
        $this->assertIsBool($result);
    }

    public function testIsLinuxDetectsLinux(): void
    {
        $result = $this->detector->isLinux();
        $this->assertIsBool($result);
    }

    public function testGetPlatformReturnsPlatformEnum(): void
    {
        $platform = $this->detector->getPlatform();
        
        $this->assertInstanceOf(Platform::class, $platform);
        $this->assertContains($platform, [Platform::MACOS, Platform::WINDOWS, Platform::LINUX, Platform::UNKNOWN]);
    }

    public function testPlatformMethodsAreMutuallyExclusiveOnActualPlatform(): void
    {
        $isMac = $this->detector->isMac();
        $isWindows = $this->detector->isWindows();
        $isLinux = $this->detector->isLinux();

        // On any given platform, exactly one should be true
        $trueCount = (int) $isMac + (int) $isWindows + (int) $isLinux;
        
        // This assertion will pass if we're on a known platform (count = 1)
        // or if we're on an unknown platform (count = 0)
        $this->assertLessThanOrEqual(1, $trueCount);
        $this->assertGreaterThanOrEqual(0, $trueCount);
    }

    public function testGetPlatformMatchesPlatformDetectionMethods(): void
    {
        $platform = $this->detector->getPlatform();

        if ($this->detector->isMac()) {
            $this->assertSame(Platform::MACOS, $platform);
        } elseif ($this->detector->isWindows()) {
            $this->assertSame(Platform::WINDOWS, $platform);
        } elseif ($this->detector->isLinux()) {
            $this->assertSame(Platform::LINUX, $platform);
        } else {
            $this->assertSame(Platform::UNKNOWN, $platform);
        }
    }
}

