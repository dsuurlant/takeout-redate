<?php

declare(strict_types=1);

namespace TakeoutRedate\Tests\Service;

use PHPUnit\Framework\TestCase;
use TakeoutRedate\Service\TakeoutJsonReader;

class TakeoutJsonReaderTest extends TestCase
{
    private TakeoutJsonReader $reader;

    protected function setUp(): void
    {
        $this->reader = new TakeoutJsonReader();
    }

    public function testReadTimestampsReturnsArrayWithTwoNullsForNonExistentFile(): void
    {
        $result = $this->reader->readTimestamps('/nonexistent/file.json');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertNull($result[0]);
        $this->assertNull($result[1]);
    }

    public function testReadTimestampsReturnsArrayWithTwoNullsForInvalidJson(): void
    {
        $tempFile = $this->createTempFile('invalid json content');
        
        try {
            $result = $this->reader->readTimestamps($tempFile);

            $this->assertIsArray($result);
            $this->assertCount(2, $result);
            $this->assertNull($result[0]);
            $this->assertNull($result[1]);
        } finally {
            @unlink($tempFile);
        }
    }

    public function testReadTimestampsReturnsTimestampsFromValidJson(): void
    {
        $json = [
            'photoTakenTime' => ['timestamp' => '1234567890'],
            'creationTime' => ['timestamp' => '9876543210'],
        ];
        
        $tempFile = $this->createTempFile(json_encode($json, JSON_THROW_ON_ERROR));
        
        try {
            $result = $this->reader->readTimestamps($tempFile);

            $this->assertIsArray($result);
            $this->assertCount(2, $result);
            $this->assertSame(1234567890, $result[0]);
            $this->assertSame(9876543210, $result[1]);
        } finally {
            @unlink($tempFile);
        }
    }

    public function testReadTimestampsHandlesStringTimestamps(): void
    {
        $json = [
            'photoTakenTime' => ['timestamp' => '1234567890'],
            'creationTime' => ['timestamp' => '9876543210'],
        ];
        
        $tempFile = $this->createTempFile(json_encode($json, JSON_THROW_ON_ERROR));
        
        try {
            $result = $this->reader->readTimestamps($tempFile);

            $this->assertIsInt($result[0]);
            $this->assertIsInt($result[1]);
        } finally {
            @unlink($tempFile);
        }
    }

    public function testReadTimestampsHandlesNumericTimestamps(): void
    {
        $json = [
            'photoTakenTime' => ['timestamp' => 1234567890],
            'creationTime' => ['timestamp' => 9876543210],
        ];
        
        $tempFile = $this->createTempFile(json_encode($json, JSON_THROW_ON_ERROR));
        
        try {
            $result = $this->reader->readTimestamps($tempFile);

            $this->assertIsInt($result[0]);
            $this->assertIsInt($result[1]);
            $this->assertSame(1234567890, $result[0]);
            $this->assertSame(9876543210, $result[1]);
        } finally {
            @unlink($tempFile);
        }
    }

    public function testReadTimestampsHandlesMissingTimestampFields(): void
    {
        $json = [];
        
        $tempFile = $this->createTempFile(json_encode($json, JSON_THROW_ON_ERROR));
        
        try {
            $result = $this->reader->readTimestamps($tempFile);

            $this->assertIsArray($result);
            $this->assertCount(2, $result);
            $this->assertNull($result[0]);
            $this->assertNull($result[1]);
        } finally {
            @unlink($tempFile);
        }
    }

    public function testReadTimestampsHandlesPartialTimestampFields(): void
    {
        $json = [
            'photoTakenTime' => ['timestamp' => '1234567890'],
        ];
        
        $tempFile = $this->createTempFile(json_encode($json, JSON_THROW_ON_ERROR));
        
        try {
            $result = $this->reader->readTimestamps($tempFile);

            $this->assertIsArray($result);
            $this->assertCount(2, $result);
            $this->assertSame(1234567890, $result[0]);
            $this->assertNull($result[1]);
        } finally {
            @unlink($tempFile);
        }
    }

    public function testReadTimestampsHandlesInvalidTimestampValues(): void
    {
        $json = [
            'photoTakenTime' => ['timestamp' => 'not-a-number'],
            'creationTime' => ['timestamp' => null],
        ];
        
        $tempFile = $this->createTempFile(json_encode($json, JSON_THROW_ON_ERROR));
        
        try {
            $result = $this->reader->readTimestamps($tempFile);

            $this->assertIsArray($result);
            $this->assertCount(2, $result);
            $this->assertNull($result[0]);
            $this->assertNull($result[1]);
        } finally {
            @unlink($tempFile);
        }
    }

    public function testReadTimestampsHandlesEmptyArray(): void
    {
        $json = [];
        
        $tempFile = $this->createTempFile(json_encode($json, JSON_THROW_ON_ERROR));
        
        try {
            $result = $this->reader->readTimestamps($tempFile);

            $this->assertIsArray($result);
            $this->assertCount(2, $result);
            $this->assertNull($result[0]);
            $this->assertNull($result[1]);
        } finally {
            @unlink($tempFile);
        }
    }

    private function createTempFile(string $content): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'takeout_json_');
        if ($tempFile === false) {
            $this->fail('Could not create temporary file');
        }
        
        file_put_contents($tempFile, $content);
        
        return $tempFile;
    }
}

