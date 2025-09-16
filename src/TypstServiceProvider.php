<?php

namespace Durable\LaravelTypst;

use Illuminate\Support\ServiceProvider;

class TypstServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/typst.php', 'typst'
        );

        $this->app->singleton('typst', function ($app) {
            return new TypstService($app['config']['typst']);
        });

        $this->app->alias('typst', TypstService::class);
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/typst.php' => config_path('typst.php'),
        ], 'typst-config');
    }

    public function provides()
    {
        return ['typst', TypstService::class];
    }
}