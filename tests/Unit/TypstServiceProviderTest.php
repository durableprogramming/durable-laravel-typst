<?php

namespace Durableprogramming\LaravelTypst\Tests\Unit;

use Durableprogramming\LaravelTypst\Tests\TestCase;
use Durableprogramming\LaravelTypst\TypstService;
use Durableprogramming\LaravelTypst\TypstServiceProvider;

class TypstServiceProviderTest extends TestCase
{
    public function test_service_is_registered_as_singleton(): void
    {
        $this->assertTrue($this->app->bound('typst'));
        $this->assertTrue($this->app->isShared('typst'));
    }

    public function test_service_provider_provides_correct_services(): void
    {
        $provider = new TypstServiceProvider($this->app);
        $provides = $provider->provides();

        $this->assertContains('typst', $provides);
        $this->assertContains(TypstService::class, $provides);
    }

    public function test_typst_service_is_resolved_correctly(): void
    {
        $service = $this->app->make('typst');

        $this->assertInstanceOf(TypstService::class, $service);
    }

    public function test_typst_service_is_aliased(): void
    {
        $serviceByBinding = $this->app->make('typst');
        $serviceByClass = $this->app->make(TypstService::class);

        $this->assertSame($serviceByBinding, $serviceByClass);
    }

    public function test_typst_service_receives_config(): void
    {
        $this->app['config']->set('typst.bin_path', '/test/bin/typst');
        $this->app['config']->set('typst.timeout', 120);

        $service = $this->app->make('typst');
        $config = $service->getConfig();

        $this->assertEquals('/test/bin/typst', $config['bin_path']);
        $this->assertEquals(120, $config['timeout']);
    }

    public function test_config_is_published(): void
    {
        $provider = new TypstServiceProvider($this->app);

        $this->artisan('vendor:publish', [
            '--tag' => 'typst-config',
            '--force' => true,
        ]);

        $configPath = config_path('typst.php');
        $this->assertFileExists($configPath);

        $config = require $configPath;
        $this->assertIsArray($config);
        $this->assertArrayHasKey('bin_path', $config);
        $this->assertArrayHasKey('working_directory', $config);
        $this->assertArrayHasKey('timeout', $config);
        $this->assertArrayHasKey('format', $config);
    }

    public function test_service_provider_is_deferred(): void
    {
        $provider = new TypstServiceProvider($this->app);

        $this->assertNotEmpty($provider->provides());
    }

    protected function tearDown(): void
    {
        $configPath = config_path('typst.php');
        if (file_exists($configPath)) {
            unlink($configPath);
        }

        parent::tearDown();
    }
}
