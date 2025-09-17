<?php

namespace Durableprogramming\LaravelTypst\Tests\Unit;

use Durableprogramming\LaravelTypst\Tests\TestCase;
use Durableprogramming\LaravelTypst\TypstService;
use Illuminate\Support\Facades\Process;

class BladeTemplateTest extends TestCase
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

    public function test_compile_with_blade_template_variables(): void
    {
        $template = '= Hello {{ $name }}!

This is a test document for {{ $company }}.';

        $data = [
            'name' => 'John Doe',
            'company' => 'Acme Corp'
        ];

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

        $outputFile = $this->service->compile($template, $data);

        $this->assertStringEndsWith('.pdf', $outputFile);
        $this->assertStringContainsString($this->getTestWorkingDirectory(), $outputFile);
    }

    public function test_compile_with_blade_directives(): void
    {
        $template = '= Document Title

@if($showSection)
== Important Section
This section is conditionally shown.
@endif

@foreach($items as $item)
- {{ $item }}
@endforeach';

        $data = [
            'showSection' => true,
            'items' => ['Item 1', 'Item 2', 'Item 3']
        ];

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

        $outputFile = $this->service->compile($template, $data);

        $this->assertStringEndsWith('.pdf', $outputFile);
    }

    public function test_compile_with_laravel_path_helpers(): void
    {
        $template = '= Document with Assets

Logo path: {{ asset("images/logo.png") }}
Public path: {{ public_path("files/document.pdf") }}
Base path: {{ base_path("config/app.php") }}';

        $data = [];

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

        $outputFile = $this->service->compile($template, $data);

        $this->assertStringEndsWith('.pdf', $outputFile);
    }

    public function test_compile_without_blade_syntax_remains_unchanged(): void
    {
        $template = '= Simple Document

This is a plain Typst document without any Blade syntax.

== Section
Regular content here.';

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

        $outputFile = $this->service->compile($template);

        $this->assertStringEndsWith('.pdf', $outputFile);
    }

    public function test_compile_file_with_blade_template(): void
    {
        $inputFile = $this->getTestWorkingDirectory() . '/template.typ';
        $template = '= Invoice for {{ $customerName }}

Date: {{ $date }}
Amount: ${{ number_format($amount, 2) }}

@if($isPaid)
Status: PAID
@else
Status: PENDING
@endif';

        file_put_contents($inputFile, $template);

        $data = [
            'customerName' => 'John Smith',
            'date' => '2024-01-15',
            'amount' => 1250.75,
            'isPaid' => false
        ];

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

        $outputFile = $this->service->compileFile($inputFile, $data);

        $this->assertStringEndsWith('.pdf', $outputFile);
    }

    public function test_blade_template_rendering_error_throws_exception(): void
    {
        $template = '= Broken Template

{{ $undefinedVariable->someMethod() }}';

        $data = [];

        $this->expectException(\Durableprogramming\LaravelTypst\Exceptions\TypstCompilationException::class);
        $this->expectExceptionMessageMatches('/Blade template rendering failed/');

        $this->service->compile($template, $data);
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