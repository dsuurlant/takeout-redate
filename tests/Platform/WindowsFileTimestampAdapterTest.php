<?php

declare(strict_types=1);

namespace TakeoutRedate\Tests\Platform;

use PHPUnit\Framework\TestCase;
use TakeoutRedate\Platform\PlatformDetector;
use TakeoutRedate\Platform\WindowsFileTimestampAdapter;

class WindowsFileTimestampAdapterTest extends TestCase
{
    private WindowsFileTimestampAdapter $adapter;

    protected function setUp(): void
    {
        $detector = new PlatformDetector();
        if (!$detector->isWindows()) {
            $this->markTestSkipped('This test only runs on Windows');
        }

        $this->adapter = new WindowsFileTimestampAdapter();
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
        $mtime = $this->adapter->getModificationTime('C:\nonexistent\file\path');
        
        $this->assertNull($mtime);
    }

    public function testGetCreationTimeReturnsIntOrNull(): void
    {
        $tempFile = $this->createTempFile();
        
        try {
            $ctime = $this->adapter->getCreationTime($tempFile);
            
            if ($ctime !== null) {
                $this->assertIsInt($ctime);
                $this->assertGreaterThan(0, $ctime);
            }
        } finally {
            @unlink($tempFile);
        }
    }

    public function testGetCreationTimeReturnsNullForNonExistentFile(): void
    {
        $ctime = $this->adapter->getCreationTime('C:\nonexistent\file\path');
        
        $this->assertNull($ctime);
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
            
            // Result may be false if PowerShell is not available or command fails
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

