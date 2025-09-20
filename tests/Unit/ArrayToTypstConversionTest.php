<?php

namespace Durableprogramming\LaravelTypst\Tests\Unit;

use Durableprogramming\LaravelTypst\Tests\TestCase;
use Durableprogramming\LaravelTypst\TypstService;

class ArrayToTypstConversionTest extends TestCase
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

    public function test_array_to_typst_value_converts_string(): void
    {
        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('arrayToTypstValue');
        $method->setAccessible(true);

        $result = $method->invoke($service, 'Hello World');
        $this->assertEquals('"Hello World"', $result);

        $result = $method->invoke($service, 'String with "quotes"');
        $this->assertEquals('"String with \\"quotes\\""', $result);
    }

    public function test_array_to_typst_value_converts_numeric(): void
    {
        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('arrayToTypstValue');
        $method->setAccessible(true);

        $result = $method->invoke($service, 42);
        $this->assertEquals('42', $result);

        $result = $method->invoke($service, 3.14);
        $this->assertEquals('3.14', $result);

        $result = $method->invoke($service, 0);
        $this->assertEquals('0', $result);

        $result = $method->invoke($service, -15);
        $this->assertEquals('-15', $result);
    }

    public function test_array_to_typst_value_converts_boolean(): void
    {
        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('arrayToTypstValue');
        $method->setAccessible(true);

        $result = $method->invoke($service, true);
        $this->assertEquals('true', $result);

        $result = $method->invoke($service, false);
        $this->assertEquals('false', $result);
    }

    public function test_array_to_typst_value_converts_indexed_array(): void
    {
        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('arrayToTypstValue');
        $method->setAccessible(true);

        $array = ['apple', 'banana', 'cherry'];
        $result = $method->invoke($service, $array);
        $this->assertEquals('("apple", "banana", "cherry")', $result);

        $mixedArray = [1, 'two', true, 4.5];
        $result = $method->invoke($service, $mixedArray);
        $this->assertEquals('(1, "two", true, 4.5)', $result);
    }

    public function test_array_to_typst_value_converts_associative_array(): void
    {
        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('arrayToTypstValue');
        $method->setAccessible(true);

        $array = ['name' => 'John', 'age' => 30, 'active' => true];
        $result = $method->invoke($service, $array);
        $this->assertEquals('(name: "John", age: 30, active: true)', $result);
    }

    public function test_array_to_typst_value_converts_nested_structures(): void
    {
        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('arrayToTypstValue');
        $method->setAccessible(true);

        $nested = [
            'user' => [
                'name' => 'Alice',
                'preferences' => [
                    'theme' => 'dark',
                    'notifications' => true
                ]
            ],
            'items' => ['book', 'pen', 'notebook']
        ];

        $result = $method->invoke($service, $nested);

        // Should contain nested structure
        $this->assertStringContainsString('user: (', $result);
        $this->assertStringContainsString('name: "Alice"', $result);
        $this->assertStringContainsString('preferences: (', $result);
        $this->assertStringContainsString('theme: "dark"', $result);
        $this->assertStringContainsString('notifications: true', $result);
        $this->assertStringContainsString('items: ("book", "pen", "notebook")', $result);
    }

    public function test_array_to_typst_value_converts_numeric_keys_as_strings(): void
    {
        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('arrayToTypstValue');
        $method->setAccessible(true);

        $array = [
            0 => 'first',
            1 => 'second',
            'valid_key' => 'valid'
        ];

        $result = $method->invoke($service, $array);
        $this->assertStringContainsString('"0": "first"', $result);
        $this->assertStringContainsString('"1": "second"', $result);
        $this->assertStringContainsString('valid_key: "valid"', $result);
    }

    public function test_array_to_typst_value_converts_empty_array(): void
    {
        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('arrayToTypstValue');
        $method->setAccessible(true);

        $result = $method->invoke($service, []);
        $this->assertEquals('()', $result);
    }

    public function test_array_to_typst_value_converts_null_to_empty_string(): void
    {
        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('arrayToTypstValue');
        $method->setAccessible(true);

        $result = $method->invoke($service, null);
        $this->assertEquals('""', $result);
    }

    public function test_array_to_typst_value_converts_object_to_empty_string(): void
    {
        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('arrayToTypstValue');
        $method->setAccessible(true);

        $object = new \stdClass();
        $object->property = 'value';

        $result = $method->invoke($service, $object);
        $this->assertEquals('""', $result);
    }

    public function test_array_to_typst_value_handles_deeply_nested_structures(): void
    {
        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('arrayToTypstValue');
        $method->setAccessible(true);

        $deep = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'value' => 'deep',
                        'numbers' => [1, 2, 3]
                    ]
                ]
            ]
        ];

        $result = $method->invoke($service, $deep);

        $this->assertStringContainsString('level1: (', $result);
        $this->assertStringContainsString('level2: (', $result);
        $this->assertStringContainsString('level3: (', $result);
        $this->assertStringContainsString('value: "deep"', $result);
        $this->assertStringContainsString('numbers: (1, 2, 3)', $result);
    }

    public function test_array_to_typst_value_handles_special_characters_in_strings(): void
    {
        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('arrayToTypstValue');
        $method->setAccessible(true);

        $special = 'String with "quotes", \'single quotes\', and \\backslashes\\';
        $result = $method->invoke($service, $special);

        $this->assertStringContainsString('\\"quotes\\"', $result);
        $this->assertStringContainsString('\\\'single quotes\\\'', $result);
        $this->assertStringContainsString('\\\\backslashes\\\\', $result);
    }

    public function test_array_to_typst_value_handles_large_arrays(): void
    {
        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('arrayToTypstValue');
        $method->setAccessible(true);

        $largeArray = range(1, 100); // Creates array [1, 2, 3, ..., 100]
        $result = $method->invoke($service, $largeArray);

        $this->assertStringStartsWith('(', $result);
        $this->assertStringEndsWith(')', $result);
        $this->assertStringContainsString('1, 2, 3', $result);
        $this->assertStringContainsString('100', $result);
    }

    public function test_array_to_typst_value_handles_objects_with_toString(): void
    {
        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('arrayToTypstValue');
        $method->setAccessible(true);

        $object = new class {
            public function __toString(): string
            {
                return 'custom string representation';
            }
        };

        $result = $method->invoke($service, $object);
        $this->assertEquals('"custom string representation"', $result);
    }

    public function test_array_to_typst_value_handles_extremely_deep_nesting(): void
    {
        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('arrayToTypstValue');
        $method->setAccessible(true);

        $deep = ['level' => 1];
        $current = &$deep;
        for ($i = 2; $i <= 20; $i++) {
            $current['nested'] = ['level' => $i];
            $current = &$current['nested'];
        }

        $result = $method->invoke($service, $deep);

        // Should handle deep nesting without recursion issues
        $this->assertStringContainsString('level: 1', $result);
        $this->assertStringContainsString('level: 20', $result);
        $this->assertStringStartsWith('(level: ', $result);
    }

    public function test_array_to_typst_value_handles_circular_references(): void
    {
        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('arrayToTypstValue');
        $method->setAccessible(true);

        $array1 = ['name' => 'array1'];
        $array2 = ['name' => 'array2', 'ref' => &$array1];
        $array1['ref'] = &$array2;

        // Should handle circular references gracefully (PHP will handle this)
        $result = $method->invoke($service, $array1);
        $this->assertStringContainsString('name: "array1"', $result);
        $this->assertStringContainsString('name: "array2"', $result);
    }

    public function test_array_to_typst_value_handles_float_precision(): void
    {
        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('arrayToTypstValue');
        $method->setAccessible(true);

        $result = $method->invoke($service, 1.23456789012345);
        $this->assertEquals('1.2345678901235', $result);

        $result = $method->invoke($service, 1.0);
        $this->assertEquals('1', $result); // Should not include .0

        $result = $method->invoke($service, 0.0);
        $this->assertEquals('0', $result);
    }

    public function test_array_to_typst_value_handles_scientific_notation(): void
    {
        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('arrayToTypstValue');
        $method->setAccessible(true);

        $result = $method->invoke($service, 1.23e-4);
        $this->assertEquals('0.000123', $result);

        $result = $method->invoke($service, 1.23e4);
        $this->assertEquals('12300', $result);
    }

    public function test_array_to_typst_value_handles_multidimensional_arrays(): void
    {
        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('arrayToTypstValue');
        $method->setAccessible(true);

        $multiArray = [
            [1, 2, 3],
            [4, 5, 6],
            [7, 8, 9]
        ];

        $result = $method->invoke($service, $multiArray);
        $this->assertStringContainsString('(1, 2, 3)', $result);
        $this->assertStringContainsString('(4, 5, 6)', $result);
        $this->assertStringContainsString('(7, 8, 9)', $result);
    }

    public function test_array_to_typst_value_handles_mixed_key_types(): void
    {
        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('arrayToTypstValue');
        $method->setAccessible(true);

        $mixedKeys = [
            'string_key' => 'value1',
            0 => 'value2',
            1 => 'value3',
            '2' => 'value4',
            true => 'value5', // This will be converted to 1
            false => 'value6', // This will be converted to 0
        ];

        $result = $method->invoke($service, $mixedKeys);

        $this->assertStringContainsString('string_key: "value1"', $result);
        $this->assertStringContainsString('"0": "value6"', $result);
        $this->assertStringContainsString('"1": "value5"', $result); // true becomes 1
        $this->assertStringContainsString('"2": "value4"', $result);
        $this->assertStringContainsString('"0": "value6"', $result); // false becomes 0
    }

    public function test_array_to_typst_value_handles_unicode_strings(): void
    {
        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('arrayToTypstValue');
        $method->setAccessible(true);

        $unicodeString = 'Hello 世界 🌍 éñüîôç';
        $result = $method->invoke($service, $unicodeString);
        $this->assertEquals('"Hello 世界 🌍 éñüîôç"', $result);
    }

    public function test_array_to_typst_value_handles_closures(): void
    {
        $service = new TypstService(['working_directory' => $this->getTestWorkingDirectory()]);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('arrayToTypstValue');
        $method->setAccessible(true);

        $closure = function() { return 'test'; };
        $result = $method->invoke($service, $closure);
        $this->assertEquals('""', $result); // Closures should be treated as objects
    }
}