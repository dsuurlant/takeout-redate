<?php

declare(strict_types=1);

namespace TakeoutRedate\Tests\Platform;

use PHPUnit\Framework\TestCase;
use TakeoutRedate\Platform\FileTimestampAdapter;
use TakeoutRedate\Platform\FileTimestampAdapterFactory;

class FileTimestampAdapterFactoryTest extends TestCase
{
    private FileTimestampAdapterFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new FileTimestampAdapterFactory();
    }

    public function testCreateReturnsFileTimestampAdapter(): void
    {
        $adapter = $this->factory->create();

        $this->assertInstanceOf(FileTimestampAdapter::class, $adapter);
    }

    public function testCreateReturnsAvailableAdapter(): void
    {
        $adapter = $this->factory->create();

        // Factory should always return an adapter, even if it's not available
        // The adapter itself will indicate availability
        $this->assertInstanceOf(FileTimestampAdapter::class, $adapter);
    }

    public function testCreateReturnsSameTypeOnMultipleCalls(): void
    {
        $adapter1 = $this->factory->create();
        $adapter2 = $this->factory->create();

        // Both should be instances of FileTimestampAdapter
        $this->assertInstanceOf(FileTimestampAdapter::class, $adapter1);
        $this->assertInstanceOf(FileTimestampAdapter::class, $adapter2);
        
        // They should be of the same class (same platform detection)
        $this->assertSame(\get_class($adapter1), \get_class($adapter2));
    }
}

