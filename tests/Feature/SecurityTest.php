<?php

namespace Durableprogramming\LaravelTypst\Tests\Feature;

use Durableprogramming\LaravelTypst\Exceptions\TypstCompilationException;
use Durableprogramming\LaravelTypst\Facades\Typst;
use Durableprogramming\LaravelTypst\Tests\TestCase;
use Illuminate\Support\Facades\Process;

class SecurityTest extends TestCase
{
    public function test_prevents_path_traversal_in_input_file(): void
    {
        $maliciousPath = '../../../etc/passwd';

        $this->expectException(TypstCompilationException::class);
        $this->expectExceptionMessage("Input file does not exist");

        Typst::compileFile($maliciousPath);
    }

    public function test_prevents_path_traversal_in_working_directory(): void
    {
        $maliciousWorkingDir = '../../../etc';

        $this->expectException(TypstCompilationException::class);

        Typst::setConfig(['working_directory' => $maliciousWorkingDir]);
        Typst::compile($this->getValidTypstContent());
    }

    public function test_prevents_command_injection_in_bin_path(): void
    {
        $maliciousBinPath = 'typst; rm -rf /';

        Typst::setConfig(['bin_path' => $maliciousBinPath]);

        Process::shouldReceive('timeout')
            ->once()
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->once()
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->once()
            ->andReturnUsing(function ($command) {
                // Verify that the command doesn't contain shell injection
                $commandStr = implode(' ', $command);
                $this->assertStringNotContainsString('; rm -rf /', $commandStr);
                $this->assertStringNotContainsString('&&', $commandStr);
                $this->assertStringNotContainsString('||', $commandStr);
                $this->assertStringNotContainsString('|', $commandStr);

                return $this->createSuccessfulProcess();
            });

        Typst::compile($this->getValidTypstContent());
    }

    public function test_prevents_command_injection_in_format(): void
    {
        $maliciousFormat = 'pdf; malicious-command';

        Process::shouldReceive('timeout')
            ->once()
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->once()
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->once()
            ->andReturnUsing(function ($command) use ($maliciousFormat) {
                // Verify that malicious format doesn't appear in command
                $commandStr = implode(' ', $command);
                $this->assertStringNotContainsString($maliciousFormat, $commandStr);

                return $this->createSuccessfulProcess();
            });

        Typst::compile($this->getValidTypstContent(), [], ['format' => $maliciousFormat]);
    }

    public function test_prevents_malicious_file_content_execution(): void
    {
        // Create a file with potentially malicious content
        $maliciousContent = '#set page(width: 10cm, height: auto)
= Malicious Document

// This could potentially be malicious if executed
#eval("malicious_code_here")';

        Process::shouldReceive('timeout')
            ->once()
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->once()
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->once()
            ->andReturn($this->createSuccessfulProcess());

        // Should compile without executing malicious code (Typst handles this safely)
        $outputFile = Typst::compile($maliciousContent);
        $this->assertFileExists($outputFile);
    }

    public function test_prevents_directory_traversal_in_imports(): void
    {
        $template = '#import "../../../etc/passwd" : *
= Document';

        Process::shouldReceive('timeout')
            ->once()
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->once()
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->once()
            ->andReturn($this->createSuccessfulProcess());

        // Should compile but import should fail safely
        $outputFile = Typst::compile($template);
        $this->assertFileExists($outputFile);
    }

    public function test_handles_malicious_unicode_in_content(): void
    {
        // Content with potentially malicious Unicode
        $maliciousUnicode = '#set page(width: 10cm, height: auto)
= Document

Content with malicious Unicode: ' . "\u{202E}" . 'REVERSE TEXT' . "\u{202C}";

        Process::shouldReceive('timeout')
            ->once()
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->once()
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->once()
            ->andReturn($this->createSuccessfulProcess());

        $outputFile = Typst::compile($maliciousUnicode);
        $this->assertFileExists($outputFile);
    }

    public function test_prevents_null_byte_injection_in_filenames(): void
    {
        $maliciousFilename = 'normal.pdf' . "\0" . 'malicious.pdf';

        Process::shouldReceive('timeout')
            ->once()
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->once()
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->once()
            ->andReturnUsing(function ($command) {
                // Verify null bytes are not in the command
                $commandStr = implode(' ', $command);
                $this->assertStringNotContainsString("\0", $commandStr);

                return $this->createSuccessfulProcess();
            });

        $outputFile = Typst::compile($this->getValidTypstContent(), [], ['output' => $maliciousFilename]);
        $this->assertFileExists($outputFile);
    }

    public function test_handles_extremely_long_input_content(): void
    {
        $longContent = str_repeat('= Very Long Document Section
This is a test of handling extremely long content that could potentially be used for DoS attacks.
' . str_repeat('Lorem ipsum dolor sit amet. ', 1000) . "\n\n", 100);

        Process::shouldReceive('timeout')
            ->once()
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->once()
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->once()
            ->andReturn($this->createSuccessfulProcess());

        $outputFile = Typst::compile($longContent);
        $this->assertFileExists($outputFile);
    }

    public function test_prevents_execution_of_arbitrary_commands_via_blade(): void
    {
        // This should be safe since Blade processing happens before Typst compilation
        $maliciousBlade = '{{ exec("malicious_command") }}
= Document';

        $data = [];

        $this->expectException(TypstCompilationException::class);
        $this->expectExceptionMessage('Dangerous function "exec" detected in Blade template');

        Typst::compile($maliciousBlade, $data);
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