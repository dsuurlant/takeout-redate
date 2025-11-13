<?php

declare(strict_types=1);

namespace TakeoutRedate\Tests\Platform;

use PHPUnit\Framework\TestCase;
use TakeoutRedate\Platform\MacOSFileTimestampAdapter;
use TakeoutRedate\Platform\PlatformDetector;

class MacOSFileTimestampAdapterTest extends TestCase
{
    private MacOSFileTimestampAdapter $adapter;

    protected function setUp(): void
    {
        $detector = new PlatformDetector();
        if (!$detector->isMac()) {
            $this->markTestSkipped('This test only runs on macOS');
        }

        $this->adapter = new MacOSFileTimestampAdapter();
    }

    public function testIsAvailableReturnsBool(): void
    {
        $result = $this->adapter->isAvailable();
        $this->assertIsBool($result);
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
        $ctime = $this->adapter->getCreationTime('/nonexistent/file/path');
        
        $this->assertNull($ctime);
    }

    public function testSetModificationTimeReturnsBool(): void
    {
        if (!$this->adapter->isAvailable()) {
            $this->markTestSkipped('SetFile is not available on this system');
        }

        $tempFile = $this->createTempFile();
        
        try {
            $timestamp = time();
            $result = $this->adapter->setModificationTime($tempFile, $timestamp);
            
            $this->assertIsBool($result);
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
        if (!$this->adapter->isAvailable()) {
            $this->markTestSkipped('SetFile is not available on this system');
        }

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

    public function testSetCreationTimeReturnsFalseWhenNotAvailable(): void
    {
        if ($this->adapter->isAvailable()) {
            $this->markTestSkipped('Adapter is available on this system');
        }

        $tempFile = $this->createTempFile();
        
        try {
            $result = $this->adapter->setCreationTime($tempFile, time());
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

