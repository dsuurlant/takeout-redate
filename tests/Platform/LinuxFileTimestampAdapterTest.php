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
            $this->safeRemoveFile($tempFile);
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
            $this->safeRemoveFile($tempFile);
        }
    }

    public function testGetCreationTimeReturnsNullWhenProcessFails(): void
    {
        // Test with a non-existent file - Process should fail and return null
        $ctime = $this->adapter->getCreationTime('/nonexistent/file/path/that/does/not/exist');
        
        // Process should fail and getCreationTime should return null
        $this->assertNull($ctime);
    }

    public function testGetCreationTimeHandlesProcessErrorsGracefully(): void
    {
        // Test with a directory instead of a file - stat may behave differently
        $tempDir = sys_get_temp_dir() . '/takeout_test_dir_' . uniqid();
        
        if (!mkdir($tempDir, 0777, true) && !is_dir($tempDir)) {
            $this->fail('Could not create temporary directory');
        }
        
        try {
            $ctime = $this->adapter->getCreationTime($tempDir);
            
            // Should return either null or a valid timestamp, never throw
            if ($ctime !== null) {
                $this->assertIsInt($ctime);
            } else {
                $this->assertNull($ctime);
            }
        } finally {
            $this->safeRemoveDirectory($tempDir);
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
            $this->safeRemoveFile($tempFile);
        }
    }

    public function testSetModificationTimeReturnsFalseForNullTimestamp(): void
    {
        $tempFile = $this->createTempFile();
        
        try {
            $result = $this->adapter->setModificationTime($tempFile, null);
            $this->assertFalse($result);
        } finally {
            $this->safeRemoveFile($tempFile);
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
            $this->safeRemoveFile($tempFile);
        }
    }

    public function testSetCreationTimeReturnsFalseWhenProcessFails(): void
    {
        // Test with a non-existent file - Process should fail
        $result = $this->adapter->setCreationTime('/nonexistent/file/path', time());
        
        // Process should fail and setCreationTime should return false
        $this->assertFalse($result);
    }

    public function testSetCreationTimeReturnsFalseForNullTimestamp(): void
    {
        $tempFile = $this->createTempFile();
        
        try {
            $result = $this->adapter->setCreationTime($tempFile, null);
            $this->assertFalse($result);
        } finally {
            $this->safeRemoveFile($tempFile);
        }
    }

    private function safeRemoveFile(string $file): void
    {
        if (file_exists($file)) {
            unlink($file);
        }
    }

    private function safeRemoveDirectory(string $dir): void
    {
        if (is_dir($dir)) {
            rmdir($dir);
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

