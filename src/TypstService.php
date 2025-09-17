<?php

namespace Durableprogramming\LaravelTypst;

use Durableprogramming\LaravelTypst\Exceptions\TypstCompilationException;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Process;

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
            'root' => base_path(),
        ], $config);

        $this->binPath = $this->config['bin_path'];
        $this->workingDirectory = $this->config['working_directory'];

        $this->ensureWorkingDirectory();
    }

    public function compile(string $source, array $data = [], array $options = []): string
    {
        $processedSource = $this->renderBladeTemplate($source, $data);
        $tempFile = $this->createTempFile($processedSource);
        $outputFile = $this->getOutputPath($tempFile, $options['format'] ?? $this->config['format']);

        try {
            $result = $this->executeTypst($tempFile, $outputFile, $options);

            if (! $result->successful()) {
                throw new TypstCompilationException(
                    'Typst compilation failed: '.$result->errorOutput(),
                    $result->exitCode()
                );
            }

            return $outputFile;
        } finally {
            $this->cleanupTempFile($tempFile);
        }
    }

    public function compileToString(string $source, array $data = [], array $options = []): string
    {
        $outputFile = $this->compile($source, $data, $options);

        try {
            if (! file_exists($outputFile)) {
                throw new TypstCompilationException("Output file does not exist: {$outputFile}");
            }

            $content = file_get_contents($outputFile);

            if ($content === false) {
                throw new TypstCompilationException('Failed to read compiled output file');
            }

            return $content;
        } finally {
            $this->cleanupFile($outputFile);
        }
    }

    public function compileFile(string $inputPath, array $data = [], ?string $outputPath = null, array $options = []): string
    {
        if (! file_exists($inputPath)) {
            throw new TypstCompilationException("Input file does not exist: {$inputPath}");
        }

        $fileContent = file_get_contents($inputPath);
        if ($fileContent === false) {
            throw new TypstCompilationException("Failed to read input file: {$inputPath}");
        }

        $processedContent = $this->renderBladeTemplate($fileContent, $data);
        $tempFile = $this->createTempFile($processedContent);
        $outputPath = $outputPath ?? $this->getOutputPath($inputPath, $options['format'] ?? $this->config['format']);

        try {
            $result = $this->executeTypst($tempFile, $outputPath, $options);

            if (! $result->successful()) {
                throw new TypstCompilationException(
                    'Typst compilation failed: '.$result->errorOutput(),
                    $result->exitCode()
                );
            }

            return $outputPath;
        } finally {
            $this->cleanupTempFile($tempFile);
        }
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
        } else {

            $command[] = '--root';
            $command[] = base_path();

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
        $tempFile = $baseTempFile.'.typ';

        // Remove the base temp file created by tempnam
        if (file_exists($baseTempFile)) {
            unlink($baseTempFile);
        }

        if (file_put_contents($tempFile, $content) === false) {
            throw new TypstCompilationException('Failed to create temporary file');
        }

        return $tempFile;
    }

    protected function getOutputPath(string $inputFile, string $format): string
    {
        $pathInfo = pathinfo($inputFile);

        return $pathInfo['dirname'].'/'.$pathInfo['filename'].'.'.$format;
    }

    protected function ensureWorkingDirectory(): void
    {
        if (! is_dir($this->workingDirectory)) {
            try {
                if (! mkdir($this->workingDirectory, 0755, true)) {
                    throw new TypstCompilationException("Failed to create working directory: {$this->workingDirectory}");
                }
            } catch (\ErrorException $e) {
                throw new TypstCompilationException("Failed to create working directory: {$this->workingDirectory}");
            }
        }
    }

    protected function cleanupTempFile(string $filePath): void
    {
        // Temporarily disable cleanup for debugging
        // if (file_exists($filePath) && strpos(basename($filePath), 'typst_') === 0) {
        //     unlink($filePath);
        // }
        
        // Also cleanup any imported files created during processing
        $this->cleanupImportedFiles();
    }
    
    protected function cleanupImportedFiles(): void
    {
        // Temporarily disable cleanup for debugging
        return;
        
        $pattern = $this->workingDirectory . '/imported_*';
        $files = glob($pattern);
        if ($files) {
            foreach ($files as $file) {
                if (file_exists($file)) {
                    unlink($file);
                }
            }
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

    protected function renderBladeTemplate(string $template, array $data = []): string
    {
        // First process any special !import commands
        $template = $this->processSpecialImports($template, $data);
        
        // Build dependency tree and process all imports
        $template = $this->buildDependencyTreeAndResolveImports($template);
        
        if (empty($data) && ! $this->containsBladeDirectives($template)) {
            return $template;
        }

        try {
            $compiledTemplate = Blade::compileString($template);

            ob_start();
            extract($data, EXTR_SKIP);

            $__env = app('view');
            eval('?>'.$compiledTemplate);
            $rendered = ob_get_clean();

            if ($rendered === false) {
                throw new TypstCompilationException('Failed to render Blade template');
            }

            return $rendered;
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            throw new TypstCompilationException(
                'Blade template rendering failed: '.$e->getMessage(),
                0,
                $e
            );
        }
    }

    protected function processSpecialImports(string $template, array $data = []): string
    {
        // Pattern to match: #!import "template_name" with_data: variable_name
        $pattern = '/#!import\s+"([^"]*)"\s+with_data:\s*(\w+)/';
        
        return preg_replace_callback($pattern, function ($matches) use ($data) {
            $templateName = $matches[1];
            $dataVariable = $matches[2];
            
            // Generate the data variables for the template
            if (isset($data[$dataVariable]) && is_array($data[$dataVariable])) {
                // Generate the main data variable as a dictionary
                $mainVariable = "#let $dataVariable = " . $this->arrayToTypstValue($data[$dataVariable]) . "\n";
                $variableDefinitions = $this->generateTypstVariables($data[$dataVariable]);
                
                // If template name is empty, just inject variables
                if (empty($templateName)) {
                    return $mainVariable . $variableDefinitions;
                }
                
                // Return the variable definitions followed by the import
                return $mainVariable . $variableDefinitions . "\n#import \"$templateName\" : *";
            }
            
            // Fallback to regular import if data not found
            if (!empty($templateName)) {
                return "#import \"$templateName\" : *";
            }
            
            return "";
        }, $template);
    }
    
    protected function generateTypstVariables(array $data): string
    {
        $variables = [];
        
        foreach ($data as $key => $value) {
            // Skip numeric keys as they can't be valid Typst variable names
            if (is_numeric($key)) {
                continue;
            }
            
            // Skip invalid variable names (must start with letter or underscore)
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $key)) {
                continue;
            }
            
            if (is_string($value)) {
                $variables[] = "#let $key = \"" . addslashes($value) . "\"";
            } elseif (is_numeric($value)) {
                $variables[] = "#let $key = $value";
            } elseif (is_bool($value)) {
                $variables[] = "#let $key = " . ($value ? 'true' : 'false');
            } elseif (is_array($value)) {
                // Handle arrays/objects
                $variables[] = "#let $key = " . $this->arrayToTypstValue($value);
            } else {
                $variables[] = "#let $key = \"\"";
            }
        }
        
        return implode("\n", $variables) . "\n";
    }
    
    protected function arrayToTypstValue($value): string
    {
        if (is_array($value)) {
            if (array_keys($value) === range(0, count($value) - 1)) {
                // Indexed array
                $items = array_map([$this, 'arrayToTypstValue'], $value);
                return '(' . implode(', ', $items) . ')';
            } else {
                // Associative array (dictionary)
                $items = [];
                foreach ($value as $k => $v) {
                    // Convert numeric keys to strings for Typst compatibility
                    $key = is_numeric($k) ? '"' . $k . '"' : $k;
                    $items[] = $key . ': ' . $this->arrayToTypstValue($v);
                }
                return '(' . implode(', ', $items) . ')';
            }
        } elseif (is_string($value)) {
            return '"' . addslashes($value) . '"';
        } elseif (is_numeric($value)) {
            return (string) $value;
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        
        return '""';
    }

    protected function buildDependencyTreeAndResolveImports(string $template, string $currentDir = ''): string
    {
        $dependencies = [];
        $processedFiles = [];
        
        // Build dependency tree
        $this->collectDependencies($template, $currentDir, $dependencies);
        
        // Process all dependencies based on file type
        foreach ($dependencies as $sourceFilePath => $info) {
            $content = file_get_contents($sourceFilePath);
            if ($content === false) {
                continue;
            }
            
            // Process blade files through blade system, regular files as-is
            if (str_contains($sourceFilePath, '.blade.typ')) {
                $processedContent = $this->processBladeSyntaxOnly($content);
            } else {
                // For regular .typ files, never process through blade
                $processedContent = $content;
            }
            
            // Rewrite imports in this file to use temp filenames
            $processedContent = $this->rewriteImports($processedContent, $dependencies, dirname($sourceFilePath));
            
            // Write to temporary file
            $tempFilename = $info['temp_filename'];
            $tempFilePath = $this->workingDirectory . '/' . $tempFilename;
            file_put_contents($tempFilePath, $processedContent);
            
            $processedFiles[$sourceFilePath] = $tempFilename;
        }
        
        // Rewrite all imports in the main template
        return $this->rewriteImports($template, $dependencies, $currentDir);
    }
    
    protected function collectDependencies(string $template, string $currentDir, array &$dependencies): void
    {
        $pattern = '/#import\s+"([^"]+)"\s*:\s*([^\n\r]+)/';
        
        preg_match_all($pattern, $template, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            $importPath = $match[1];
            
            // Skip if path is already absolute
            if (str_starts_with($importPath, '/')) {
                continue;
            }
            
            $sourceFilePath = $this->resolveFilePath($importPath, $currentDir);
            
            if (!$sourceFilePath || !file_exists($sourceFilePath)) {
                continue;
            }
            
            if (isset($dependencies[$sourceFilePath])) {
                continue;
            }
            
            // Add to dependencies
            $originalFilename = basename($sourceFilePath);
            $tempFilename = 'imported_' . md5($sourceFilePath) . '_' . $originalFilename;
            
            $dependencies[$sourceFilePath] = [
                'temp_filename' => $tempFilename,
                'original_path' => $importPath
            ];
            
            // Recursively collect dependencies from this file
            $fileContent = file_get_contents($sourceFilePath);
            if ($fileContent !== false) {
                $this->collectDependencies($fileContent, dirname($sourceFilePath), $dependencies);
            }
        }
    }
    
    protected function resolveFilePath(string $importPath, string $currentDir = ''): ?string
    {
        // If path starts with "resources/typst/", resolve from base path
        if (str_starts_with($importPath, 'resources/typst/')) {
            $sourceFilePath = base_path($importPath);
        } else {
            // Handle relative paths (like "../../BaseStyle.typ")
            if ($currentDir) {
                $resolvedPath = $currentDir . '/' . $importPath;
                $sourceFilePath = realpath($resolvedPath);
            } else {
                $sourceFilePath = resource_path('typst/' . $importPath);
            }
        }
        
        return ($sourceFilePath && file_exists($sourceFilePath)) ? $sourceFilePath : null;
    }
    
    protected function processBladeSyntaxOnly(string $content): string
    {
        // Process blade without recursive import resolution
        if (! $this->containsBladeDirectives($content)) {
            return $content;
        }

        try {
            $compiledTemplate = Blade::compileString($content);
            ob_start();
            $__env = app('view');
            eval('?>'.$compiledTemplate);
            $rendered = ob_get_clean();
            return $rendered !== false ? $rendered : $content;
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            return $content; // Return original content if blade processing fails
        }
    }
    
    protected function rewriteImports(string $template, array $dependencies, string $currentDir = ''): string
    {
        $pattern = '/#import\s+"([^"]+)"\s*:\s*([^\n\r]+)/';
        
        return preg_replace_callback($pattern, function ($matches) use ($dependencies, $currentDir) {
            $importPath = $matches[1];
            $symbols = $matches[2];
            
            $sourceFilePath = $this->resolveFilePath($importPath, $currentDir);
            
            if ($sourceFilePath && isset($dependencies[$sourceFilePath])) {
                $tempFilename = $dependencies[$sourceFilePath]['temp_filename'];
                return "#import \"$tempFilename\" : $symbols";
            }
            
            return $matches[0]; // Return unchanged if not processed
        }, $template);
    }

    protected function containsBladeDirectives(string $template): bool
    {
        $bladePatterns = [
            '/\{\{.*?\}\}/',
            '/\{!!.*?!!\}/',
            '/@\w+/',
            '/\{\{--.*?--\}\}/s',
            '/#!import\s+"[^"]+"\s+with_data:\s*\w+/', // Our special import syntax
        ];

        foreach ($bladePatterns as $pattern) {
            if (preg_match($pattern, $template)) {
                return true;
            }
        }

        return false;
    }

}
