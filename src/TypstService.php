<?php

namespace Durableprogramming\LaravelTypst;

use Illuminate\Support\Facades\Process;
use Durableprogramming\LaravelTypst\Exceptions\TypstCompilationException;

class TypstService
{
    protected array $config;
    protected string $binPath;
    protected string $workingDirectory;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'bin_path' => 'typst',
            'working_directory' => storage_path('typst'),
            'timeout' => 60,
            'format' => 'pdf',
        ], $config);

        $this->binPath = $this->config['bin_path'];
        $this->workingDirectory = $this->config['working_directory'];

        $this->ensureWorkingDirectory();
    }

    public function compile(string $source, array $options = []): string
    {
        $tempFile = $this->createTempFile($source);
        $outputFile = $this->getOutputPath($tempFile, $options['format'] ?? $this->config['format']);

        try {
            $result = $this->executeTypst($tempFile, $outputFile, $options);
            
            if (!$result->successful()) {
                throw new TypstCompilationException(
                    "Typst compilation failed: " . $result->errorOutput(),
                    $result->exitCode()
                );
            }

            return $outputFile;
        } finally {
            $this->cleanupTempFile($tempFile);
        }
    }

    public function compileToString(string $source, array $options = []): string
    {
        $outputFile = $this->compile($source, $options);
        
        try {
            if (!file_exists($outputFile)) {
                throw new TypstCompilationException("Output file does not exist: {$outputFile}");
            }
            
            $content = file_get_contents($outputFile);
            
            if ($content === false) {
                throw new TypstCompilationException("Failed to read compiled output file");
            }
            
            return $content;
        } finally {
            $this->cleanupFile($outputFile);
        }
    }

    public function compileFile(string $inputPath, string $outputPath = null, array $options = []): string
    {
        if (!file_exists($inputPath)) {
            throw new TypstCompilationException("Input file does not exist: {$inputPath}");
        }

        $outputPath = $outputPath ?? $this->getOutputPath($inputPath, $options['format'] ?? $this->config['format']);

        $result = $this->executeTypst($inputPath, $outputPath, $options);
        
        if (!$result->successful()) {
            throw new TypstCompilationException(
                "Typst compilation failed: " . $result->errorOutput(),
                $result->exitCode()
            );
        }

        return $outputPath;
    }

    protected function executeTypst(string $inputFile, string $outputFile, array $options = [])
    {
        $command = [
            $this->binPath,
            'compile',
            $inputFile,
            $outputFile,
        ];

        if (isset($options['root'])) {
            $command[] = '--root';
            $command[] = $options['root'];
        }

        if (isset($options['font_paths']) && is_array($options['font_paths'])) {
            foreach ($options['font_paths'] as $fontPath) {
                $command[] = '--font-path';
                $command[] = $fontPath;
            }
        }

        return Process::timeout($this->config['timeout'])
            ->path($this->workingDirectory)
            ->run($command);
    }

    protected function createTempFile(string $content): string
    {
        $baseTempFile = @tempnam($this->workingDirectory, 'typst_');
        $tempFile = $baseTempFile . '.typ';
        
        // Remove the base temp file created by tempnam
        if (file_exists($baseTempFile)) {
            unlink($baseTempFile);
        }
        
        if (file_put_contents($tempFile, $content) === false) {
            throw new TypstCompilationException("Failed to create temporary file");
        }

        return $tempFile;
    }

    protected function getOutputPath(string $inputFile, string $format): string
    {
        $pathInfo = pathinfo($inputFile);
        return $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '.' . $format;
    }

    protected function ensureWorkingDirectory(): void
    {
        if (!is_dir($this->workingDirectory)) {
            try {
                if (!mkdir($this->workingDirectory, 0755, true)) {
                    throw new TypstCompilationException("Failed to create working directory: {$this->workingDirectory}");
                }
            } catch (\ErrorException $e) {
                throw new TypstCompilationException("Failed to create working directory: {$this->workingDirectory}");
            }
        }
    }

    protected function cleanupTempFile(string $filePath): void
    {
        if (file_exists($filePath) && strpos(basename($filePath), 'typst_') === 0) {
            unlink($filePath);
        }
    }

    protected function cleanupFile(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        
        // Update properties that might have changed
        if (isset($config['bin_path'])) {
            $this->binPath = $config['bin_path'];
        }
        
        if (isset($config['working_directory'])) {
            $this->workingDirectory = $config['working_directory'];
            $this->ensureWorkingDirectory();
        }
        
        return $this;
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}
