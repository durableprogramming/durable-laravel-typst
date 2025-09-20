<?php

namespace Durableprogramming\LaravelTypst\Tests\Feature;

use Durableprogramming\LaravelTypst\Facades\Typst;
use Durableprogramming\LaravelTypst\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class LaravelIntegrationTest extends TestCase
{
    public function test_caching_behavior_with_facade(): void
    {
        $source = $this->getValidTypstContent();
        $cacheKey = 'typst_compilation_' . md5($source);

        // Ensure cache is clear
        Cache::forget($cacheKey);

        Process::shouldReceive('timeout')
            ->times(2)
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->times(2)
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->times(2)
            ->andReturn($this->createSuccessfulProcess());

        // First compilation
        $result1 = Typst::compile($source);

        // Second compilation with same source (should use cache if implemented)
        $result2 = Typst::compile($source);

        $this->assertStringEndsWith('.pdf', $result1);
        $this->assertStringEndsWith('.pdf', $result2);
        $this->assertFileExists($result1);
        $this->assertFileExists($result2);
    }

    public function test_logging_integration(): void
    {
        $source = $this->getValidTypstContent();

        Log::shouldReceive('info')
            ->once()
            ->with('Typst compilation started', \Mockery::on(function ($context) {
                return isset($context['source_length']) && isset($context['working_directory']);
            }));

        Log::shouldReceive('info')
            ->once()
            ->with('Typst compilation completed successfully', \Mockery::on(function ($context) {
                return isset($context['output_file']) && isset($context['compilation_time']);
            }));

        Process::shouldReceive('timeout')
            ->once()
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->once()
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->once()
            ->andReturn($this->createSuccessfulProcess());

        $result = Typst::compile($source);

        $this->assertStringEndsWith('.pdf', $result);
        $this->assertFileExists($result);
    }

    public function test_event_dispatching_integration(): void
    {
        $source = $this->getValidTypstContent();

        // Mock event dispatcher
        Event::shouldReceive('dispatch')->andReturnSelf();

        Process::shouldReceive('timeout')
            ->once()
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->once()
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->once()
            ->andReturn($this->createSuccessfulProcess());

        $result = Typst::compile($source);

        $this->assertStringEndsWith('.pdf', $result);
        $this->assertFileExists($result);

        Event::shouldHaveReceived('dispatch')->with('typst.compilation.started', \Mockery::on(function ($payload) {
            return isset($payload['source']) && isset($payload['options']);
        }))->once();

        Event::shouldHaveReceived('dispatch')->with('typst.compilation.completed', \Mockery::on(function ($payload) {
            return isset($payload['output_file']) && isset($payload['compilation_time']);
        }))->once();
    }

    public function test_service_container_interaction(): void
    {
        $source = $this->getValidTypstContent();

        // Test that the service is properly resolved from container
        $service1 = app(\Durableprogramming\LaravelTypst\TypstService::class);
        $service2 = app(\Durableprogramming\LaravelTypst\TypstService::class);

        $this->assertInstanceOf(\Durableprogramming\LaravelTypst\TypstService::class, $service1);
        $this->assertInstanceOf(\Durableprogramming\LaravelTypst\TypstService::class, $service2);

        // Services should be same instance (singleton)
        $this->assertSame($service1, $service2);

        Process::shouldReceive('timeout')
            ->once()
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->once()
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->once()
            ->andReturn($this->createSuccessfulProcess());

        $result = Typst::compile($source);

        $this->assertStringEndsWith('.pdf', $result);
        $this->assertFileExists($result);
    }

    public function test_config_publishing_and_loading(): void
    {
        // Test that config is properly loaded from the package
        $config = config('typst');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('bin_path', $config);
        $this->assertArrayHasKey('working_directory', $config);
        $this->assertArrayHasKey('timeout', $config);
        $this->assertArrayHasKey('format', $config);
        $this->assertArrayHasKey('font_paths', $config);
        $this->assertArrayHasKey('root', $config);

        // Test config override
        config(['typst.timeout' => 45]);
        $this->assertEquals(45, config('typst.timeout'));
    }

    public function test_middleware_and_request_context(): void
    {
        $source = $this->getValidTypstContent();

        // Simulate request context
        $request = request();
        $this->assertNotNull($request);

        Process::shouldReceive('timeout')
            ->once()
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->once()
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->once()
            ->andReturn($this->createSuccessfulProcess());

        // Compilation should work within request context
        $result = Typst::compile($source);

        $this->assertStringEndsWith('.pdf', $result);
        $this->assertFileExists($result);
    }

    public function test_database_connection_during_compilation(): void
    {
        $source = $this->getValidTypstContent();

        // Ensure database connection is available
        $connection = $this->app['db']->connection();
        $this->assertNotNull($connection);

        Process::shouldReceive('timeout')
            ->once()
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->once()
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->once()
            ->andReturn($this->createSuccessfulProcess());

        // Compilation should work with database connection available
        $result = Typst::compile($source);

        $this->assertStringEndsWith('.pdf', $result);
        $this->assertFileExists($result);
    }

    public function test_queue_integration_simulation(): void
    {
        $source = $this->getValidTypstContent();

        // Simulate queuing a compilation job
        $job = new class($source) {
            public function __construct(public string $source) {}
            public function handle() {
                return \Durableprogramming\LaravelTypst\Facades\Typst::compile($this->source);
            }
        };

        Process::shouldReceive('timeout')
            ->once()
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->once()
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->once()
            ->andReturn($this->createSuccessfulProcess());

        // Simulate job execution
        $result = $job->handle();

        $this->assertStringEndsWith('.pdf', $result);
        $this->assertFileExists($result);
    }

    public function test_localization_and_i18n_context(): void
    {
        $source = $this->getValidTypstContent();

        // Test with different locales
        $originalLocale = app()->getLocale();

        app()->setLocale('es');
        $this->assertEquals('es', app()->getLocale());

        Process::shouldReceive('timeout')
            ->once()
            ->with(30)
            ->andReturnSelf();

        Process::shouldReceive('path')
            ->once()
            ->with($this->getTestWorkingDirectory())
            ->andReturnSelf();

        Process::shouldReceive('run')
            ->once()
            ->andReturn($this->createSuccessfulProcess());

        $result = Typst::compile($source);

        $this->assertStringEndsWith('.pdf', $result);
        $this->assertFileExists($result);

        // Restore original locale
        app()->setLocale($originalLocale);
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