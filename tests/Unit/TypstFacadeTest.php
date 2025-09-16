<?php

namespace Durable\LaravelTypst\Tests\Unit;

use Durable\LaravelTypst\Facades\Typst as TypstFacade;
use Durable\LaravelTypst\Tests\TestCase;
use Durable\LaravelTypst\TypstService;
use Illuminate\Support\Facades\Facade;

class TypstFacadeTest extends TestCase
{
    public function test_facade_resolves_correct_accessor(): void
    {
        $reflection = new \ReflectionClass(TypstFacade::class);
        $method = $reflection->getMethod('getFacadeAccessor');
        $method->setAccessible(true);
        
        $this->assertEquals('typst', $method->invoke(new TypstFacade()));
    }

    public function test_facade_resolves_to_typst_service(): void
    {
        $resolved = TypstFacade::getFacadeRoot();
        
        $this->assertInstanceOf(TypstService::class, $resolved);
    }

    public function test_facade_is_singleton(): void
    {
        $service1 = TypstFacade::getFacadeRoot();
        $service2 = TypstFacade::getFacadeRoot();
        
        $this->assertSame($service1, $service2);
    }

    public function test_facade_methods_are_proxied_correctly(): void
    {
        $config = TypstFacade::getConfig();
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('bin_path', $config);
        $this->assertArrayHasKey('working_directory', $config);
        $this->assertArrayHasKey('timeout', $config);
        $this->assertArrayHasKey('format', $config);
    }

    public function test_facade_set_config_returns_service_instance(): void
    {
        $result = TypstFacade::setConfig(['timeout' => 90]);
        
        $this->assertInstanceOf(TypstService::class, $result);
        $this->assertEquals(90, TypstFacade::getConfig()['timeout']);
    }

    public function test_facade_inherits_from_laravel_facade(): void
    {
        $this->assertInstanceOf(Facade::class, new TypstFacade());
    }

    public function test_facade_alias_is_registered(): void
    {
        $this->assertTrue(class_exists('Typst'));
        $this->assertTrue(is_subclass_of('Typst', Facade::class));
    }

    public function test_facade_can_call_compile_method(): void
    {
        $this->expectException(\Durable\LaravelTypst\Exceptions\TypstCompilationException::class);
        
        TypstFacade::compile($this->getValidTypstContent());
    }

    public function test_facade_can_call_compile_to_string_method(): void
    {
        $this->expectException(\Durable\LaravelTypst\Exceptions\TypstCompilationException::class);
        
        TypstFacade::compileToString($this->getValidTypstContent());
    }

    public function test_facade_can_call_compile_file_method(): void
    {
        $inputFile = $this->getTestWorkingDirectory() . '/test.typ';
        file_put_contents($inputFile, $this->getValidTypstContent());

        $this->expectException(\Durable\LaravelTypst\Exceptions\TypstCompilationException::class);
        
        TypstFacade::compileFile($inputFile);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        parent::tearDown();
    }
}