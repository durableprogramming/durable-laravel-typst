<?php

namespace Durable\LaravelTypst\Tests\Unit;

use Durable\LaravelTypst\Exceptions\TypstCompilationException;
use Durable\LaravelTypst\Tests\TestCase;
use Durable\LaravelTypst\TypstService;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

class TypstServiceTest extends TestCase
{
    private TypstService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TypstService([
            'bin_path' => 'mock-typst',
            'working_directory' => $this->getTestWorkingDirectory(),
            'timeout' => 30,
            'format' => 'pdf',
        ]);
    }

    public function test_constructor_sets_default_config(): void
    {
        $service = new TypstService();
        $config = $service->getConfig();

        $this->assertEquals('typst', $config['bin_path']);
        $this->assertEquals(storage_path('typst'), $config['working_directory']);
        $this->assertEquals(60, $config['timeout']);
        $this->assertEquals('pdf', $config['format']);
    }

    public function test_constructor_merges_custom_config(): void
    {
        $customConfig = [
            'bin_path' => '/custom/typst',
            'timeout' => 120,
        ];

        $service = new TypstService($customConfig);
        $config = $service->getConfig();

        $this->assertEquals('/custom/typst', $config['bin_path']);
        $this->assertEquals(120, $config['timeout']);
        $this->assertEquals('pdf', $config['format']);
    }

    public function test_set_config_updates_configuration(): void
    {
        $newConfig = ['timeout' => 90];
        $result = $this->service->setConfig($newConfig);

        $this->assertSame($this->service, $result);
        $this->assertEquals(90, $this->service->getConfig()['timeout']);
    }

    public function test_compile_creates_temp_file_and_compiles(): void
    {
        $source = $this->getValidTypstContent();
        $mockProcess = $this->createMockProcess(true, '', '');

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
            ->andReturn($mockProcess);

        $outputFile = $this->service->compile($source);

        $this->assertStringEndsWith('.pdf', $outputFile);
        $this->assertStringContainsString($this->getTestWorkingDirectory(), $outputFile);
    }

    public function test_compile_with_custom_format(): void
    {
        $source = $this->getValidTypstContent();
        $mockProcess = $this->createMockProcess(true, '', '');

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
            ->andReturn($mockProcess);

        $outputFile = $this->service->compile($source, ['format' => 'png']);

        $this->assertStringEndsWith('.png', $outputFile);
    }

    public function test_compile_throws_exception_on_failure(): void
    {
        $source = $this->getValidTypstContent();
        $mockProcess = $this->createMockProcess(false, '', 'Compilation error');

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
            ->andReturn($mockProcess);

        $this->expectException(TypstCompilationException::class);
        $this->expectExceptionMessage('Typst compilation failed: Compilation error');

        $this->service->compile($source);
    }

    public function test_compile_to_string_returns_file_content(): void
    {
        $source = $this->getValidTypstContent();
        $expectedContent = 'Mock PDF content';

        $outputFile = $this->getTestWorkingDirectory() . '/test_output.pdf';
        file_put_contents($outputFile, $expectedContent);

        $service = $this->getMockBuilder(TypstService::class)
            ->setConstructorArgs([['working_directory' => $this->getTestWorkingDirectory()]])
            ->onlyMethods(['compile'])
            ->getMock();

        $service->expects($this->once())
            ->method('compile')
            ->with($source, [])
            ->willReturn($outputFile);

        $result = $service->compileToString($source);

        $this->assertEquals($expectedContent, $result);
        $this->assertFileDoesNotExist($outputFile);
    }

    public function test_compile_to_string_throws_exception_if_cannot_read_file(): void
    {
        $source = $this->getValidTypstContent();
        $nonExistentFile = $this->getTestWorkingDirectory() . '/non_existent.pdf';

        $service = $this->getMockBuilder(TypstService::class)
            ->setConstructorArgs([['working_directory' => $this->getTestWorkingDirectory()]])
            ->onlyMethods(['compile'])
            ->getMock();

        $service->expects($this->once())
            ->method('compile')
            ->willReturn($nonExistentFile);

        $this->expectException(TypstCompilationException::class);
        $this->expectExceptionMessage('Output file does not exist:');

        $service->compileToString($source);
    }

    public function test_compile_file_with_existing_input(): void
    {
        $inputFile = $this->getTestWorkingDirectory() . '/input.typ';
        $outputFile = $this->getTestWorkingDirectory() . '/output.pdf';
        
        file_put_contents($inputFile, $this->getValidTypstContent());

        $mockProcess = $this->createMockProcess(true, '', '');

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
            ->andReturn($mockProcess);

        $result = $this->service->compileFile($inputFile, $outputFile);

        $this->assertEquals($outputFile, $result);
    }

    public function test_compile_file_throws_exception_if_input_not_exists(): void
    {
        $inputFile = $this->getTestWorkingDirectory() . '/non_existent.typ';
        
        $this->expectException(TypstCompilationException::class);
        $this->expectExceptionMessage("Input file does not exist: {$inputFile}");

        $this->service->compileFile($inputFile);
    }

    public function test_compile_file_generates_output_path_if_not_provided(): void
    {
        $inputFile = $this->getTestWorkingDirectory() . '/input.typ';
        file_put_contents($inputFile, $this->getValidTypstContent());

        $mockProcess = $this->createMockProcess(true, '', '');

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
            ->andReturn($mockProcess);

        $result = $this->service->compileFile($inputFile);

        $expectedOutput = $this->getTestWorkingDirectory() . '/input.pdf';
        $this->assertEquals($expectedOutput, $result);
    }

    public function test_compile_with_root_option(): void
    {
        $source = $this->getValidTypstContent();
        $rootPath = '/custom/root';

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
            ->with(\Mockery::on(function ($command) use ($rootPath) {
                return in_array('--root', $command) && 
                       in_array($rootPath, $command);
            }))
            ->andReturn($this->createMockProcess(true, '', ''));

        $this->service->compile($source, ['root' => $rootPath]);
    }

    public function test_compile_with_font_paths(): void
    {
        $source = $this->getValidTypstContent();
        $fontPaths = ['/fonts/path1', '/fonts/path2'];

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
            ->with(\Mockery::on(function ($command) use ($fontPaths) {
                $commandStr = implode(' ', $command);
                return strpos($commandStr, '--font-path /fonts/path1') !== false &&
                       strpos($commandStr, '--font-path /fonts/path2') !== false;
            }))
            ->andReturn($this->createMockProcess(true, '', ''));

        $this->service->compile($source, ['font_paths' => $fontPaths]);
    }

    public function test_working_directory_is_created_if_not_exists(): void
    {
        $nonExistentDir = $this->getTestWorkingDirectory() . '/nested/path';
        $this->assertDirectoryDoesNotExist($nonExistentDir);

        new TypstService(['working_directory' => $nonExistentDir]);

        $this->assertDirectoryExists($nonExistentDir);
    }

    public function test_working_directory_creation_throws_exception_on_failure(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Cannot test permission failures on Windows');
        }

        $readOnlyDir = $this->getTestWorkingDirectory() . '/readonly';
        mkdir($readOnlyDir, 0444);
        
        $invalidPath = $readOnlyDir . '/nested';

        try {
            $this->expectException(TypstCompilationException::class);
            $this->expectExceptionMessage("Failed to create working directory: {$invalidPath}");

            new TypstService(['working_directory' => $invalidPath]);
        } finally {
            chmod($readOnlyDir, 0755);
        }
    }

    private function createMockProcess(bool $successful, string $output = '', string $errorOutput = ''): object
    {
        return new class($successful, $output, $errorOutput) {
            public function __construct(
                private bool $successful,
                private string $output,
                private string $errorOutput
            ) {}

            public function successful(): bool
            {
                return $this->successful;
            }

            public function output(): string
            {
                return $this->output;
            }

            public function errorOutput(): string
            {
                return $this->errorOutput;
            }

            public function exitCode(): int
            {
                return $this->successful ? 0 : 1;
            }
        };
    }
}