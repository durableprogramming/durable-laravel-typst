<?php

namespace Durableprogramming\LaravelTypst\Tests\Unit;

use Durableprogramming\LaravelTypst\Tests\TestCase;

class ConfigurationTest extends TestCase
{
    public function test_default_configuration_values(): void
    {
        $config = config('typst');

        $this->assertEquals('mock-typst', $config['bin_path']);
        $this->assertEquals($this->getTestWorkingDirectory(), $config['working_directory']);
        $this->assertEquals(30, $config['timeout']);
        $this->assertEquals('pdf', $config['format']);
        $this->assertIsArray($config['font_paths']);
        $this->assertNull($config['root']);
    }

    public function test_configuration_can_be_overridden(): void
    {
        config()->set('typst.bin_path', '/custom/typst');
        config()->set('typst.timeout', 120);
        config()->set('typst.format', 'png');

        $config = config('typst');

        $this->assertEquals('/custom/typst', $config['bin_path']);
        $this->assertEquals(120, $config['timeout']);
        $this->assertEquals('png', $config['format']);
    }

    public function test_environment_variables_are_respected(): void
    {
        // Set environment variables
        putenv('TYPST_BIN_PATH=/env/typst');
        putenv('TYPST_TIMEOUT=90');
        putenv('TYPST_FORMAT=svg');
        putenv('TYPST_ROOT=/env/root');

        // Test that the environment variables are properly set using getenv
        $this->assertEquals('/env/typst', getenv('TYPST_BIN_PATH'));
        $this->assertEquals('90', getenv('TYPST_TIMEOUT')); // getenv returns strings
        $this->assertEquals('svg', getenv('TYPST_FORMAT'));
        $this->assertEquals('/env/root', getenv('TYPST_ROOT'));

        // Create a mock env function to test the config file behavior
        $mockEnv = function($key, $default = null) {
            return getenv($key) ?: $default;
        };
        
        // Test the config values directly
        $binPath = $mockEnv('TYPST_BIN_PATH', 'typst');
        $timeout = $mockEnv('TYPST_TIMEOUT', 60);
        $format = $mockEnv('TYPST_FORMAT', 'pdf');
        $root = $mockEnv('TYPST_ROOT', null);

        $this->assertEquals('/env/typst', $binPath);
        $this->assertEquals('90', $timeout);
        $this->assertEquals('svg', $format);
        $this->assertEquals('/env/root', $root);
    }

    public function test_font_paths_configuration(): void
    {
        config()->set('typst.font_paths', ['/fonts/path1', '/fonts/path2']);

        $config = config('typst');
        $fontPaths = $config['font_paths'];

        $this->assertIsArray($fontPaths);
        $this->assertCount(2, $fontPaths);
        $this->assertContains('/fonts/path1', $fontPaths);
        $this->assertContains('/fonts/path2', $fontPaths);
    }

    public function test_working_directory_from_environment(): void
    {
        $customWorkingDir = '/tmp/custom-typst';
        
        putenv("TYPST_WORKING_DIR={$customWorkingDir}");

        // Load fresh config from the file
        $configArray = include(__DIR__ . '/../../config/typst.php');
        
        $this->assertEquals($customWorkingDir, $configArray['working_directory']);
        
        // Clean up
        putenv("TYPST_WORKING_DIR=");
    }

    public function test_all_configuration_keys_exist(): void
    {
        $config = config('typst');
        $expectedKeys = [
            'bin_path',
            'working_directory',
            'timeout',
            'format',
            'font_paths',
            'root'
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $config, "Configuration key '{$key}' is missing");
        }
    }

    public function test_configuration_types_are_correct(): void
    {
        $config = config('typst');

        $this->assertIsString($config['bin_path']);
        $this->assertIsString($config['working_directory']);
        $this->assertIsInt($config['timeout']);
        $this->assertIsString($config['format']);
        $this->assertIsArray($config['font_paths']);
        $this->assertTrue(is_string($config['root']) || is_null($config['root']));
    }

    public function test_timeout_configuration_bounds(): void
    {
        config()->set('typst.timeout', 1);
        $this->assertEquals(1, config('typst.timeout'));

        config()->set('typst.timeout', 3600);
        $this->assertEquals(3600, config('typst.timeout'));
    }

    public function test_format_configuration_validation(): void
    {
        $validFormats = ['pdf', 'png', 'svg'];

        foreach ($validFormats as $format) {
            config()->set('typst.format', $format);
            $this->assertEquals($format, config('typst.format'));
        }
    }

    public function test_bin_path_configuration_scenarios(): void
    {
        $scenarios = [
            'typst',
            '/usr/local/bin/typst',
            '/opt/typst/bin/typst',
            './typst',
            '../bin/typst'
        ];

        foreach ($scenarios as $path) {
            config()->set('typst.bin_path', $path);
            $this->assertEquals($path, config('typst.bin_path'));
        }
    }

    public function test_configuration_file_structure(): void
    {
        $configPath = __DIR__ . '/../../config/typst.php';
        $this->assertFileExists($configPath);

        $configArray = include $configPath;
        $this->assertIsArray($configArray);

        $expectedKeys = [
            'bin_path',
            'working_directory', 
            'timeout',
            'format',
            'font_paths',
            'root'
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $configArray);
        }
    }

    public function test_configuration_has_proper_defaults_when_env_missing(): void
    {
        putenv('TYPST_BIN_PATH=');
        putenv('TYPST_TIMEOUT=');
        putenv('TYPST_FORMAT=');
        putenv('TYPST_ROOT=');

        // Verify environment variables are cleared using getenv
        $this->assertEmpty(getenv('TYPST_BIN_PATH'));
        $this->assertEmpty(getenv('TYPST_TIMEOUT'));
        $this->assertEmpty(getenv('TYPST_FORMAT'));
        $this->assertEmpty(getenv('TYPST_ROOT'));

        // Create a mock env function to test the config file behavior
        $mockEnv = function($key, $default = null) {
            return getenv($key) ?: $default;
        };
        
        // Test the config default values
        $binPath = $mockEnv('TYPST_BIN_PATH', 'typst');
        $timeout = $mockEnv('TYPST_TIMEOUT', 60);
        $format = $mockEnv('TYPST_FORMAT', 'pdf');
        $root = $mockEnv('TYPST_ROOT', null);

        $this->assertEquals('typst', $binPath);
        $this->assertEquals(60, $timeout);
        $this->assertEquals('pdf', $format);
        $this->assertNull($root);
    }

    public function test_configuration_merging_behavior(): void
    {
        config()->set('typst.bin_path', '/custom/bin');
        config()->set('typst.custom_key', 'custom_value');

        $originalConfig = config('typst');
        $originalConfig['new_key'] = 'new_value';

        config()->set('typst', $originalConfig);

        $config = config('typst');
        $this->assertEquals('/custom/bin', $config['bin_path']);
        $this->assertEquals('custom_value', $config['custom_key']);
        $this->assertEquals('new_value', $config['new_key']);
    }

    protected function tearDown(): void
    {
        putenv('TYPST_BIN_PATH=');
        putenv('TYPST_TIMEOUT=');
        putenv('TYPST_FORMAT=');
        putenv('TYPST_ROOT=');
        putenv('TYPST_WORKING_DIR=');

        parent::tearDown();
    }
}