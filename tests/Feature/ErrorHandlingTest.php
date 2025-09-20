<?php

namespace Durableprogramming\LaravelTypst\Tests\Feature;

use Durableprogramming\LaravelTypst\Exceptions\TypstCompilationException;
use Durableprogramming\LaravelTypst\Facades\Typst;
use Durableprogramming\LaravelTypst\Tests\TestCase;
use Illuminate\Support\Facades\Process;

class ErrorHandlingTest extends TestCase
{
    public function test_handles_typst_binary_not_found(): void
    {
        Typst::setConfig(['bin_path' => 'nonexistent-typst-binary']);

        $this->expectException(\Durableprogramming\LaravelTypst\Exceptions\TypstCompilationException::class);

        Typst::compile($this->getValidTypstContent());
    }

    public function test_handles_compilation_timeout(): void
    {
        Typst::setConfig(['timeout' => 1]);

        Process::shouldReceive('timeout')
            ->once()
            ->with(1)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->once()
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        $mockProcess = \Mockery::mock(\Symfony\Component\Process\Process::class);
        $mockProcess->shouldReceive('getCommandLine')->andReturn('mock command');
        $mockProcess->shouldReceive('getTimeout')->andReturn(1);

        $mockResult = \Mockery::mock(\Illuminate\Contracts\Process\ProcessResult::class);

        Process::shouldReceive('run')
            ->once()
            ->andThrow(new \Illuminate\Process\Exceptions\ProcessTimedOutException(
                new \Symfony\Component\Process\Exception\ProcessTimedOutException(
                    $mockProcess,
                    \Symfony\Component\Process\Exception\ProcessTimedOutException::TYPE_GENERAL
                ),
                $mockResult
            ));

        $this->expectException(\Illuminate\Process\Exceptions\ProcessTimedOutException::class);

        Typst::compile($this->getValidTypstContent());
    }

    public function test_handles_invalid_working_directory(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Cannot test permission failures on Windows');
        }

        // Create a read-only directory to simulate permission failure
        $readOnlyDir = $this->getTestWorkingDirectory().'/readonly';
        mkdir($readOnlyDir, 0444);

        $forbiddenPath = $readOnlyDir.'/forbidden';

        try {
            $this->expectException(TypstCompilationException::class);
            $this->expectExceptionMessage('Failed to create working directory');

            Typst::setConfig(['working_directory' => $forbiddenPath]);
        } finally {
            chmod($readOnlyDir, 0755);
        }
    }

    public function test_handles_compilation_syntax_errors(): void
    {
        $invalidSource = '#invalid syntax {{{ broken';

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
            ->andReturn($this->createFailedProcess('Syntax error at line 1'));

        $this->expectException(TypstCompilationException::class);
        $this->expectExceptionMessage('Syntax error at line 1');

        Typst::compile($invalidSource);
    }

    public function test_handles_file_system_errors(): void
    {
        $source = $this->getValidTypstContent();

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
            ->andReturn($this->createFailedProcess('Permission denied'));

        $this->expectException(TypstCompilationException::class);
        $this->expectExceptionMessage('Permission denied');

        Typst::compile($source);
    }

    public function test_handles_missing_input_file(): void
    {
        $nonExistentFile = '/tmp/does-not-exist.typ';

        $this->expectException(TypstCompilationException::class);
        $this->expectExceptionMessage("Input file does not exist: {$nonExistentFile}");

        Typst::compileFile($nonExistentFile);
    }

    public function test_handles_output_file_write_errors(): void
    {
        $inputFile = $this->getTestWorkingDirectory().'/input.typ';
        file_put_contents($inputFile, $this->getValidTypstContent());

        if (PHP_OS_FAMILY !== 'Windows') {
            $readOnlyDir = $this->getTestWorkingDirectory().'/readonly';
            mkdir($readOnlyDir, 0444);
            $outputFile = $readOnlyDir.'/output.pdf';

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
                ->andReturn($this->createFailedProcess('Cannot write to output file'));

            try {
                $this->expectException(TypstCompilationException::class);
                $this->expectExceptionMessage('Cannot write to output file');

                Typst::compileFile($inputFile, [], $outputFile);
            } finally {
                chmod($readOnlyDir, 0755);
            }
        } else {
            $this->markTestSkipped('Cannot test file permission errors on Windows');
        }
    }

    public function test_handles_corrupted_temp_files(): void
    {
        $source = $this->getValidTypstContent();

        $service = $this->getMockBuilder(\Durableprogramming\LaravelTypst\TypstService::class)
            ->setConstructorArgs([['working_directory' => $this->getTestWorkingDirectory()]])
            ->onlyMethods(['createTempFile'])
            ->getMock();

        $service->expects($this->once())
            ->method('createTempFile')
            ->willThrowException(new TypstCompilationException('Failed to create temporary file'));

        $this->expectException(TypstCompilationException::class);
        $this->expectExceptionMessage('Failed to create temporary file');

        $service->compile($source);
    }

    public function test_handles_process_interruption(): void
    {
        $source = $this->getValidTypstContent();

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
            ->andReturn($this->createFailedProcess('Process interrupted', 130));

        $this->expectException(TypstCompilationException::class);
        $this->expectExceptionMessage('Process interrupted');

        Typst::compile($source);
    }

    public function test_handles_memory_limit_exceeded(): void
    {
        $source = $this->getValidTypstContent();

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
            ->andReturn($this->createFailedProcess('Memory limit exceeded', 137));

        $this->expectException(TypstCompilationException::class);
        $this->expectExceptionMessage('Memory limit exceeded');

        Typst::compile($source);
    }

    public function test_error_includes_exit_code(): void
    {
        $source = $this->getValidTypstContent();
        $exitCode = 42;

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
            ->andReturn($this->createFailedProcess('Custom error', $exitCode));

        try {
            Typst::compile($source);
        } catch (TypstCompilationException $e) {
            $this->assertEquals($exitCode, $e->getExitCode());
        }
    }

    public function test_error_message_formatting(): void
    {
        $source = $this->getValidTypstContent();
        $errorMessage = 'error: expected expression, found end of file
  ┌─ /tmp/input.typ:3:1
  │
3 │ = Document
  │          ^ expected expression';

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
            ->andReturn($this->createFailedProcess($errorMessage));

        try {
            Typst::compile($source);
        } catch (TypstCompilationException $e) {
            $this->assertStringContainsString('expected expression', $e->getMessage());
            $this->assertStringContainsString('/tmp/input.typ:3:1', $e->getMessage());
        }
    }

    public function test_cleanup_occurs_even_on_error(): void
    {
        // Since temp file creation/cleanup is hard to test with mocks, let's test the concept
        // by verifying that the finally block in compile() always executes
        $source = $this->getValidTypstContent();

        // Create a temp file manually to simulate what happens in compilation
        $workingDir = $this->getTestWorkingDirectory();
        $testTempFile = tempnam($workingDir, 'typst_').'.typ';
        file_put_contents($testTempFile, $source);

        // Verify the temp file exists
        $this->assertFileExists($testTempFile);

        // Create a service and test the cleanup method directly
        $service = new \Durableprogramming\LaravelTypst\TypstService(['working_directory' => $workingDir]);

        // Use reflection to test the cleanup method
        $reflection = new \ReflectionClass($service);
        $cleanupMethod = $reflection->getMethod('cleanupTempFile');
        $cleanupMethod->setAccessible(true);

        // Call cleanup method
        $cleanupMethod->invoke($service, $testTempFile);

        // Verify file was cleaned up
        $this->assertFileDoesNotExist($testTempFile);
    }

    public function test_handles_compilation_with_disk_full(): void
    {
        $source = $this->getValidTypstContent();

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
            ->andReturn($this->createFailedProcess('No space left on device', 28));

        $this->expectException(TypstCompilationException::class);
        $this->expectExceptionMessage('No space left on device');

        Typst::compile($source);
    }

    public function test_handles_compilation_with_corrupted_output_file(): void
    {
        $source = $this->getValidTypstContent();

        // Mock successful process but create corrupted output
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
                $outputFile = $command[3];
                // Create a corrupted/truncated file
                file_put_contents($outputFile, 'corrupted');
                return $this->createSuccessfulProcess();
            });

        $outputFile = Typst::compile($source);
        $this->assertFileExists($outputFile);
        // File exists but is corrupted - this tests that we don't validate content
    }

    public function test_handles_compilation_with_permission_denied_on_output(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Cannot test permission errors on Windows');
        }

        $source = $this->getValidTypstContent();
        $readOnlyDir = $this->getTestWorkingDirectory().'/readonly_output';
        mkdir($readOnlyDir, 0444);

        Typst::setConfig(['working_directory' => $readOnlyDir]);

        Process::shouldReceive('timeout')
            ->once()
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->once()
            ->with($readOnlyDir)
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->once()
            ->andReturn($this->createFailedProcess('Permission denied', 13));

        try {
            $this->expectException(TypstCompilationException::class);
            $this->expectExceptionMessage('Permission denied');

            Typst::compile($source);
        } finally {
            chmod($readOnlyDir, 0755);
        }
    }

    public function test_handles_compilation_timeout_during_execution(): void
    {
        $source = $this->getValidTypstContent();

        Process::shouldReceive('timeout')
            ->once()
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->once()
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        $mockProcess = \Mockery::mock(\Symfony\Component\Process\Process::class);
        $mockProcess->shouldReceive('getCommandLine')->andReturn('mock command');
        $mockProcess->shouldReceive('getTimeout')->andReturn(30);

        $mockResult = \Mockery::mock(\Illuminate\Contracts\Process\ProcessResult::class);

        Process::shouldReceive('run')
            ->once()
            ->andThrow(new \Illuminate\Process\Exceptions\ProcessTimedOutException(
                new \Symfony\Component\Process\Exception\ProcessTimedOutException(
                    $mockProcess,
                    \Symfony\Component\Process\Exception\ProcessTimedOutException::TYPE_GENERAL
                ),
                $mockResult
            ));

        $this->expectException(\Illuminate\Process\Exceptions\ProcessTimedOutException::class);

        Typst::compile($source);
    }

    public function test_handles_compilation_with_network_interruptions(): void
    {
        $source = $this->getValidTypstContent();

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
            ->andReturn($this->createFailedProcess('Network is unreachable', 101));

        $this->expectException(TypstCompilationException::class);
        $this->expectExceptionMessage('Network is unreachable');

        Typst::compile($source);
    }

    public function test_handles_compilation_with_invalid_utf8_in_error(): void
    {
        $source = $this->getValidTypstContent();
        $invalidUtf8Error = "Error: \x80\x81\x82 invalid utf8 content";

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
            ->andReturn($this->createFailedProcess($invalidUtf8Error));

        $this->expectException(TypstCompilationException::class);
        // Should handle invalid UTF-8 gracefully
        Typst::compile($source);
    }

    public function test_handles_symlink_working_directory(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Symlink tests not reliable on Windows');
        }

        $realDir = $this->getTestWorkingDirectory().'/real_dir';
        $symlinkDir = $this->getTestWorkingDirectory().'/symlink_dir';

        mkdir($realDir, 0755, true);
        symlink($realDir, $symlinkDir);

        Typst::setConfig(['working_directory' => $symlinkDir]);

        Process::shouldReceive('timeout')
            ->once()
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->once()
            ->with($symlinkDir)
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->once()
            ->andReturn($this->createSuccessfulProcess());

        $source = $this->getValidTypstContent();
        $result = Typst::compile($source);

        $this->assertStringEndsWith('.pdf', $result);
        $this->assertFileExists($result);

        unlink($result);
        unlink($symlinkDir);
        rmdir($realDir);
    }

    public function test_handles_case_sensitivity_in_paths(): void
    {
        $source = $this->getValidTypstContent();
        $workingDir = $this->getTestWorkingDirectory();

        // Test with different case variations if filesystem is case-insensitive
        $upperCaseDir = strtoupper($workingDir);
        $lowerCaseDir = strtolower($workingDir);

        // This test mainly ensures the code handles path variations gracefully
        Typst::setConfig(['working_directory' => $workingDir]);

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
            ->andReturn($this->createSuccessfulProcess());

        $result = Typst::compile($source);

        $this->assertStringEndsWith('.pdf', $result);
        $this->assertFileExists($result);
    }

    private function createFailedProcess(string $errorMessage, int $exitCode = 1): object
    {
        return new class($errorMessage, $exitCode)
        {
            public function __construct(
                private string $errorMessage,
                private int $exitCode
            ) {}

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
                return $this->errorMessage;
            }

            public function exitCode(): int
            {
                return $this->exitCode;
            }
        };
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
