<?php

namespace Durableprogramming\LaravelTypst\Tests\Unit;

use Durableprogramming\LaravelTypst\Facades\Typst;
use Durableprogramming\LaravelTypst\Tests\TestCase;
use Illuminate\Support\Facades\Process;

class PerformanceTest extends TestCase
{
    public function test_memory_usage_during_compilation(): void
    {
        $source = str_repeat($this->getValidTypstContent(), 10);

        $initialMemory = memory_get_usage();

        Process::shouldReceive('timeout')
            ->once()
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->once()
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->once()
            ->andReturn($this->createSuccessfulProcess());

        Typst::compile($source);

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;

        $this->assertLessThan(50 * 1024 * 1024, $memoryIncrease, 'Memory usage increased by more than 50MB');
    }

    public function test_concurrent_compilation_performance(): void
    {
        $sources = array_fill(0, 5, $this->getValidTypstContent());

        Process::shouldReceive('timeout')
            ->times(5)
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->times(5)
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->times(5)
            ->andReturn($this->createSuccessfulProcess());

        $startTime = microtime(true);

        foreach ($sources as $source) {
            Typst::compile($source);
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        $this->assertLessThan(30, $totalTime, 'Sequential compilations took more than 30 seconds');
    }

    public function test_large_document_handling(): void
    {
        $largeSource = '#set page(width: 10cm, height: auto)\n';
        $largeSource .= str_repeat("= Section\n".str_repeat('Lorem ipsum dolor sit amet. ', 50)."\n\n", 50);

        Process::shouldReceive('timeout')
            ->once()
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->once()
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->once()
            ->andReturnUsing(function ($command) {
                // Extract the output file from the command and create it
                $outputFile = $command[3]; // outputFile is the 4th argument
                file_put_contents($outputFile, 'Mock PDF content for large document');

                return $this->createSuccessfulProcess();
            });

        $startTime = microtime(true);
        $outputFile = Typst::compile($largeSource);
        $endTime = microtime(true);

        $compilationTime = $endTime - $startTime;

        $this->assertFileExists($outputFile);
        $this->assertLessThan(10, $compilationTime, 'Large document compilation took more than 10 seconds');
    }

    public function test_temp_file_creation_performance(): void
    {
        $source = $this->getValidTypstContent();
        $iterations = 20;

        Process::shouldReceive('timeout')
            ->times($iterations)
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->times($iterations)
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->times($iterations)
            ->andReturn($this->createSuccessfulProcess());

        $startTime = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            Typst::compile($source);
        }

        $endTime = microtime(true);
        $averageTime = ($endTime - $startTime) / $iterations;

        $this->assertLessThan(1.0, $averageTime, 'Average compilation time exceeded 1 second');
    }

    public function test_memory_cleanup_efficiency(): void
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

        $initialMemory = memory_get_usage();

        for ($i = 0; $i < 10; $i++) {
            Typst::compile($source);

            if ($i % 3 === 0) {
                gc_collect_cycles();
            }
        }

        gc_collect_cycles();
        $finalMemory = memory_get_usage();
        $memoryDifference = abs($finalMemory - $initialMemory);

        $this->assertLessThan(10 * 1024 * 1024, $memoryDifference, 'Memory not properly cleaned up after multiple compilations');
    }

    public function test_configuration_access_performance(): void
    {
        $iterations = 1000;

        $startTime = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $config = Typst::getConfig();
            $this->assertIsArray($config);
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        $this->assertLessThan(0.25, $totalTime, 'Configuration access is too slow');
    }

    public function test_facade_resolution_performance(): void
    {
        $iterations = 1000;

        $startTime = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $service = Typst::getFacadeRoot();
            $this->assertNotNull($service);
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        $this->assertLessThan(0.5, $totalTime, 'Facade resolution is too slow');
    }

    public function test_string_compilation_vs_file_compilation_performance(): void
    {
        $source = $this->getValidTypstContent();
        $inputFile = $this->getTestWorkingDirectory().'/input.typ';
        file_put_contents($inputFile, $source);

        Process::shouldReceive('timeout')
            ->times(2)
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->times(2)
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->times(2)
            ->andReturn($this->createSuccessfulProcess());

        $stringStart = microtime(true);
        Typst::compile($source);
        $stringEnd = microtime(true);
        $stringTime = $stringEnd - $stringStart;

        $fileStart = microtime(true);
        Typst::compileFile($inputFile);
        $fileEnd = microtime(true);
        $fileTime = $fileEnd - $fileStart;

        $this->assertLessThan($stringTime * 2, $fileTime, 'File compilation significantly slower than string compilation');
    }

    public function test_working_directory_operations_performance(): void
    {
        $source = $this->getValidTypstContent();

        Process::shouldReceive('timeout')
            ->times(5)
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->times(5)
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->times(5)
            ->andReturn($this->createSuccessfulProcess());

        $startTime = microtime(true);

        for ($i = 0; $i < 5; $i++) {
            $workingDir = $this->getTestWorkingDirectory()."/test_{$i}";
            Typst::setConfig(['working_directory' => $workingDir]);
            Typst::compile($source);
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        $this->assertLessThan(15, $totalTime, 'Working directory operations are too slow');
    }

    public function test_service_instantiation_performance(): void
    {
        $iterations = 100;

        $startTime = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            $service = new \Durableprogramming\LaravelTypst\TypstService([
                'working_directory' => $this->getTestWorkingDirectory()."/perf_{$i}",
                'timeout' => 30,
            ]);
            $this->assertNotNull($service);
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;

        $this->assertLessThan(1.0, $totalTime, 'Service instantiation is too slow');
    }

    public function test_memory_limits_under_extreme_load(): void
    {
        $source = $this->getValidTypstContent();

        Process::shouldReceive('timeout')
            ->times(50)
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->times(50)
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->times(50)
            ->andReturn($this->createSuccessfulProcess());

        $initialMemory = memory_get_usage();
        $peakMemory = $initialMemory;

        for ($i = 0; $i < 50; $i++) {
            Typst::compile($source);
            $currentMemory = memory_get_usage();
            $peakMemory = max($peakMemory, $currentMemory);

            // Force garbage collection periodically
            if ($i % 10 === 0) {
                gc_collect_cycles();
            }
        }

        $memoryIncrease = $peakMemory - $initialMemory;

        // Memory usage should stay reasonable even under load
        $this->assertLessThan(100 * 1024 * 1024, $memoryIncrease, 'Memory usage exceeded 100MB under load');
    }

    public function test_timeout_edge_cases(): void
    {
        $source = $this->getValidTypstContent();

        // Test with very short timeout
        Typst::setConfig(['timeout' => 1]);

        Process::shouldReceive('timeout')
            ->once()
            ->with(1)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->once()
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->once()
            ->andReturn($this->createSuccessfulProcess());

        $result = Typst::compile($source);
        $this->assertFileExists($result);

        // Test with very long timeout
        Typst::setConfig(['timeout' => 3600]); // 1 hour

        Process::shouldReceive('timeout')
            ->once()
            ->with(3600)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->once()
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->once()
            ->andReturn($this->createSuccessfulProcess());

        $result = Typst::compile($source);
        $this->assertFileExists($result);
    }

    public function test_resource_exhaustion_prevention(): void
    {
        // Test with extremely large data arrays for Blade processing
        $largeData = [];
        for ($i = 0; $i < 10000; $i++) {
            $largeData["var{$i}"] = str_repeat('x', 1000); // 1KB per value
        }

        $template = '@foreach($data as $key => $value)
{{ $key }}: {{ Str::limit($value, 10) }}
@endforeach';

        $data = $largeData;

        Process::shouldReceive('timeout')
            ->once()
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->once()
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->once()
            ->andReturn($this->createSuccessfulProcess());

        $startTime = microtime(true);
        $initialMemory = memory_get_usage();

        $result = Typst::compile($template, $data);

        $endTime = microtime(true);
        $finalMemory = memory_get_usage();

        $this->assertFileExists($result);
        $this->assertLessThan(30, $endTime - $startTime, 'Large data processing took too long');
        $this->assertLessThan(200 * 1024 * 1024, $finalMemory - $initialMemory, 'Memory usage too high for large data');
    }

    public function test_very_fast_operations_performance(): void
    {
        $source = '#set page(width: 1cm, height: 1cm)
= Test';

        Process::shouldReceive('timeout')
            ->times(100)
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->times(100)
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->times(100)
            ->andReturnUsing(function () {
                // Simulate very fast processing
                usleep(1000); // 1ms
                return $this->createSuccessfulProcess();
            });

        $startTime = microtime(true);

        for ($i = 0; $i < 100; $i++) {
            $result = Typst::compile($source);
            $this->assertFileExists($result);
        }

        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        $avgTime = $totalTime / 100;

        // Average time should be very low for simple documents
        $this->assertLessThan(0.1, $avgTime, 'Very fast operations are too slow');
    }

    public function test_compilation_pipeline_efficiency(): void
    {
        $complexTemplate = '#import "complex.typ" : *
#let data = ({{ $complexData }})
#let processed = data.map(item => item.value * 2)

#for item in processed [
  - #item.value
]';

        $complexData = array_fill(0, 100, ['value' => 1, 'name' => 'test']);

        $service = new \Durableprogramming\LaravelTypst\TypstService(['working_directory' => $this->getTestWorkingDirectory()]);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('arrayToTypstValue');
        $method->setAccessible(true);
        $complexDataString = $method->invoke($service, $complexData);

        Process::shouldReceive('timeout')
            ->once()
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->once()
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->once()
            ->andReturn($this->createSuccessfulProcess());

        $startTime = microtime(true);
        $initialMemory = memory_get_usage();

        $result = Typst::compile($complexTemplate, ['complexData' => $complexDataString]);

        $endTime = microtime(true);
        $finalMemory = memory_get_usage();

        $this->assertFileExists($result);
        $this->assertLessThan(5, $endTime - $startTime, 'Complex pipeline processing took too long');
        $this->assertLessThan(50 * 1024 * 1024, $finalMemory - $initialMemory, 'Memory usage too high for complex pipeline');
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
