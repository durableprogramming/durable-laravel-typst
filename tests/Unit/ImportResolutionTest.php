<?php

namespace Durableprogramming\LaravelTypst\Tests\Unit;

use Durableprogramming\LaravelTypst\Exceptions\TypstCompilationException;
use Durableprogramming\LaravelTypst\Tests\TestCase;
use Durableprogramming\LaravelTypst\TypstService;
use Illuminate\Support\Facades\Process;

class ImportResolutionTest extends TestCase
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

    public function test_resolve_file_path_with_resources_typst_prefix(): void
    {
        $importPath = 'resources/typst/BaseStyle.typ';
        $expectedPath = base_path($importPath);

        // Create the file to ensure it exists
        $dir = dirname($expectedPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($expectedPath, '#let baseStyle = "test"');

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('resolveFilePath');
        $method->setAccessible(true);

        $result = $method->invoke($service, $importPath);

        $this->assertEquals($expectedPath, $result);
    }

    public function test_resolve_file_path_with_relative_path(): void
    {
        $currentDir = $this->getTestWorkingDirectory() . '/subdir';
        mkdir($currentDir, 0755, true);

        $importPath = '../BaseStyle.typ';

        // Create the file in the parent directory
        $expectedPath = $this->getTestWorkingDirectory() . '/BaseStyle.typ';
        file_put_contents($expectedPath, '#let baseStyle = "test"');

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('resolveFilePath');
        $method->setAccessible(true);

        $result = $method->invoke($service, $importPath, $currentDir);

        $this->assertEquals($expectedPath, $result);
    }

    public function test_resolve_file_path_returns_null_for_nonexistent_file(): void
    {
        $importPath = 'nonexistent/file.typ';

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('resolveFilePath');
        $method->setAccessible(true);

        $result = $method->invoke($service, $importPath);

        $this->assertNull($result);
    }

    public function test_resolve_file_path_with_absolute_path(): void
    {
        $absolutePath = '/absolute/path/file.typ';

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('resolveFilePath');
        $method->setAccessible(true);

        $result = $method->invoke($service, $absolutePath);

        $this->assertNull($result); // Should return null for absolute paths starting with /
    }

    public function test_collect_dependencies_with_nested_imports(): void
    {
        $baseDir = $this->getTestWorkingDirectory() . '/test_imports';
        mkdir($baseDir, 0755, true);

        // Create main file with import
        $mainFile = $baseDir . '/main.typ';
        $mainContent = '#import "BaseStyle.typ" : *
#import "Utils.typ" : helper';
        file_put_contents($mainFile, $mainContent);

        // Create BaseStyle file with its own import
        $baseStyleFile = $baseDir . '/BaseStyle.typ';
        $baseStyleContent = '#import "Colors.typ" : *
#let baseStyle = "base"';
        file_put_contents($baseStyleFile, $baseStyleContent);

        // Create Utils file
        $utilsFile = $baseDir . '/Utils.typ';
        $utilsContent = '#let helper = "helper function"';
        file_put_contents($utilsFile, $utilsContent);

        // Create Colors file
        $colorsFile = $baseDir . '/Colors.typ';
        $colorsContent = '#let primaryColor = blue';
        file_put_contents($colorsFile, $colorsContent);

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('collectDependencies');
        $method->setAccessible(true);

        $dependencies = [];
        $method->invokeArgs($service, [$mainContent, $baseDir, &$dependencies]);

        $this->assertCount(3, $dependencies);
        $this->assertArrayHasKey($baseStyleFile, $dependencies);
        $this->assertArrayHasKey($utilsFile, $dependencies);
        $this->assertArrayHasKey($colorsFile, $dependencies);

        // Verify temp filenames are generated
        foreach ($dependencies as $dep) {
            $this->assertArrayHasKey('temp_filename', $dep);
            $this->assertArrayHasKey('original_path', $dep);
            $this->assertStringStartsWith('imported_', $dep['temp_filename']);
        }
    }

    public function test_collect_dependencies_ignores_duplicate_imports(): void
    {
        $baseDir = $this->getTestWorkingDirectory() . '/test_dups';
        mkdir($baseDir, 0755, true);

        $file = $baseDir . '/test.typ';
        $content = '#import "Base.typ" : *
#import "Base.typ" : style
#import "Other.typ" : *';
        file_put_contents($file, $content);

        file_put_contents($baseDir . '/Base.typ', '#let base = "base"');
        file_put_contents($baseDir . '/Other.typ', '#let other = "other"');

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('collectDependencies');
        $method->setAccessible(true);

        $dependencies = [];
        $method->invokeArgs($service, [$content, $baseDir, &$dependencies]);

        $this->assertCount(2, $dependencies); // Should only have Base.typ and Other.typ, not duplicate Base.typ
    }

    public function test_collect_dependencies_ignores_nonexistent_files(): void
    {
        $baseDir = $this->getTestWorkingDirectory() . '/test_missing';
        mkdir($baseDir, 0755, true);

        $content = '#import "Exists.typ" : *
#import "Missing.typ" : *
#import "AlsoMissing.typ" : helper';

        file_put_contents($baseDir . '/Exists.typ', '#let exists = "exists"');

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('collectDependencies');
        $method->setAccessible(true);

        $dependencies = [];
        $method->invokeArgs($service, [$content, $baseDir, &$dependencies]);

        $this->assertCount(1, $dependencies);
        $this->assertArrayHasKey($baseDir . '/Exists.typ', $dependencies);
    }

    public function test_rewrite_imports_with_dependencies(): void
    {
        $baseDir = $this->getTestWorkingDirectory() . '/test_rewrite';
        mkdir($baseDir, 0755, true);

        // Create the actual files so resolveFilePath can find them
        file_put_contents($baseDir . '/Base.typ', '#let base = "test"');
        file_put_contents($baseDir . '/Utils.typ', '#let utils = "test"');

        $dependencies = [
            $baseDir . '/Base.typ' => [
                'temp_filename' => 'imported_abc123_Base.typ',
                'original_path' => 'Base.typ'
            ],
            $baseDir . '/Utils.typ' => [
                'temp_filename' => 'imported_def456_Utils.typ',
                'original_path' => 'Utils.typ'
            ]
        ];

        $template = '#import "Base.typ" : *
#import "Utils.typ" : helper
#import "NonExistent.typ" : *';

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('rewriteImports');
        $method->setAccessible(true);

        $result = $method->invoke($service, $template, $dependencies, $baseDir);

        $this->assertStringContainsString('#import "imported_abc123_Base.typ" : *', $result);
        $this->assertStringContainsString('#import "imported_def456_Utils.typ" : helper', $result);
        $this->assertStringContainsString('#import "NonExistent.typ" : *', $result); // Unchanged
    }

    public function test_build_dependency_tree_processes_blade_files(): void
    {
        $baseDir = $this->getTestWorkingDirectory() . '/test_blade_imports';
        mkdir($baseDir, 0755, true);

        // Create a blade template file
        $bladeFile = $baseDir . '/Header.blade.typ';
        $bladeContent = '#let header = "Header from {{ $company }}"';
        file_put_contents($bladeFile, $bladeContent);

        // Create main template that imports the blade file
        $mainTemplate = '#import "Header.blade.typ" : *
{{ $company }} Document';

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildDependencyTreeAndResolveImports');
        $method->setAccessible(true);

        $result = $method->invoke($service, $mainTemplate, $baseDir);

        // Should process the blade file and rewrite the import
        $this->assertStringContainsString('#import "imported_', $result);
        $this->assertStringContainsString('Header.blade.typ', $result);
    }

    public function test_build_dependency_tree_processes_regular_typ_files(): void
    {
        $baseDir = $this->getTestWorkingDirectory() . '/test_regular_imports';
        mkdir($baseDir, 0755, true);

        // Create a regular typ file
        $typFile = $baseDir . '/Styles.typ';
        $typContent = '#let primaryColor = blue
#let fontSize = 12pt';
        file_put_contents($typFile, $typContent);

        // Create main template
        $mainTemplate = '#import "Styles.typ" : *
Document with styles';

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('buildDependencyTreeAndResolveImports');
        $method->setAccessible(true);

        $result = $method->invoke($service, $mainTemplate, $baseDir);

        // Should rewrite the import to use temp filename
        $this->assertStringContainsString('#import "imported_', $result);
        $this->assertStringContainsString('Styles.typ', $result);
    }

    public function test_process_blade_syntax_only_handles_errors_gracefully(): void
    {
        $content = '{{ $undefinedVariable }}';

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('processBladeSyntaxOnly');
        $method->setAccessible(true);

        $result = $method->invoke($service, $content);

        // Should return original content if blade processing fails
        $this->assertEquals($content, $result);
    }

    public function test_resolve_file_path_with_unicode_characters(): void
    {
        $importPath = 'resources/typst/文件.typ';
        $expectedPath = base_path($importPath);

        $dir = dirname($expectedPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($expectedPath, '#let unicodeFile = "test"');

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('resolveFilePath');
        $method->setAccessible(true);

        $result = $method->invoke($service, $importPath);

        $this->assertEquals($expectedPath, $result);
    }

    public function test_collect_dependencies_with_extremely_deep_imports(): void
    {
        $baseDir = $this->getTestWorkingDirectory() . '/deep_imports';
        mkdir($baseDir, 0755, true);

        // Create a chain of 10 imports
        for ($i = 1; $i <= 10; $i++) {
            $fileName = $baseDir . "/Level{$i}.typ";
            $nextLevel = $i < 10 ? "Level" . ($i + 1) : "Final";
            $content = $i < 10 ? "#import \"{$nextLevel}.typ\" : *\n#let level{$i} = \"level{$i}\"" : "#let final = \"final\"";
            file_put_contents($fileName, $content);
        }

        $mainFile = $baseDir . '/Main.typ';
        $mainContent = '#import "Level1.typ" : *';
        file_put_contents($mainFile, $mainContent);

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('collectDependencies');
        $method->setAccessible(true);

        $dependencies = [];
        $method->invokeArgs($service, [$mainContent, $baseDir, &$dependencies]);

        $this->assertCount(10, $dependencies); // All 10 levels should be found
        $this->assertArrayHasKey($baseDir . '/Level1.typ', $dependencies);
        $this->assertArrayHasKey($baseDir . '/Level10.typ', $dependencies);
    }

    public function test_collect_dependencies_detects_circular_imports(): void
    {
        $baseDir = $this->getTestWorkingDirectory() . '/circular';
        mkdir($baseDir, 0755, true);

        // Create circular imports: A -> B -> C -> A
        file_put_contents($baseDir . '/A.typ', '#import "B.typ" : *');
        file_put_contents($baseDir . '/B.typ', '#import "C.typ" : *');
        file_put_contents($baseDir . '/C.typ', '#import "A.typ" : *');

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('collectDependencies');
        $method->setAccessible(true);

        $dependencies = [];
        $method->invokeArgs($service, ['#import "A.typ" : *', $baseDir, &$dependencies]);

        // Should detect all files despite circular reference
        $this->assertCount(3, $dependencies);
        $this->assertArrayHasKey($baseDir . '/A.typ', $dependencies);
        $this->assertArrayHasKey($baseDir . '/B.typ', $dependencies);
        $this->assertArrayHasKey($baseDir . '/C.typ', $dependencies);
    }

    public function test_collect_dependencies_with_malformed_import_syntax(): void
    {
        $baseDir = $this->getTestWorkingDirectory() . '/malformed';
        mkdir($baseDir, 0755, true);

        $content = '#import "Valid.typ" : *
#import invalid syntax here
#import "AlsoValid.typ" : *
#import "" : *
#import "Missing.typ" : *';

        file_put_contents($baseDir . '/Valid.typ', '#let valid = "valid"');
        file_put_contents($baseDir . '/AlsoValid.typ', '#let alsoValid = "alsoValid"');

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('collectDependencies');
        $method->setAccessible(true);

        $dependencies = [];
        $method->invokeArgs($service, [$content, $baseDir, &$dependencies]);

        // Should only collect valid imports
        $this->assertCount(2, $dependencies);
        $this->assertArrayHasKey($baseDir . '/Valid.typ', $dependencies);
        $this->assertArrayHasKey($baseDir . '/AlsoValid.typ', $dependencies);
    }

    public function test_rewrite_imports_with_complex_dependencies(): void
    {
        $baseDir = $this->getTestWorkingDirectory() . '/complex_rewrite';
        mkdir($baseDir, 0755, true);

        // Create files with various import patterns
        file_put_contents($baseDir . '/Base.typ', '#let base = "base"');
        file_put_contents($baseDir . '/Utils.typ', '#let utils = "utils"');
        file_put_contents($baseDir . '/Theme.typ', '#let theme = "theme"');

        $dependencies = [
            $baseDir . '/Base.typ' => [
                'temp_filename' => 'imported_abc123_Base.typ',
                'original_path' => 'Base.typ'
            ],
            $baseDir . '/Utils.typ' => [
                'temp_filename' => 'imported_def456_Utils.typ',
                'original_path' => 'Utils.typ'
            ],
            $baseDir . '/Theme.typ' => [
                'temp_filename' => 'imported_ghi789_Theme.typ',
                'original_path' => 'Theme.typ'
            ]
        ];

        $template = '#import "Base.typ" : *
#import "Utils.typ" : helper as utilsHelper
#import "Theme.typ" : *
#import "NonExistent.typ" : *
#let localVar = "local"';

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('rewriteImports');
        $method->setAccessible(true);

        $result = $method->invoke($service, $template, $dependencies, $baseDir);

        $this->assertStringContainsString('#import "imported_abc123_Base.typ" : *', $result);
        $this->assertStringContainsString('#import "imported_def456_Utils.typ" : helper as utilsHelper', $result);
        $this->assertStringContainsString('#import "imported_ghi789_Theme.typ" : *', $result);
        $this->assertStringContainsString('#import "NonExistent.typ" : *', $result); // Unchanged
        $this->assertStringContainsString('#let localVar = "local"', $result); // Unchanged
    }

    private function createMockProcess(bool $successful, string $output = '', string $errorOutput = ''): object
    {
        return new class($successful, $output, $errorOutput)
        {
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