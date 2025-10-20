<?php

namespace Cyclechain\Sandboxer;

use Illuminate\Support\ServiceProvider;

class SandboxerServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot(): void
    {
        // $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'cyclechain');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'cyclechain');
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadRoutesFrom(__DIR__.'/routes.php');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sandboxer.php', 'sandboxer');

        // Register the service the package provides.
        $this->app->singleton('sandboxer', function ($app) {
            return new Sandboxer;
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['sandboxer'];
    }

    /**
     * Console-specific booting.
     *
     * @return void
     */
    protected function bootForConsole(): void
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__.'/../config/sandboxer.php' => config_path('sandboxer.php'),
        ], 'sandboxer.config');

        // Publishing the views.
        /*$this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/cyclechain'),
        ], 'sandboxer.views');*/

        // Publishing assets.
        /*$this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/cyclechain'),
        ], 'sandboxer.assets');*/

        // Publishing the translation files.
        /*$this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/cyclechain'),
        ], 'sandboxer.lang');*/

        // Registering package commands.
        // $this->commands([]);
    }
}
