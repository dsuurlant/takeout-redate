<?php

declare(strict_types=1);

namespace TakeoutRedate\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use TakeoutRedate\Command\TakeoutRedateCommand;
use TakeoutRedate\Platform\FileTimestampAdapter;
use TakeoutRedate\Platform\FileTimestampAdapterFactory;
use TakeoutRedate\Service\FileTimestampService;
use TakeoutRedate\Service\MediaFileResolver;
use TakeoutRedate\Service\TakeoutJsonReader;

class TakeoutRedateCommandTest extends TestCase
{
    private FileTimestampAdapterFactory $adapterFactory;
    private TakeoutJsonReader $jsonReader;
    private MediaFileResolver $mediaResolver;
    private TakeoutRedateCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->adapterFactory = $this->createMock(FileTimestampAdapterFactory::class);
        $this->jsonReader = $this->createMock(TakeoutJsonReader::class);
        $this->mediaResolver = $this->createMock(MediaFileResolver::class);

        $this->command = new TakeoutRedateCommand(
            $this->adapterFactory,
            $this->jsonReader,
            $this->mediaResolver
        );

        $app = new Application();
        $app->add($this->command);
        $this->commandTester = new CommandTester($this->command);
    }

    public function testCommandHasCorrectName(): void
    {
        $this->assertSame('takeout:restore-imagedates', $this->command->getName());
    }

    public function testCommandHasAlias(): void
    {
        $this->assertSame(['imgdates'], $this->command->getAliases());
    }

    public function testCommandFailsWhenRootDirectoryDoesNotExist(): void
    {
        $this->commandTester->execute([
            '--root' => '/nonexistent/directory/path',
        ]);

        $this->assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
        $this->assertStringContainsString('Root directory not found', $this->commandTester->getDisplay());
    }

    public function testCommandFailsWhenAdapterNotAvailableInNonDryRunMode(): void
    {
        $tempDir = $this->createTempDir();
        
        try {
            $adapter = $this->createMock(FileTimestampAdapter::class);
            $adapter->method('isAvailable')->willReturn(false);
            $this->adapterFactory->method('create')->willReturn($adapter);

            $this->commandTester->execute([
                '--root' => $tempDir,
            ]);

            $this->assertSame(Command::FAILURE, $this->commandTester->getStatusCode());
            $this->assertStringContainsString('File timestamp adapter not available', $this->commandTester->getDisplay());
        } finally {
            $this->cleanupTempDir($tempDir);
        }
    }

    public function testCommandSucceedsWhenAdapterNotAvailableInDryRunMode(): void
    {
        $tempDir = $this->createTempDir();
        
        try {
            $adapter = $this->createMock(FileTimestampAdapter::class);
            $adapter->method('isAvailable')->willReturn(false);
            $this->adapterFactory->method('create')->willReturn($adapter);

            $this->commandTester->execute([
                '--root' => $tempDir,
                '--dry-run' => true,
            ]);

            $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        } finally {
            $this->cleanupTempDir($tempDir);
        }
    }

    public function testCommandProcessesJsonFiles(): void
    {
        $tempDir = $this->createTempDir();
        $jsonFile = $tempDir . \DIRECTORY_SEPARATOR . 'image.jpg.json';
        $mediaFile = $tempDir . \DIRECTORY_SEPARATOR . 'image.jpg';
        
        file_put_contents($jsonFile, '{}');
        file_put_contents($mediaFile, 'content');

        try {
            $adapter = $this->createMock(FileTimestampAdapter::class);
            $adapter->method('isAvailable')->willReturn(true);
            $adapter->method('getCreationTime')->willReturn(1000);
            $adapter->method('getModificationTime')->willReturn(2000);
            $this->adapterFactory->method('create')->willReturn($adapter);

            $this->jsonReader
                ->method('readTimestamps')
                ->willReturn([1000, 2000]);

            $this->mediaResolver
                ->method('derivePrefix')
                ->willReturn([$tempDir, 'image.jpg']);

            $this->mediaResolver
                ->method('resolveMediaPath')
                ->willReturn($mediaFile);

            $this->commandTester->execute([
                '--root' => $tempDir,
                '--dry-run' => true,
            ]);

            $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
            $this->assertStringContainsString('file(s) processed', $this->commandTester->getDisplay());
        } finally {
            $this->cleanupTempDir($tempDir);
        }
    }

    public function testCommandIgnoresNonJsonFiles(): void
    {
        $tempDir = $this->createTempDir();
        $textFile = $tempDir . \DIRECTORY_SEPARATOR . 'file.txt';
        file_put_contents($textFile, 'content');

        try {
            $adapter = $this->createMock(FileTimestampAdapter::class);
            $adapter->method('isAvailable')->willReturn(true);
            $this->adapterFactory->method('create')->willReturn($adapter);

            $this->commandTester->execute([
                '--root' => $tempDir,
                '--dry-run' => true,
            ]);

            $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
            // Should process 0 files
            $this->assertStringNotContainsString('file(s) processed', $this->commandTester->getDisplay());
        } finally {
            $this->cleanupTempDir($tempDir);
        }
    }

    public function testCommandHandlesJsonFilesWithoutMatchingMedia(): void
    {
        $tempDir = $this->createTempDir();
        $jsonFile = $tempDir . \DIRECTORY_SEPARATOR . 'image.jpg.json';
        file_put_contents($jsonFile, '{}');

        try {
            $adapter = $this->createMock(FileTimestampAdapter::class);
            $adapter->method('isAvailable')->willReturn(true);
            $this->adapterFactory->method('create')->willReturn($adapter);

            $this->jsonReader
                ->method('readTimestamps')
                ->willReturn([1000, 2000]);

            $this->mediaResolver
                ->method('derivePrefix')
                ->willReturn([$tempDir, 'image.jpg']);

            $this->mediaResolver
                ->method('resolveMediaPath')
                ->willReturn(null); // Media file not found

            $this->commandTester->execute([
                '--root' => $tempDir,
                '--dry-run' => true,
            ]);

            $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        } finally {
            $this->cleanupTempDir($tempDir);
        }
    }

    public function testCommandUsesDefaultRootDirectory(): void
    {
        $tempDir = $this->createTempDir();
        $originalCwd = getcwd();
        
        try {
            chdir($tempDir);
            
            $adapter = $this->createMock(FileTimestampAdapter::class);
            $adapter->method('isAvailable')->willReturn(true);
            $this->adapterFactory->method('create')->willReturn($adapter);

            // Mock the services to avoid file system operations
            $this->jsonReader
                ->method('readTimestamps')
                ->willReturn([null, null]);

            $this->mediaResolver
                ->method('derivePrefix')
                ->willReturn([$tempDir, 'test']);

            $this->mediaResolver
                ->method('resolveMediaPath')
                ->willReturn(null);

            $this->commandTester->execute([
                '--dry-run' => true,
            ]);

            $this->assertSame(Command::SUCCESS, $this->commandTester->getStatusCode());
        } finally {
            chdir($originalCwd);
            $this->cleanupTempDir($tempDir);
        }
    }

    private function createTempDir(): string
    {
        $tempDir = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'takeout_test_' . uniqid('', true);
        if (!mkdir($tempDir, 0777, true)) {
            $this->fail('Could not create temporary directory');
        }

        return $tempDir;
    }

    private function cleanupTempDir(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $filePath = $dir . \DIRECTORY_SEPARATOR . $file;
            if (is_dir($filePath)) {
                $this->cleanupTempDir($filePath);
            } else {
                @unlink($filePath);
            }
        }
        @rmdir($dir);
    }
}

