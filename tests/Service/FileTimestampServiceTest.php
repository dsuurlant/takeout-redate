<?php

declare(strict_types=1);

namespace TakeoutRedate\Tests\Service;

use PHPUnit\Framework\TestCase;
use TakeoutRedate\Platform\FileTimestampAdapter;
use TakeoutRedate\Service\FileTimestampService;

class FileTimestampServiceTest extends TestCase
{
    private FileTimestampAdapter $adapter;
    private FileTimestampService $service;

    protected function setUp(): void
    {
        $this->adapter = $this->createMock(FileTimestampAdapter::class);
        $this->service = new FileTimestampService($this->adapter);
    }

    public function testCloseEnoughReturnsTrueWhenValuesAreEqual(): void
    {
        $this->assertTrue($this->service->closeEnough(1000, 1000));
    }

    public function testCloseEnoughReturnsTrueWithinTolerance(): void
    {
        $this->assertTrue($this->service->closeEnough(1000, 1001));
        $this->assertTrue($this->service->closeEnough(1000, 1002));
        $this->assertTrue($this->service->closeEnough(1000, 999));
        $this->assertTrue($this->service->closeEnough(1000, 998));
    }

    public function testCloseEnoughReturnsFalseOutsideTolerance(): void
    {
        $this->assertFalse($this->service->closeEnough(1000, 1003));
        $this->assertFalse($this->service->closeEnough(1000, 997));
    }

    public function testCloseEnoughUsesCustomTolerance(): void
    {
        $this->assertTrue($this->service->closeEnough(1000, 1010, 10));
        $this->assertFalse($this->service->closeEnough(1000, 1011, 10));
    }

    public function testGetBirthTimeDelegatesToAdapter(): void
    {
        $expectedTime = 1234567890;
        $this->adapter
            ->expects($this->once())
            ->method('getCreationTime')
            ->with('/path/to/file')
            ->willReturn($expectedTime);

        $result = $this->service->getBirthTime('/path/to/file');

        $this->assertSame($expectedTime, $result);
    }

    public function testGetBirthTimeReturnsNullWhenAdapterReturnsNull(): void
    {
        $this->adapter
            ->expects($this->once())
            ->method('getCreationTime')
            ->willReturn(null);

        $result = $this->service->getBirthTime('/path/to/file');

        $this->assertNull($result);
    }

    public function testGetModificationTimeDelegatesToAdapter(): void
    {
        $expectedTime = 9876543210;
        $this->adapter
            ->expects($this->once())
            ->method('getModificationTime')
            ->with('/path/to/file')
            ->willReturn($expectedTime);

        $result = $this->service->getModificationTime('/path/to/file');

        $this->assertSame($expectedTime, $result);
    }

    public function testNeedsUpdateReturnsFalseWhenBothTimestampsMatch(): void
    {
        $this->adapter
            ->method('getCreationTime')
            ->willReturn(1000);
        $this->adapter
            ->method('getModificationTime')
            ->willReturn(2000);

        $result = $this->service->needsUpdate('/path/to/file', 1000, 2000);

        $this->assertFalse($result);
    }

    public function testNeedsUpdateReturnsFalseWhenTimestampsAreCloseEnough(): void
    {
        $this->adapter
            ->method('getCreationTime')
            ->willReturn(1000);
        $this->adapter
            ->method('getModificationTime')
            ->willReturn(2001); // Within tolerance of 2

        $result = $this->service->needsUpdate('/path/to/file', 1000, 2000);

        $this->assertFalse($result);
    }

    public function testNeedsUpdateReturnsTrueWhenBirthTimeDiffers(): void
    {
        $this->adapter
            ->method('getCreationTime')
            ->willReturn(1000);
        $this->adapter
            ->method('getModificationTime')
            ->willReturn(2000);

        $result = $this->service->needsUpdate('/path/to/file', 2000, 2000);

        $this->assertTrue($result);
    }

    public function testNeedsUpdateReturnsTrueWhenModificationTimeDiffers(): void
    {
        $this->adapter
            ->method('getCreationTime')
            ->willReturn(1000);
        $this->adapter
            ->method('getModificationTime')
            ->willReturn(2000);

        $result = $this->service->needsUpdate('/path/to/file', 1000, 3000);

        $this->assertTrue($result);
    }

    public function testNeedsUpdateReturnsFalseWhenBothTimestampsAreNull(): void
    {
        $this->adapter
            ->method('getCreationTime')
            ->willReturn(1000);
        $this->adapter
            ->method('getModificationTime')
            ->willReturn(2000);

        $result = $this->service->needsUpdate('/path/to/file', null, null);

        $this->assertFalse($result);
    }

    public function testNeedsUpdateReturnsTrueWhenExpectedTimestampIsSetButFileTimeIsNull(): void
    {
        $this->adapter
            ->method('getCreationTime')
            ->willReturn(null);
        $this->adapter
            ->method('getModificationTime')
            ->willReturn(2000);

        $result = $this->service->needsUpdate('/path/to/file', 1000, null);

        $this->assertTrue($result);
    }

    public function testApplyTimestampsSetsBothTimestamps(): void
    {
        $this->adapter
            ->expects($this->once())
            ->method('setCreationTime')
            ->with('/path/to/file', 1000)
            ->willReturn(true);
        $this->adapter
            ->expects($this->once())
            ->method('setModificationTime')
            ->with('/path/to/file', 2000)
            ->willReturn(true);

        $result = $this->service->applyTimestamps('/path/to/file', 1000, 2000);

        $this->assertTrue($result);
    }

    public function testApplyTimestampsSkipsNullTimestamps(): void
    {
        $this->adapter
            ->expects($this->never())
            ->method('setCreationTime');
        $this->adapter
            ->expects($this->once())
            ->method('setModificationTime')
            ->with('/path/to/file', 2000)
            ->willReturn(true);

        $result = $this->service->applyTimestamps('/path/to/file', null, 2000);

        $this->assertTrue($result);
    }

    public function testApplyTimestampsReturnsFalseWhenCreationTimeFails(): void
    {
        $this->adapter
            ->expects($this->once())
            ->method('setCreationTime')
            ->willReturn(false);
        $this->adapter
            ->expects($this->once())
            ->method('setModificationTime')
            ->willReturn(true);

        $result = $this->service->applyTimestamps('/path/to/file', 1000, 2000);

        $this->assertFalse($result);
    }

    public function testApplyTimestampsReturnsFalseWhenModificationTimeFails(): void
    {
        $this->adapter
            ->expects($this->once())
            ->method('setCreationTime')
            ->willReturn(true);
        $this->adapter
            ->expects($this->once())
            ->method('setModificationTime')
            ->willReturn(false);

        $result = $this->service->applyTimestamps('/path/to/file', 1000, 2000);

        $this->assertFalse($result);
    }

    public function testApplyTimestampsReturnsTrueWhenBothTimestampsAreNull(): void
    {
        $this->adapter
            ->expects($this->never())
            ->method('setCreationTime');
        $this->adapter
            ->expects($this->never())
            ->method('setModificationTime');

        $result = $this->service->applyTimestamps('/path/to/file', null, null);

        $this->assertTrue($result);
    }
}

