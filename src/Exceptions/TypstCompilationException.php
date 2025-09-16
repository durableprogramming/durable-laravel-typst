<?php

namespace Durable\LaravelTypst\Exceptions;

use Exception;

class TypstCompilationException extends Exception
{
    protected int $exitCode;

    public function __construct(string $message = "", int $exitCode = 0, Exception $previous = null)
    {
        $this->exitCode = $exitCode;
        parent::__construct($message, $exitCode, $previous);
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function __sleep(): array
    {
        return ['message', 'code', 'file', 'line', 'exitCode'];
    }

    public function __wakeup(): void
    {
        // Ensure properties are restored correctly after unserialization
    }
}