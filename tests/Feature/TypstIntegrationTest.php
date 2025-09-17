<?php

namespace Durableprogramming\LaravelTypst\Tests\Feature;

use Durableprogramming\LaravelTypst\Exceptions\TypstCompilationException;
use Durableprogramming\LaravelTypst\Facades\Typst;
use Durableprogramming\LaravelTypst\Tests\TestCase;
use Illuminate\Support\Facades\Process;

class TypstIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createMockTypstBinary();
    }

    public function test_end_to_end_compilation_with_facade(): void
    {
        $source = $this->getValidTypstContent();

        $this->mockSuccessfulTypstProcess();

        $outputFile = Typst::compile($source);

        $this->assertStringEndsWith('.pdf', $outputFile);
        $this->assertStringContainsString($this->getTestWorkingDirectory(), $outputFile);
        $this->assertFileExists($outputFile);
        $this->assertFileContainsString('Mock compilation successful', $outputFile);
    }

    public function test_compile_to_string_integration(): void
    {
        $source = $this->getValidTypstContent();

        $this->mockSuccessfulTypstProcess();

        $content = Typst::compileToString($source);

        $this->assertStringContainsString('Mock compilation successful', $content);
    }

    public function test_compile_file_integration(): void
    {
        $inputFile = $this->getTestWorkingDirectory().'/input.typ';
        $outputFile = $this->getTestWorkingDirectory().'/output.pdf';

        file_put_contents($inputFile, $this->getValidTypstContent());

        $this->mockSuccessfulTypstProcess();

        $result = Typst::compileFile($inputFile, [], $outputFile);

        $this->assertEquals($outputFile, $result);
        $this->assertFileExists($outputFile);
        $this->assertFileContainsString('Mock compilation successful', $outputFile);
    }

    public function test_compilation_with_custom_options(): void
    {
        $source = $this->getValidTypstContent();
        $rootPath = $this->getTestWorkingDirectory();
        $fontPaths = ['/custom/fonts'];

        Process::shouldReceive('timeout')
            ->once()
            ->with(30)
            ->andReturn(Process::getFacadeRoot());

        Process::shouldReceive('path')
            ->once()
            ->with($this->getTestWorkingDirectory())
            ->andReturn(Process::getFacadeRoot());

        Process::shouldReceive('run')
            ->once()
            ->with(\Mockery::on(function ($command) use ($rootPath, $fontPaths) {
                $commandStr = implode(' ', $command);

                return strpos($commandStr, '--root '.$rootPath) !== false &&
                       strpos($commandStr, '--font-path '.$fontPaths[0]) !== false;
            }))
            ->andReturn($this->createMockSuccessfulProcess());

        $outputFile = Typst::compile($source, [], [
            'root' => $rootPath,
            'font_paths' => $fontPaths,
            'format' => 'png',
        ]);

        $this->assertStringEndsWith('.png', $outputFile);
    }

    public function test_multiple_sequential_compilations(): void
    {
        $source1 = '#set page(width: 10cm, height: auto)\n= Document 1';
        $source2 = '#set page(width: 10cm, height: auto)\n= Document 2';
        $source3 = '#set page(width: 10cm, height: auto)\n= Document 3';

        $this->mockMultipleSuccessfulProcesses(3);

        $output1 = Typst::compile($source1);
        $output2 = Typst::compile($source2);
        $output3 = Typst::compile($source3);

        $this->assertNotEquals($output1, $output2);
        $this->assertNotEquals($output2, $output3);
        $this->assertNotEquals($output1, $output3);

        $this->assertFileExists($output1);
        $this->assertFileExists($output2);
        $this->assertFileExists($output3);
    }

    public function test_compilation_with_different_formats(): void
    {
        $source = $this->getValidTypstContent();
        $formats = ['pdf', 'png', 'svg'];

        $this->mockMultipleSuccessfulProcesses(count($formats));

        foreach ($formats as $format) {
            $outputFile = Typst::compile($source, [], ['format' => $format]);
            $this->assertStringEndsWith('.'.$format, $outputFile);
            $this->assertFileExists($outputFile);
        }
    }

    public function test_error_handling_integration(): void
    {
        $source = $this->getInvalidTypstContent();

        $this->mockFailedTypstProcess();

        $this->expectException(TypstCompilationException::class);
        $this->expectExceptionMessage('Mock compilation error');

        Typst::compile($source);
    }

    public function test_configuration_override_integration(): void
    {
        $customTimeout = 45;

        Typst::setConfig(['timeout' => $customTimeout]);

        Process::shouldReceive('timeout')
            ->once()
            ->with($customTimeout)
            ->andReturn(Process::getFacadeRoot());

        Process::shouldReceive('path')
            ->once()
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->once()
            ->andReturn($this->createMockSuccessfulProcess());

        $source = $this->getValidTypstContent();
        Typst::compile($source);

        $config = Typst::getConfig();
        $this->assertEquals($customTimeout, $config['timeout']);
    }

    public function test_temp_file_cleanup_integration(): void
    {
        $source = $this->getValidTypstContent();

        // Clean up any existing temp files first
        $existingFiles = glob($this->getTestWorkingDirectory().'/typst_*');
        foreach ($existingFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        // Test temp file cleanup functionality directly
        $workingDir = $this->getTestWorkingDirectory();

        // Create a service to test
        $service = new \Durableprogramming\LaravelTypst\TypstService(['working_directory' => $workingDir]);

        // Use reflection to access protected methods
        $reflection = new \ReflectionClass($service);

        $createTempMethod = $reflection->getMethod('createTempFile');
        $createTempMethod->setAccessible(true);

        $cleanupMethod = $reflection->getMethod('cleanupTempFile');
        $cleanupMethod->setAccessible(true);

        // Create a temp file
        $tempFile = $createTempMethod->invoke($service, $source);
        $this->assertFileExists($tempFile);
        $this->assertStringContainsString('typst_', basename($tempFile));

        // Clean it up
        $cleanupMethod->invoke($service, $tempFile);
        $this->assertFileDoesNotExist($tempFile);

        // Verify no temp files remain
        $tempFiles = glob($workingDir.'/typst_*');
        if (! empty($tempFiles)) {
            $this->fail('Temp files were not cleaned up properly: '.implode(', ', $tempFiles));
        }
        $this->assertEmpty($tempFiles, 'Temp files were not cleaned up properly');
    }

    public function test_working_directory_permissions(): void
    {
        $workingDir = $this->getTestWorkingDirectory().'/test_permissions';

        // Ensure the directory is created first
        if (! is_dir($workingDir)) {
            mkdir($workingDir, 0755, true);
        }

        Typst::setConfig(['working_directory' => $workingDir]);

        $this->assertDirectoryExists($workingDir);
        $this->assertTrue(is_writable($workingDir));

        // Mock process for the custom working directory
        Process::shouldReceive('timeout')
            ->once()
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->once()
            ->with($workingDir)
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->once()
            ->andReturnUsing(function ($command) {
                // Extract the output file from the command and create it
                $outputFile = $command[3]; // outputFile is the 4th argument
                file_put_contents($outputFile, 'Mock compilation successful');

                return $this->createMockSuccessfulProcess();
            });

        $source = $this->getValidTypstContent();
        $outputFile = Typst::compile($source);

        $this->assertStringContainsString($workingDir, $outputFile);
    }

    public function test_large_document_compilation(): void
    {
        $largeSource = '#set page(width: 10cm, height: auto)\n';
        $largeSource .= str_repeat("= Section\nLorem ipsum dolor sit amet.\n\n", 100);

        $this->mockSuccessfulTypstProcess();

        $outputFile = Typst::compile($largeSource);

        $this->assertFileExists($outputFile);
        $this->assertGreaterThan(0, filesize($outputFile));
    }

    protected function mockSuccessfulTypstProcess(): void
    {
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
                // Extract the output file from the command
                $outputFile = $command[3]; // outputFile is the 4th argument

                // Create the expected output file
                file_put_contents($outputFile, 'Mock compilation successful');

                return $this->createMockSuccessfulProcess();
            });
    }

    protected function mockFailedTypstProcess(): void
    {
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
            ->andReturn($this->createMockFailedProcess());
    }

    protected function mockMultipleSuccessfulProcesses(int $count): void
    {
        Process::shouldReceive('timeout')
            ->times($count)
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->times($count)
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->times($count)
            ->andReturnUsing(function ($command) {
                // Extract the output file from the command
                $outputFile = $command[3]; // outputFile is the 4th argument

                // Create the expected output file
                file_put_contents($outputFile, 'Mock compilation successful');

                return $this->createMockSuccessfulProcess();
            });
    }

    protected function createMockSuccessfulProcess(): object
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

    protected function createMockFailedProcess(): object
    {
        return new class
        {
            public function successful(): bool
            {
                return false;
            }

            public function output(): string
            {
                return '';
            }

            public function errorOutput(): string
            {
                return 'Mock compilation error';
            }

            public function exitCode(): int
            {
                return 1;
            }
        };
    }
}
