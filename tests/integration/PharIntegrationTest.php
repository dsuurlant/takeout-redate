<?php

declare(strict_types=1);

namespace TakeoutRedate\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use TakeoutRedate\Platform\FileTimestampAdapterFactory;

/**
 * Integration tests for the built PHAR file.
 * 
 * These tests verify that the PHAR correctly processes Google Photos Takeout archives
 * on the actual platform, including file timestamp updates and JSON file handling.
 * 
 * @requires extension phar
 */
class PharIntegrationTest extends TestCase
{
    private string $pharPath;
    private string $fixtureDir;
    private string $testDir;

    protected function setUp(): void
    {
        parent::setUp();

        $projectRoot = \dirname(__DIR__, 2);
        $this->pharPath = $projectRoot . \DIRECTORY_SEPARATOR . 'takeout-redate.phar';
        $this->fixtureDir = __DIR__ . \DIRECTORY_SEPARATOR . 'fixtures' . \DIRECTORY_SEPARATOR . 'takeout-archive';

        if (!file_exists($this->pharPath)) {
            $this->markTestSkipped('PHAR file not found. Build it first with: composer build');
        }

        // Verify PHAR is executable and can at least show help
        $process = new Process([
            $this->getPhpExecutable(),
            $this->pharPath,
            'list',
        ]);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->markTestSkipped(
                'PHAR file exists but appears to be broken. ' .
                'Rebuild it with: composer build. ' .
                'Error: ' . $process->getErrorOutput() . "\n" . $process->getOutput()
            );
        }

        if (!is_dir($this->fixtureDir)) {
            $this->markTestSkipped('Test fixture directory not found: ' . $this->fixtureDir);
        }

        // Create temporary test directory
        $this->testDir = $this->createTempDir();
    }

    protected function tearDown(): void
    {
        if (isset($this->testDir) && is_dir($this->testDir)) {
            $this->cleanupTempDir($this->testDir);
        }

        parent::tearDown();
    }

    public function testPharExecutesSuccessfully(): void
    {
        $testArchive = $this->copyFixtureToTestDir('test-archive');

        $process = new Process([
            $this->getPhpExecutable(),
            $this->pharPath,
            '--root=' . $testArchive,
            '--no-delete',
        ]);

        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            'PHAR execution failed. Output: ' . $process->getOutput() . "\nError: " . $process->getErrorOutput()
        );
        $this->assertStringContainsString('file(s) processed', $process->getOutput());
    }

    public function testPharPreservesJsonFilesWithNoDeleteFlag(): void
    {
        $testArchive = $this->copyFixtureToTestDir('test-no-delete');

        $jsonCountBefore = $this->countJsonFiles($testArchive);
        $this->assertGreaterThan(0, $jsonCountBefore, 'Fixture should contain JSON files');

        $this->runPhar($testArchive, ['--no-delete' => true]);

        $jsonCountAfter = $this->countJsonFiles($testArchive);
        $this->assertSame(
            $jsonCountBefore,
            $jsonCountAfter,
            'JSON files should be preserved when using --no-delete flag'
        );
    }

    public function testPharDeletesJsonFilesWithoutNoDeleteFlag(): void
    {
        $testArchive = $this->copyFixtureToTestDir('test-delete');

        $jsonCountBefore = $this->countJsonFiles($testArchive);
        $this->assertGreaterThan(0, $jsonCountBefore, 'Fixture should contain JSON files');

        $this->runPhar($testArchive);

        $jsonCountAfter = $this->countJsonFiles($testArchive);
        $this->assertSame(
            0,
            $jsonCountAfter,
            'JSON files should be deleted when --no-delete flag is not used'
        );
    }

    public function testPharUpdatesFileTimestamps(): void
    {
        $testArchive = $this->copyFixtureToTestDir('test-timestamps');

        // Collect expected timestamps from JSON files before processing
        $expectedTimestamps = $this->collectExpectedTimestamps($testArchive);
        $this->assertNotEmpty($expectedTimestamps, 'Should have expected timestamps from JSON files');

        // Run PHAR with --no-delete to preserve JSON files for verification
        $this->runPhar($testArchive, ['--no-delete' => true]);

        // Verify timestamps were updated
        $errors = [];
        foreach ($expectedTimestamps as $mediaFile => $expectedTimestamp) {
            if (!file_exists($mediaFile)) {
                continue;
            }

            $actualTimestamp = $this->getFileCreationTimestamp($mediaFile);
            if ($actualTimestamp === null) {
                $errors[] = sprintf('Could not get timestamp for: %s', $mediaFile);
                continue;
            }

            // Allow 2 second tolerance (as per FileTimestampService)
            $diff = abs($actualTimestamp - $expectedTimestamp);
            if ($diff > 2) {
                $errors[] = sprintf(
                    'Timestamp mismatch for %s: expected %d, got %d (diff: %d)',
                    basename($mediaFile),
                    $expectedTimestamp,
                    $actualTimestamp,
                    $diff
                );
            }
        }

        $this->assertEmpty($errors, "Timestamp verification errors:\n" . implode("\n", $errors));
    }

    public function testPharProcessesNestedDirectoryStructure(): void
    {
        $testArchive = $this->copyFixtureToTestDir('test-nested');

        $output = [];
        $this->runPhar($testArchive, ['--no-delete' => true], $output);

        $outputString = implode("\n", $output);

        // Verify that files from different directories were processed
        $this->assertStringContainsString('Google Photos', $outputString);
        $this->assertStringContainsString('file(s) processed', $outputString);

        // Count processed files from output
        preg_match_all('/(\d+) file\(s\) processed/', $outputString, $matches);
        $totalProcessed = array_sum(array_map('intval', $matches[1] ?? []));
        $this->assertGreaterThan(0, $totalProcessed, 'Should have processed at least one file');
    }

    /**
     * Run the PHAR with given options.
     *
     * @param string $rootDir Root directory to process
     * @param array<string, bool|string> $options Additional options (e.g., ['--no-delete' => true])
     * @param array<string> $output Output array (passed by reference)
     */
    private function runPhar(string $rootDir, array $options = [], ?array &$output = null): void
    {
        $command = [
            $this->getPhpExecutable(),
            $this->pharPath,
            '--root=' . $rootDir,
        ];

        foreach ($options as $option => $value) {
            if (is_bool($value) && $value) {
                $command[] = $option;
            } elseif (is_string($value)) {
                $command[] = $option . '=' . $value;
            }
        }

        $process = new Process($command);
        $process->run();

        if ($output !== null) {
            $combinedOutput = trim($process->getOutput() . "\n" . $process->getErrorOutput());
            $output = $combinedOutput !== '' ? explode("\n", $combinedOutput) : [];
        }

        $this->assertTrue(
            $process->isSuccessful(),
            'PHAR execution failed. Command: ' . implode(' ', $command) .
            "\nOutput: " . $process->getOutput() .
            "\nError: " . $process->getErrorOutput()
        );
    }

    /**
     * Copy fixture directory to test directory.
     */
    private function copyFixtureToTestDir(string $name): string
    {
        $target = $this->testDir . \DIRECTORY_SEPARATOR . $name;
        $this->copyDirectory($this->fixtureDir, $target);

        return $target;
    }

    /**
     * Recursively copy a directory.
     */
    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0777, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $destination . \DIRECTORY_SEPARATOR . $iterator->getSubPathName();

            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0777, true);
                }
            } else {
                copy((string) $item, $target);
            }
        }
    }

    /**
     * Count JSON files in a directory.
     */
    private function countJsonFiles(string $dir): int
    {
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with(strtolower((string) $file), '.json')) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Collect expected timestamps from JSON files.
     *
     * @return array<string, int> Map of media file path => expected timestamp (photoTakenTime)
     */
    private function collectExpectedTimestamps(string $dir): array
    {
        $timestamps = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || !str_ends_with(strtolower((string) $file), '.json')) {
                continue;
            }

            $jsonPath = (string) $file;
            $json = json_decode(file_get_contents($jsonPath), true);

            if (!is_array($json)) {
                continue;
            }

            $takenTimestamp = $json['photoTakenTime']['timestamp'] ?? null;
            if ($takenTimestamp === null || !is_numeric($takenTimestamp)) {
                continue;
            }

            // Find corresponding media file
            $mediaFile = $this->findMediaFileForJson($jsonPath);
            if ($mediaFile !== null) {
                $timestamps[$mediaFile] = (int) $takenTimestamp;
            }
        }

        return $timestamps;
    }

    /**
     * Find the media file corresponding to a JSON file.
     */
    private function findMediaFileForJson(string $jsonPath): ?string
    {
        $basePath = substr($jsonPath, 0, -5); // Remove .json extension

        // Try common extensions
        $extensions = ['mp4', 'jpg', 'jpeg', 'png', 'mov', 'gif', 'webm'];
        foreach ($extensions as $ext) {
            $mediaPath = $basePath . '.' . $ext;
            if (file_exists($mediaPath)) {
                return $mediaPath;
            }
        }

        // Try without extension (if JSON was like "file.supplemental-metadata.json")
        $pathInfo = pathinfo($basePath);
        if (isset($pathInfo['extension'])) {
            $mediaPath = $pathInfo['dirname'] . \DIRECTORY_SEPARATOR . $pathInfo['filename'];
            if (file_exists($mediaPath)) {
                return $mediaPath;
            }
        }

        return null;
    }

    /**
     * Get file creation timestamp (Unix epoch) using the platform adapter.
     */
    private function getFileCreationTimestamp(string $filePath): ?int
    {
        $factory = new FileTimestampAdapterFactory();
        $adapter = $factory->create();

        if (!$adapter->isAvailable()) {
            // Fallback to filemtime if adapter is not available
            if (!file_exists($filePath)) {
                return null;
            }
            $mtime = filemtime($filePath);
            return $mtime !== false ? $mtime : null;
        }

        return $adapter->getCreationTime($filePath);
    }

    /**
     * Get PHP executable path.
     */
    private function getPhpExecutable(): string
    {
        $php = \PHP_BINARY;
        if ($php === false || $php === '') {
            $php = 'php';
        }

        return $php;
    }

    private function createTempDir(): string
    {
        $tempDir = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'takeout_phar_test_' . uniqid('', true);
        if (!mkdir($tempDir, 0777, true)) {
            $this->fail('Could not create temporary directory');
        }

        return $tempDir;
    }

    private function cleanupTempDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

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

