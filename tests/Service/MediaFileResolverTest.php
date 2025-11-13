<?php

declare(strict_types=1);

namespace TakeoutRedate\Tests\Service;

use PHPUnit\Framework\TestCase;
use TakeoutRedate\Service\MediaFileResolver;

class MediaFileResolverTest extends TestCase
{
    private MediaFileResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new MediaFileResolver();
    }

    public function testDerivePrefixReturnsArrayWithTwoElements(): void
    {
        $result = $this->resolver->derivePrefix('/path/to/file.json');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertIsString($result[0]);
        $this->assertIsString($result[1]);
    }

    public function testDerivePrefixExtractsDirectoryAndFilename(): void
    {
        $result = $this->resolver->derivePrefix('/path/to/image.jpg.json');

        $this->assertSame('/path/to', $result[0]);
        $this->assertSame('image.jpg', $result[1]);
    }

    public function testDerivePrefixHandlesJsonExtension(): void
    {
        $result = $this->resolver->derivePrefix('/dir/photo.JPG.json');

        $this->assertSame('/dir', $result[0]);
        $this->assertSame('photo.JPG', $result[1]);
    }

    public function testDerivePrefixHandlesFileWithoutJsonExtension(): void
    {
        $result = $this->resolver->derivePrefix('/dir/photo.jpg');

        $this->assertSame('/dir', $result[0]);
        // When there's no .json extension, pathinfo extracts just the filename without extension
        $this->assertSame('photo', $result[1]);
    }

    public function testDerivePrefixTruncatesSuffixAfterExtension(): void
    {
        $result = $this->resolver->derivePrefix('/dir/image.jpg.something.json');

        $this->assertSame('/dir', $result[0]);
        // Should extract "image.jpg" and ignore ".something"
        $this->assertSame('image.jpg', $result[1]);
    }

    public function testDerivePrefixHandlesThreeCharExtension(): void
    {
        $result = $this->resolver->derivePrefix('/dir/file.gif.json');

        $this->assertSame('/dir', $result[0]);
        $this->assertSame('file.gif', $result[1]);
    }

    public function testDerivePrefixHandlesFourCharExtension(): void
    {
        $result = $this->resolver->derivePrefix('/dir/file.webm.json');

        $this->assertSame('/dir', $result[0]);
        $this->assertSame('file.webm', $result[1]);
    }

    public function testDerivePrefixHandlesWindowsPath(): void
    {
        // Use forward slashes or normalize path - dirname works differently on Windows
        $result = $this->resolver->derivePrefix('C:/path/to/file.jpg.json');

        // dirname will extract the directory part
        $this->assertIsString($result[0]);
        $this->assertSame('file.jpg', $result[1]);
    }

    public function testResolveMediaPathReturnsExactMatch(): void
    {
        $tempDir = $this->createTempDir();
        $mediaFile = $tempDir . \DIRECTORY_SEPARATOR . 'image.jpg';
        file_put_contents($mediaFile, 'content');

        try {
            $result = $this->resolver->resolveMediaPath($tempDir, 'image.jpg');

            $this->assertSame($mediaFile, $result);
        } finally {
            $this->cleanupTempDir($tempDir);
        }
    }

    public function testResolveMediaPathReturnsNullWhenNotFound(): void
    {
        $tempDir = $this->createTempDir();

        try {
            $result = $this->resolver->resolveMediaPath($tempDir, 'nonexistent.jpg');

            $this->assertNull($result);
        } finally {
            $this->cleanupTempDir($tempDir);
        }
    }

    public function testResolveMediaPathIgnoresJsonFiles(): void
    {
        $tempDir = $this->createTempDir();
        $jsonFile = $tempDir . \DIRECTORY_SEPARATOR . 'image.jpg.json';
        file_put_contents($jsonFile, '{}');

        try {
            $result = $this->resolver->resolveMediaPath($tempDir, 'image.jpg');

            $this->assertNull($result);
        } finally {
            $this->cleanupTempDir($tempDir);
        }
    }

    public function testResolveMediaPathMatchesByStemWithDifferentExtension(): void
    {
        $tempDir = $this->createTempDir();
        $mediaFile = $tempDir . \DIRECTORY_SEPARATOR . 'image.png';
        file_put_contents($mediaFile, 'content');

        try {
            $result = $this->resolver->resolveMediaPath($tempDir, 'image.jpg');

            // Should find image.png as it matches the stem
            $this->assertSame($mediaFile, $result);
        } finally {
            $this->cleanupTempDir($tempDir);
        }
    }

    public function testResolveMediaPathSelectsShortestFilenameWhenMultipleMatches(): void
    {
        $tempDir = $this->createTempDir();
        $longFile = $tempDir . \DIRECTORY_SEPARATOR . 'image-long.jpg';
        $shortFile = $tempDir . \DIRECTORY_SEPARATOR . 'image.jpg';
        file_put_contents($longFile, 'content');
        file_put_contents($shortFile, 'content');

        try {
            $result = $this->resolver->resolveMediaPath($tempDir, 'image.jpg');

            // Should prefer the shorter filename
            $this->assertSame($shortFile, $result);
        } finally {
            $this->cleanupTempDir($tempDir);
        }
    }

    public function testResolveMediaPathReturnsNullForNonExistentDirectory(): void
    {
        $result = $this->resolver->resolveMediaPath('/nonexistent/directory', 'file.jpg');

        $this->assertNull($result);
    }

    public function testResolveMediaPathHandlesExtensionsCaseInsensitively(): void
    {
        $tempDir = $this->createTempDir();
        $mediaFile = $tempDir . \DIRECTORY_SEPARATOR . 'image.JPG';
        file_put_contents($mediaFile, 'content');

        try {
            // The resolver matches by stem (filename without extension) case-insensitively
            // and accepts any valid extension, so it should find the file
            $result = $this->resolver->resolveMediaPath($tempDir, 'image.jpg');

            // The resolver should find the file by matching the stem
            // On case-insensitive filesystems (like macOS), is_file() may match but return
            // the requested path case rather than the actual file case
            $this->assertNotNull($result);
            // Verify the file exists and points to the same file (case may differ on case-insensitive FS)
            $this->assertFileExists($result);
            // Compare file inodes to ensure they're the same file (works across case differences)
            $expectedInode = fileinode($mediaFile);
            $actualInode = fileinode($result);
            $this->assertSame($expectedInode, $actualInode, 'Resolved path should point to the same file');
        } finally {
            $this->cleanupTempDir($tempDir);
        }
    }

    public function testResolveMediaPathTrimsSuffixFromPrefixName(): void
    {
        $tempDir = $this->createTempDir();
        $mediaFile = $tempDir . \DIRECTORY_SEPARATOR . 'image.jpg';
        file_put_contents($mediaFile, 'content');

        try {
            // Prefix name has suffix that should be trimmed
            $result = $this->resolver->resolveMediaPath($tempDir, 'image.jpg.extra');

            $this->assertSame($mediaFile, $result);
        } finally {
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
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
        }
        if (is_dir($dir)) {
            rmdir($dir);
        }
    }
}

