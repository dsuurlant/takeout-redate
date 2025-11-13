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

        // Verify PHAR is executable and can at least show list
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

    public function testPharShowsHelpCommand(): void
    {
        $process = new Process([
            $this->getPhpExecutable(),
            $this->pharPath,
            'help',
        ]);

        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            'PHAR help command failed. Output: ' . $process->getOutput() . "\nError: " . $process->getErrorOutput()
        );
        $this->assertStringContainsString('Display help for a command', $process->getOutput());
    }

    public function testPharShowsHelpWithoutArguments(): void
    {
        $process = new Process([
            $this->getPhpExecutable(),
            $this->pharPath,
        ]);

        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            'PHAR without arguments failed. Output: ' . $process->getOutput() . "\nError: " . $process->getErrorOutput()
        );
        $this->assertStringContainsString('Takeout Redate', $process->getOutput());
        $this->assertStringContainsString('Available commands', $process->getOutput());
    }

    public function testPharExecutesSuccessfully(): void
    {
        $testArchive = $this->copyFixtureToTestDir('test-archive');

        $process = new Process([
            $this->getPhpExecutable(),
            $this->pharPath,
            '--path=' . $testArchive,
        ]);

        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            'PHAR execution failed. Output: ' . $process->getOutput() . "\nError: " . $process->getErrorOutput()
        );
        $this->assertStringContainsString('file(s) processed', $process->getOutput());
    }

    public function testPharPreservesJsonFilesByDefault(): void
    {
        $testArchive = $this->copyFixtureToTestDir('test-no-delete');

        $jsonCountBefore = $this->countJsonFiles($testArchive);
        $this->assertGreaterThan(0, $jsonCountBefore, 'Fixture should contain JSON files');

        $this->runPhar($testArchive);

        $jsonCountAfter = $this->countJsonFiles($testArchive);
        $this->assertSame(
            $jsonCountBefore,
            $jsonCountAfter,
            'JSON files should be preserved by default'
        );
    }

    public function testPharDeletesJsonFilesWithDeleteFlag(): void
    {
        $testArchive = $this->copyFixtureToTestDir('test-delete');

        $jsonCountBefore = $this->countJsonFiles($testArchive);
        $this->assertGreaterThan(0, $jsonCountBefore, 'Fixture should contain JSON files');

        $this->runPhar($testArchive, ['--delete' => true]);

        $jsonCountAfter = $this->countJsonFiles($testArchive);
        $this->assertSame(
            0,
            $jsonCountAfter,
            'JSON files should be deleted when --delete flag is used'
        );
    }

    public function testPharUpdatesFileTimestamps(): void
    {
        $testArchive = $this->copyFixtureToTestDir('test-timestamps');

        // Collect expected timestamps from JSON files before processing
        $expectedTimestamps = $this->collectExpectedTimestamps($testArchive);
        $this->assertNotEmpty($expectedTimestamps, 'Should have expected timestamps from JSON files');

        // Run PHAR without --delete to preserve JSON files for verification (default behavior)
        $this->runPhar($testArchive);

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
        $this->runPhar($testArchive, [], $output);

        $outputString = implode("\n", $output);

        // Verify that files from different directories were processed
        $this->assertStringContainsString('Google Photos', $outputString);
        $this->assertStringContainsString('file(s) processed', $outputString);

        // Count processed files from output
        preg_match_all('/(\d+) file\(s\) processed/', $outputString, $matches);
        $totalProcessed = array_sum(array_map('intval', $matches[1] ?? []));
        $this->assertGreaterThan(0, $totalProcessed, 'Should have processed at least one file');
    }

    public function testPharDryRunDoesNotModifyFiles(): void
    {
        $testArchive = $this->copyFixtureToTestDir('test-dry-run');

        // Collect initial state
        $jsonCountBefore = $this->countJsonFiles($testArchive);
        $this->assertGreaterThan(0, $jsonCountBefore, 'Fixture should contain JSON files');

        $initialTimestamps = $this->collectInitialTimestamps($testArchive);
        $this->assertNotEmpty($initialTimestamps, 'Should have media files to test');

        // Run with --dry-run
        $output = [];
        $this->runPhar($testArchive, ['--dry-run' => true], $output);

        // Verify JSON files are still present
        $jsonCountAfter = $this->countJsonFiles($testArchive);
        $this->assertSame(
            $jsonCountBefore,
            $jsonCountAfter,
            'JSON files should be preserved in dry-run mode'
        );

        // Verify file timestamps were NOT modified
        $errors = [];
        foreach ($initialTimestamps as $mediaFile => $initialTimestamp) {
            if (!file_exists($mediaFile)) {
                continue;
            }

            $currentTimestamp = $this->getFileCreationTimestamp($mediaFile);
            if ($currentTimestamp === null) {
                $errors[] = sprintf('Could not get timestamp for: %s', $mediaFile);
                continue;
            }

            // Timestamps should be exactly the same (no tolerance needed since nothing should change)
            if ($currentTimestamp !== $initialTimestamp) {
                $errors[] = sprintf(
                    'Timestamp was modified for %s: initial %d, current %d (diff: %d)',
                    basename($mediaFile),
                    $initialTimestamp,
                    $currentTimestamp,
                    abs($currentTimestamp - $initialTimestamp)
                );
            }
        }

        $this->assertEmpty($errors, "Dry-run should not modify timestamps:\n" . implode("\n", $errors));

        // Verify output shows files were processed
        $outputString = implode("\n", $output);
        $this->assertStringContainsString('file(s) processed', $outputString);
    }

    public function testPharDryRunDoesNotDeleteJsonFilesEvenWithDeleteFlag(): void
    {
        $testArchive = $this->copyFixtureToTestDir('test-dry-run-delete');

        $jsonCountBefore = $this->countJsonFiles($testArchive);
        $this->assertGreaterThan(0, $jsonCountBefore, 'Fixture should contain JSON files');

        // Run with --dry-run and --delete (should still not delete)
        $this->runPhar($testArchive, ['--dry-run' => true, '--delete' => true]);

        $jsonCountAfter = $this->countJsonFiles($testArchive);
        $this->assertSame(
            $jsonCountBefore,
            $jsonCountAfter,
            'JSON files should be preserved in dry-run mode even with --delete flag'
        );
    }

    /**
     * Run the PHAR with given options.
     *
     * @param string $rootDir Directory path to process
     * @param array<string, bool|string> $options Additional options (e.g., ['--delete' => true])
     * @param array<string> $output Output array (passed by reference)
     */
    private function runPhar(string $rootDir, array $options = [], ?array &$output = null): void
    {
        $command = [
            $this->getPhpExecutable(),
            $this->pharPath,
            '--path=' . $rootDir,
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
     * Collect initial timestamps of media files before processing.
     *
     * @return array<string, int> Map of media file path => initial timestamp
     */
    private function collectInitialTimestamps(string $dir): array
    {
        $timestamps = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $filePath = (string) $file;
            // Skip JSON files
            if (str_ends_with(strtolower($filePath), '.json')) {
                continue;
            }

            // Only collect timestamps for media files (images/videos)
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $mediaExtensions = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'webm'];
            if (!in_array($ext, $mediaExtensions, true)) {
                continue;
            }

            $timestamp = $this->getFileCreationTimestamp($filePath);
            if ($timestamp !== null) {
                $timestamps[$filePath] = $timestamp;
            }
        }

        return $timestamps;
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

