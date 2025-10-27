<?php

namespace Cyclechain\Sandboxer;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Cyclechain\Sandboxer\Listeners\ModelEventInterceptor;

class SandboxerServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }

        // Auto-register middleware if enabled
        if (config('sandboxer.auto_register', true)) {
            $kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
            if (method_exists($kernel, 'pushMiddleware')) {
                $kernel->pushMiddleware(\Cyclechain\Sandboxer\Middleware\SandboxMiddleware::class);
            }
        }

        // Register model event listener
        Event::subscribe(ModelEventInterceptor::class);
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
            return $app->make(SandboxManager::class);
        });

        // Register StorageManager
        $this->app->singleton(\Cyclechain\Sandboxer\Storage\StorageManager::class, function ($app) {
            return new \Cyclechain\Sandboxer\Storage\StorageManager;
        });

        // Register SandboxManager
        $this->app->singleton(SandboxManager::class, function ($app) {
            return new SandboxManager($app->make(\Cyclechain\Sandboxer\Storage\StorageManager::class));
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
    }
}
