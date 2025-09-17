<?php

namespace Durableprogramming\LaravelTypst\Tests;

use Durableprogramming\LaravelTypst\TypstServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTestEnvironment();
        $this->createTestDirectories();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestFiles();
        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            TypstServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Typst' => \Durableprogramming\LaravelTypst\Facades\Typst::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('typst.bin_path', $this->getTestTypstPath());
        $app['config']->set('typst.working_directory', $this->getTestWorkingDirectory());
        $app['config']->set('typst.timeout', 30);
        $app['config']->set('typst.format', 'pdf');
        $app['config']->set('typst.font_paths', []);
        $app['config']->set('typst.root', null);
    }

    protected function getTestTypstPath(): string
    {
        return 'mock-typst';
    }

    protected function getTestWorkingDirectory(): string
    {
        return __DIR__.'/temp';
    }

    protected function setUpTestEnvironment(): void
    {
        putenv('TYPST_BIN_PATH='.$this->getTestTypstPath());
        putenv('TYPST_WORKING_DIR='.$this->getTestWorkingDirectory());
    }

    protected function createTestDirectories(): void
    {
        $workingDir = $this->getTestWorkingDirectory();
        if (! is_dir($workingDir)) {
            mkdir($workingDir, 0755, true);
        }
    }

    protected function cleanupTestFiles(): void
    {
        $workingDir = $this->getTestWorkingDirectory();
        if (is_dir($workingDir)) {
            $this->recursiveRemoveDirectory($workingDir);
        }
    }

    protected function recursiveRemoveDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    protected function createMockTypstBinary(): string
    {
        $mockScript = '#!/bin/bash
if [ "$1" = "compile" ]; then
    if [ -f "$2" ]; then
        echo "Mock compilation successful" > "$3"
        echo "Compiled: $2 -> $3" >&2
        exit 0
    else
        echo "File not found: $2" >&2
        exit 1
    fi
fi
echo "Unknown command" >&2
exit 1';

        $mockPath = $this->getTestWorkingDirectory().'/mock-typst';
        file_put_contents($mockPath, $mockScript);
        chmod($mockPath, 0755);

        return $mockPath;
    }

    protected function getValidTypstContent(): string
    {
        return '#set page(width: 10cm, height: auto)
= Hello World
This is a test document.';
    }

    protected function getInvalidTypstContent(): string
    {
        return '#invalid-syntax {
= Broken Document';
    }

    protected function assertFileContainsString(string $expectedString, string $filePath, string $message = ''): void
    {
        $this->assertFileExists($filePath, $message);
        $content = file_get_contents($filePath);
        $this->assertStringContainsString($expectedString, $content, $message);
    }
}
