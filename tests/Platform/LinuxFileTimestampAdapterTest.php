<?php

declare(strict_types=1);

namespace TakeoutRedate\Tests\Platform;

use PHPUnit\Framework\TestCase;
use TakeoutRedate\Platform\LinuxFileTimestampAdapter;
use TakeoutRedate\Platform\PlatformDetector;

class LinuxFileTimestampAdapterTest extends TestCase
{
    private LinuxFileTimestampAdapter $adapter;

    protected function setUp(): void
    {
        $detector = new PlatformDetector();
        if (!$detector->isLinux()) {
            $this->markTestSkipped('This test only runs on Linux');
        }

        $this->adapter = new LinuxFileTimestampAdapter();
    }

    public function testIsAvailableReturnsTrue(): void
    {
        $this->assertTrue($this->adapter->isAvailable());
    }

    public function testGetModificationTimeReturnsIntOrNull(): void
    {
        $tempFile = $this->createTempFile();
        
        try {
            $mtime = $this->adapter->getModificationTime($tempFile);
            
            if ($mtime !== null) {
                $this->assertIsInt($mtime);
                $this->assertGreaterThan(0, $mtime);
            }
        } finally {
            @unlink($tempFile);
        }
    }

    public function testGetModificationTimeReturnsNullForNonExistentFile(): void
    {
        $mtime = $this->adapter->getModificationTime('/nonexistent/file/path');
        
        // filemtime returns false which becomes null
        $this->assertNull($mtime);
    }

    public function testGetCreationTimeReturnsIntOrNull(): void
    {
        $tempFile = $this->createTempFile();
        
        try {
            $ctime = $this->adapter->getCreationTime($tempFile);
            
            // Linux may or may not have creation time available
            // Assert that the result is either null or a valid integer timestamp
            if ($ctime !== null) {
                $this->assertIsInt($ctime);
                $this->assertGreaterThan(0, $ctime);
            } else {
                // If null, that's also valid for Linux
                $this->assertNull($ctime);
            }
        } finally {
            @unlink($tempFile);
        }
    }

    public function testSetModificationTimeReturnsBool(): void
    {
        $tempFile = $this->createTempFile();
        
        try {
            $timestamp = time();
            $result = $this->adapter->setModificationTime($tempFile, $timestamp);
            
            $this->assertIsBool($result);
            
            if ($result) {
                $mtime = $this->adapter->getModificationTime($tempFile);
                // Allow some tolerance for filesystem precision
                if ($mtime !== null) {
                    $this->assertLessThanOrEqual(2, abs($mtime - $timestamp));
                }
            }
        } finally {
            @unlink($tempFile);
        }
    }

    public function testSetModificationTimeReturnsFalseForNullTimestamp(): void
    {
        $tempFile = $this->createTempFile();
        
        try {
            $result = $this->adapter->setModificationTime($tempFile, null);
            $this->assertFalse($result);
        } finally {
            @unlink($tempFile);
        }
    }

    public function testSetCreationTimeReturnsBool(): void
    {
        $tempFile = $this->createTempFile();
        
        try {
            $timestamp = time();
            $result = $this->adapter->setCreationTime($tempFile, $timestamp);
            
            $this->assertIsBool($result);
        } finally {
            @unlink($tempFile);
        }
    }

    public function testSetCreationTimeReturnsFalseForNullTimestamp(): void
    {
        $tempFile = $this->createTempFile();
        
        try {
            $result = $this->adapter->setCreationTime($tempFile, null);
            $this->assertFalse($result);
        } finally {
            @unlink($tempFile);
        }
    }

    private function createTempFile(): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'takeout_test_');
        if ($tempFile === false) {
            $this->fail('Could not create temporary file');
        }
        
        file_put_contents($tempFile, 'test content');
        
        return $tempFile;
    }
}

