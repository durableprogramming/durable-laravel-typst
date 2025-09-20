<?php

namespace Durableprogramming\LaravelTypst\Tests\Unit;

use Durableprogramming\LaravelTypst\Exceptions\TypstCompilationException;
use Durableprogramming\LaravelTypst\Tests\TestCase;
use Durableprogramming\LaravelTypst\TypstService;
use Illuminate\Support\Facades\Process;

class SpecialImportSyntaxTest extends TestCase
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

    public function test_special_import_with_data_creates_variables_and_import(): void
    {
        $template = '#!import "BaseTemplate.typ" with_data: config
Regular content here.';

        $data = [
            'config' => [
                'title' => 'Test Document',
                'author' => 'John Doe',
                'version' => 1.0
            ]
        ];

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('processSpecialImports');
        $method->setAccessible(true);

        $result = $method->invoke($service, $template, $data);

        // Should contain the main variable definition
        $this->assertStringContainsString('#let config = (', $result);
        $this->assertStringContainsString('title: "Test Document"', $result);
        $this->assertStringContainsString('author: "John Doe"', $result);
        $this->assertStringContainsString('version: 1', $result);

        // Should contain individual variable definitions
        $this->assertStringContainsString('#let title = "Test Document"', $result);
        $this->assertStringContainsString('#let author = "John Doe"', $result);
        $this->assertStringContainsString('#let version = 1', $result);

        // Should contain the import statement
        $this->assertStringContainsString('#import "BaseTemplate.typ" : *', $result);
    }

    public function test_special_import_without_template_name_only_creates_variables(): void
    {
        $template = '#!import "" with_data: settings
Document content.';

        $data = [
            'settings' => [
                'theme' => 'dark',
                'fontSize' => 14
            ]
        ];

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('processSpecialImports');
        $method->setAccessible(true);

        $result = $method->invoke($service, $template, $data);

        // Should contain variable definitions but no import
        $this->assertStringContainsString('#let settings = (', $result);
        $this->assertStringContainsString('#let theme = "dark"', $result);
        $this->assertStringContainsString('#let fontSize = 14', $result);
        $this->assertStringNotContainsString('#import', $result);
    }

    public function test_special_import_with_missing_data_falls_back_to_regular_import(): void
    {
        $template = '#!import "Template.typ" with_data: missingData
Content here.';

        $data = [
            'otherData' => ['key' => 'value']
        ];

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('processSpecialImports');
        $method->setAccessible(true);

        $result = $method->invoke($service, $template, $data);

        // Should fall back to regular import
        $this->assertStringContainsString('#import "Template.typ" : *', $result);
        $this->assertStringNotContainsString('#let missingData', $result);
    }

    public function test_special_import_with_non_array_data_falls_back_to_regular_import(): void
    {
        $template = '#!import "Template.typ" with_data: scalarData
Content here.';

        $data = [
            'scalarData' => 'not an array'
        ];

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('processSpecialImports');
        $method->setAccessible(true);

        $result = $method->invoke($service, $template, $data);

        // Should fall back to regular import
        $this->assertStringContainsString('#import "Template.typ" : *', $result);
        $this->assertStringNotContainsString('#let scalarData', $result);
    }

    public function test_special_import_with_nested_array_data(): void
    {
        $template = '#!import "NestedTemplate.typ" with_data: nestedConfig
Document content.';

        $data = [
            'nestedConfig' => [
                'user' => [
                    'name' => 'John',
                    'preferences' => [
                        'theme' => 'dark',
                        'notifications' => true
                    ]
                ],
                'items' => ['item1', 'item2', 'item3']
            ]
        ];

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('processSpecialImports');
        $method->setAccessible(true);

        $result = $method->invoke($service, $template, $data);

        // Should handle nested structures
        $this->assertStringContainsString('#let nestedConfig = (', $result);
        $this->assertStringContainsString('user: (', $result);
        $this->assertStringContainsString('preferences: (', $result);
        $this->assertStringContainsString('items: (', $result);
        $this->assertStringContainsString('#import "NestedTemplate.typ" : *', $result);
    }

    public function test_special_import_with_numeric_keys_converts_to_strings(): void
    {
        $template = '#!import "NumericTemplate.typ" with_data: numericData
Content.';

        $data = [
            'numericData' => [
                0 => 'first',
                1 => 'second',
                'valid_key' => 'valid value'
            ]
        ];

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('processSpecialImports');
        $method->setAccessible(true);

        $result = $method->invoke($service, $template, $data);

        // Numeric keys should be converted to strings in the main variable
        $this->assertStringContainsString('"0": "first"', $result);
        $this->assertStringContainsString('"1": "second"', $result);
        $this->assertStringContainsString('valid_key: "valid value"', $result);

        // But should not create individual variables for numeric keys
        $this->assertStringNotContainsString('#let 0 =', $result);
        $this->assertStringNotContainsString('#let 1 =', $result);
        $this->assertStringContainsString('#let valid_key = "valid value"', $result);
    }

    public function test_special_import_with_invalid_variable_names(): void
    {
        $template = '#!import "InvalidTemplate.typ" with_data: invalidData
Content.';

        $data = [
            'invalidData' => [
                'validName' => 'valid',
                'invalid-name' => 'invalid',
                '123start' => 'invalid',
                'valid_name' => 'valid'
            ]
        ];

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('processSpecialImports');
        $method->setAccessible(true);

        $result = $method->invoke($service, $template, $data);

        // Should only create variables for valid names
        $this->assertStringContainsString('#let validName = "valid"', $result);
        $this->assertStringContainsString('#let valid_name = "valid"', $result);
        $this->assertStringNotContainsString('#let invalid-name', $result);
        $this->assertStringNotContainsString('#let 123start', $result);
    }

    public function test_special_import_with_mixed_data_types(): void
    {
        $template = '#!import "MixedTemplate.typ" with_data: mixedData
Content.';

        $data = [
            'mixedData' => [
                'string' => 'text',
                'number' => 42,
                'boolean' => true,
                'null_value' => null,
                'array' => ['a', 'b', 'c']
            ]
        ];

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('processSpecialImports');
        $method->setAccessible(true);

        $result = $method->invoke($service, $template, $data);

        // Should handle different data types correctly
        $this->assertStringContainsString('#let string = "text"', $result);
        $this->assertStringContainsString('#let number = 42', $result);
        $this->assertStringContainsString('#let boolean = true', $result);
        $this->assertStringContainsString('#let null_value = ""', $result); // null becomes empty string
        $this->assertStringContainsString('#let array = ("a", "b", "c")', $result);
    }

    public function test_multiple_special_imports_in_same_template(): void
    {
        $template = '#!import "Header.typ" with_data: headerData
Content here
#!import "Footer.typ" with_data: footerData
End content.';

        $data = [
            'headerData' => ['title' => 'Header Title'],
            'footerData' => ['copyright' => '2024']
        ];

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('processSpecialImports');
        $method->setAccessible(true);

        $result = $method->invoke($service, $template, $data);

        // Should process both imports
        $this->assertStringContainsString('#let headerData = (', $result);
        $this->assertStringContainsString('#let title = "Header Title"', $result);
        $this->assertStringContainsString('#import "Header.typ" : *', $result);

        $this->assertStringContainsString('#let footerData = (', $result);
        $this->assertStringContainsString('#let copyright = "2024"', $result);
        $this->assertStringContainsString('#import "Footer.typ" : *', $result);
    }

    public function test_special_import_with_empty_data_array(): void
    {
        $template = '#!import "EmptyTemplate.typ" with_data: emptyData
Content.';

        $data = [
            'emptyData' => []
        ];

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('processSpecialImports');
        $method->setAccessible(true);

        $result = $method->invoke($service, $template, $data);

        // Should create empty dictionary but still include import
        $this->assertStringContainsString('#let emptyData = ()', $result);
        $this->assertStringContainsString('#import "EmptyTemplate.typ" : *', $result);
    }

    public function test_special_import_with_typst_keywords_as_variable_names(): void
    {
        $template = '#!import "KeywordTemplate.typ" with_data: keywordData
Content.';

        $data = [
            'keywordData' => [
                'let' => 'keyword_value',
                'import' => 'another_keyword',
                'set' => 'third_keyword',
                'if' => 'conditional_keyword',
                'for' => 'loop_keyword'
            ]
        ];

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('processSpecialImports');
        $method->setAccessible(true);

        $result = $method->invoke($service, $template, $data);

        // Should create variables even with keyword names (Typst allows this)
        $this->assertStringContainsString('#let let = "keyword_value"', $result);
        $this->assertStringContainsString('#let import = "another_keyword"', $result);
        $this->assertStringContainsString('#let set = "third_keyword"', $result);
        $this->assertStringContainsString('#let if = "conditional_keyword"', $result);
        $this->assertStringContainsString('#let for = "loop_keyword"', $result);
    }

    public function test_special_import_with_data_containing_special_characters(): void
    {
        $template = '#!import "SpecialCharsTemplate.typ" with_data: specialData
Content.';

        $data = [
            'specialData' => [
                'quotes' => 'String with "double quotes" and \'single quotes\'',
                'backslashes' => 'Path\\to\\file',
                'newlines' => "Line 1\nLine 2\nLine 3",
                'tabs' => "Col1\tCol2\tCol3",
                'unicode' => '🚀 Unicode: 中文 русский éñüîôç'
            ]
        ];

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('processSpecialImports');
        $method->setAccessible(true);

        $result = $method->invoke($service, $template, $data);

        // Should properly escape special characters
        $this->assertStringContainsString('#let quotes = "String with \\"double quotes\\" and \\\'single quotes\\\'"', $result);
        $this->assertStringContainsString('backslashes: "Path\\\\to\\\\file"', $result);
        $this->assertStringContainsString('newlines: "Line 1\\nLine 2\\nLine 3"', $result);
        $this->assertStringContainsString('tabs: "Col1\\tCol2\\tCol3"', $result);
        $this->assertStringContainsString('unicode: "🚀 Unicode: 中文 русский éñüîôç"', $result);
    }

    public function test_special_import_with_very_large_data_arrays(): void
    {
        $template = '#!import "LargeDataTemplate.typ" with_data: largeData
Content.';

        // Create a large data array
        $largeData = [];
        for ($i = 0; $i < 1000; $i++) {
            $largeData["key{$i}"] = "value{$i}_" . str_repeat('x', 100); // Large values too
        }

        $data = ['largeData' => $largeData];

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('processSpecialImports');
        $method->setAccessible(true);

        $result = $method->invoke($service, $template, $data);

        // Should handle large data without issues
        $this->assertStringContainsString('#let largeData = (', $result);
        $this->assertStringContainsString('key0: "value0_', $result);
        $this->assertStringContainsString('key999: "value999_', $result);
        $this->assertStringContainsString('#import "LargeDataTemplate.typ" : *', $result);

        // Check that all individual variables are created
        $this->assertStringContainsString('#let key0 = "value0_', $result);
        $this->assertStringContainsString('#let key999 = "value999_', $result);
    }

    public function test_special_import_with_deeply_nested_data(): void
    {
        $template = '#!import "DeepTemplate.typ" with_data: deepData
Content.';

        $deepData = ['level' => 1];
        $current = &$deepData;
        for ($i = 2; $i <= 15; $i++) {
            $current['nested'] = ['level' => $i];
            $current = &$current['nested'];
        }

        $data = ['deepData' => $deepData];

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('processSpecialImports');
        $method->setAccessible(true);

        $result = $method->invoke($service, $template, $data);

        // Should handle deep nesting
        $this->assertStringContainsString('#let deepData = (', $result);
        $this->assertStringContainsString('level: 1', $result);
        $this->assertStringContainsString('level: 15', $result);
        $this->assertStringContainsString('#import "DeepTemplate.typ" : *', $result);
    }

    public function test_special_import_with_array_data_containing_objects(): void
    {
        $template = '#!import "ObjectTemplate.typ" with_data: objectData
Content.';

        $data = [
            'objectData' => [
                'string' => 'text',
                'number' => 42,
                'object' => new \stdClass(), // Should be converted to empty string
                'array' => ['item1', 'item2'],
                'null' => null // Should be converted to empty string
            ]
        ];

        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('processSpecialImports');
        $method->setAccessible(true);

        $result = $method->invoke($service, $template, $data);

        // Objects and null should be converted to empty strings
        $this->assertStringContainsString('string: "text"', $result);
        $this->assertStringContainsString('number: 42', $result);
        $this->assertStringContainsString('object: ""', $result);
        $this->assertStringContainsString('array: ("item1", "item2")', $result);
        $this->assertStringContainsString('null: ""', $result);

        // Individual variables
        $this->assertStringContainsString('#let string = "text"', $result);
        $this->assertStringContainsString('#let number = 42', $result);
        $this->assertStringContainsString('#let object = ""', $result);
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