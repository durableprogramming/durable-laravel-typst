<?php

namespace Durableprogramming\LaravelTypst\Tests\Unit\Exceptions;

use Durableprogramming\LaravelTypst\Exceptions\TypstCompilationException;
use Durableprogramming\LaravelTypst\Tests\TestCase;
use Exception;

class TypstCompilationExceptionTest extends TestCase
{
    public function test_exception_can_be_created_with_message(): void
    {
        $message = 'Compilation failed';
        $exception = new TypstCompilationException($message);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertEquals(0, $exception->getExitCode());
    }

    public function test_exception_can_be_created_with_message_and_exit_code(): void
    {
        $message = 'Compilation failed';
        $exitCode = 1;
        $exception = new TypstCompilationException($message, $exitCode);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($exitCode, $exception->getCode());
        $this->assertEquals($exitCode, $exception->getExitCode());
    }

    public function test_exception_can_be_created_with_previous_exception(): void
    {
        $message = 'Compilation failed';
        $exitCode = 1;
        $previous = new Exception('Previous error');
        $exception = new TypstCompilationException($message, $exitCode, $previous);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($exitCode, $exception->getCode());
        $this->assertEquals($exitCode, $exception->getExitCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function test_exception_inherits_from_exception(): void
    {
        $exception = new TypstCompilationException('Test');

        $this->assertInstanceOf(Exception::class, $exception);
    }

    public function test_exception_can_be_thrown_and_caught(): void
    {
        $message = 'Test compilation error';
        $exitCode = 2;

        try {
            throw new TypstCompilationException($message, $exitCode);
        } catch (TypstCompilationException $e) {
            $this->assertEquals($message, $e->getMessage());
            $this->assertEquals($exitCode, $e->getExitCode());
        }
    }

    public function test_exception_with_zero_exit_code(): void
    {
        $exception = new TypstCompilationException('Test', 0);

        $this->assertEquals(0, $exception->getExitCode());
        $this->assertEquals(0, $exception->getCode());
    }

    public function test_exception_with_negative_exit_code(): void
    {
        $exitCode = -1;
        $exception = new TypstCompilationException('Test', $exitCode);

        $this->assertEquals($exitCode, $exception->getExitCode());
        $this->assertEquals($exitCode, $exception->getCode());
    }

    public function test_exception_with_large_exit_code(): void
    {
        $exitCode = 255;
        $exception = new TypstCompilationException('Test', $exitCode);

        $this->assertEquals($exitCode, $exception->getExitCode());
        $this->assertEquals($exitCode, $exception->getCode());
    }

    public function test_exception_default_values(): void
    {
        $exception = new TypstCompilationException;

        $this->assertEquals('', $exception->getMessage());
        $this->assertEquals(0, $exception->getCode());
        $this->assertEquals(0, $exception->getExitCode());
        $this->assertNull($exception->getPrevious());
    }

    public function test_exception_can_be_serialized(): void
    {
        $message = 'Serialization test';
        $exitCode = 42;
        $exception = new TypstCompilationException($message, $exitCode);

        $serialized = serialize($exception);
        $unserialized = unserialize($serialized);

        $this->assertEquals($message, $unserialized->getMessage());
        $this->assertEquals($exitCode, $unserialized->getExitCode());
        $this->assertEquals($exitCode, $unserialized->getCode());
    }

    public function test_exception_string_representation(): void
    {
        $message = 'Test error';
        $exitCode = 1;
        $exception = new TypstCompilationException($message, $exitCode);

        $string = (string) $exception;

        $this->assertStringContainsString($message, $string);
        $this->assertStringContainsString('TypstCompilationException', $string);
    }

    public function test_exception_trace_is_captured(): void
    {
        try {
            $this->throwTypstException();
        } catch (TypstCompilationException $e) {
            $trace = $e->getTrace();
            $this->assertIsArray($trace);
            $this->assertNotEmpty($trace);
            $this->assertArrayHasKey('function', $trace[0]);
            $this->assertEquals('throwTypstException', $trace[0]['function']);
        }
    }

    private function throwTypstException(): void
    {
        throw new TypstCompilationException('Traced exception', 1);
    }
}
