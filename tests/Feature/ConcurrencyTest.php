<?php

namespace Durableprogramming\LaravelTypst\Tests\Feature;

use Durableprogramming\LaravelTypst\Facades\Typst;
use Durableprogramming\LaravelTypst\Tests\TestCase;
use Illuminate\Support\Facades\Process;

class ConcurrencyTest extends TestCase
{
    public function test_multiple_simultaneous_compilations(): void
    {
        $sources = [
            '#set page(width: 10cm, height: auto)\n= Document 1',
            '#set page(width: 10cm, height: auto)\n= Document 2',
            '#set page(width: 10cm, height: auto)\n= Document 3',
            '#set page(width: 10cm, height: auto)\n= Document 4',
            '#set page(width: 10cm, height: auto)\n= Document 5',
        ];

        Process::shouldReceive('timeout')
            ->times(count($sources))
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->times(count($sources))
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->times(count($sources))
            ->andReturn($this->createSuccessfulProcess());

        $results = [];

        // Simulate concurrent execution using multiple processes
        $processes = [];
        foreach ($sources as $i => $source) {
            $processes[] = function() use ($source, &$results, $i) {
                $results[$i] = Typst::compile($source);
            };
        }

        // Execute all "concurrently" (in sequence for testing, but simulates concurrent access)
        foreach ($processes as $process) {
            $process();
        }

        $this->assertCount(count($sources), $results);
        foreach ($results as $result) {
            $this->assertStringEndsWith('.pdf', $result);
            $this->assertFileExists($result);
        }

        // Verify all files are different
        $uniqueFiles = array_unique($results);
        $this->assertCount(count($sources), $uniqueFiles);
    }

    public function test_race_conditions_with_shared_temp_directory(): void
    {
        $source = $this->getValidTypstContent();

        Process::shouldReceive('timeout')
            ->times(10)
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->times(10)
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->times(10)
            ->andReturn($this->createSuccessfulProcess());

        $results = [];
        $exceptions = [];

        // Simulate multiple threads/processes accessing the same temp directory
        for ($i = 0; $i < 10; $i++) {
            try {
                $results[] = Typst::compile($source);
            } catch (\Exception $e) {
                $exceptions[] = $e;
            }
        }

        // All compilations should succeed despite concurrent access
        $this->assertCount(10, $results);
        $this->assertEmpty($exceptions);

        foreach ($results as $result) {
            $this->assertStringEndsWith('.pdf', $result);
            $this->assertFileExists($result);
        }
    }

    public function test_concurrent_file_operations_with_different_working_directories(): void
    {
        $workingDirs = [];
        for ($i = 0; $i < 5; $i++) {
            $workingDirs[] = $this->getTestWorkingDirectory() . "/concurrent_{$i}";
        }

        $source = $this->getValidTypstContent();

        Process::shouldReceive('timeout')
            ->times(count($workingDirs))
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->times(count($workingDirs))
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->times(count($workingDirs))
            ->andReturn($this->createSuccessfulProcess());

        $results = [];

        foreach ($workingDirs as $i => $workingDir) {
            Typst::setConfig(['working_directory' => $workingDir]);
            $results[] = Typst::compile($source);
            $this->assertStringContainsString($workingDir, $results[$i]);
        }

        // All results should be in different directories
        $uniqueDirs = array_unique(array_map('dirname', $results));
        $this->assertCount(count($workingDirs), $uniqueDirs);
    }

    public function test_lock_contention_simulation(): void
    {
        $source = $this->getValidTypstContent();

        // Simulate lock contention by having processes that take time
        Process::shouldReceive('timeout')
            ->times(3)
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->times(3)
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->times(3)
            ->andReturnUsing(function () {
                // Simulate some processing time
                usleep(10000); // 10ms
                return $this->createSuccessfulProcess();
            });

        $startTime = microtime(true);

        // Execute multiple compilations that might contend
        $promise1 = Typst::compile($source);
        $promise2 = Typst::compile($source);
        $promise3 = Typst::compile($source);

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        // Should complete in reasonable time (less than 1 second total for 3 operations with 10ms each)
        $this->assertLessThan(1.0, $totalTime);

        $this->assertStringEndsWith('.pdf', $promise1);
        $this->assertStringEndsWith('.pdf', $promise2);
        $this->assertStringEndsWith('.pdf', $promise3);

        $this->assertFileExists($promise1);
        $this->assertFileExists($promise2);
        $this->assertFileExists($promise3);
    }

    public function test_concurrent_config_changes(): void
    {
        $source = $this->getValidTypstContent();

        Process::shouldReceive('timeout')
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->andReturn($this->createSuccessfulProcess());

        $originalConfig = Typst::getConfig();

        // Simulate concurrent config changes
        $configs = [
            ['timeout' => 10],
            ['timeout' => 20],
            ['timeout' => 30],
        ];

        $results = [];
        foreach ($configs as $config) {
            Typst::setConfig($config);
            $results[] = Typst::compile($source);
        }

        // All should succeed
        foreach ($results as $result) {
            $this->assertStringEndsWith('.pdf', $result);
            $this->assertFileExists($result);
        }

        // Config should be set to the last one
        $this->assertEquals(30, Typst::getConfig()['timeout']);
    }

    public function test_memory_usage_under_concurrent_load(): void
    {
        $source = $this->getValidTypstContent();

        Process::shouldReceive('timeout')
            ->times(20)
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->times(20)
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->times(20)
            ->andReturn($this->createSuccessfulProcess());

        $initialMemory = memory_get_usage();

        $results = [];
        for ($i = 0; $i < 20; $i++) {
            $results[] = Typst::compile($source);
        }

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory increase should be reasonable (less than 50MB for 20 compilations)
        $this->assertLessThan(50 * 1024 * 1024, $memoryIncrease);

        $this->assertCount(20, $results);
        foreach ($results as $result) {
            $this->assertFileExists($result);
        }
    }

    private function createSuccessfulProcess(): object
    {
        return new class
        {
            public function successful(): bool
            {
                return true;
            }

            public function output(): string
            {
                return 'Mock compilation successful';
            }

            public function errorOutput(): string
            {
                return '';
            }

            public function exitCode(): int
            {
                return 0;
            }
        };
    }
}