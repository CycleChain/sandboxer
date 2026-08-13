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

        // Register sandboxApply macro on Eloquent Builder
        \Illuminate\Database\Eloquent\Builder::macro('sandboxApply', function () {
            /** @var \Illuminate\Database\Eloquent\Builder $this */
            $builder = $this;
            if (!\Cyclechain\Sandboxer\SandboxManager::isActive()) {
                return $builder;
            }
            $sandboxId = \Cyclechain\Sandboxer\SandboxManager::currentId();
            $model = $builder->getModel();
            $excludedTables = array_merge(
                (array) config('sandboxer.excluded_tables', ['users']),
                ['sandbox_sessions', 'sandbox_storage']
            );
            if (!$sandboxId || in_array($model->getTable(), $excludedTables)) {
                return $builder;
            }

            $storage = app(\Cyclechain\Sandboxer\Storage\StorageManager::class);
            $tableName = $model->getTable();
            $modelClass = get_class($model);

            return $builder->afterQuery(function ($results) use ($storage, $sandboxId, $tableName, $modelClass) {
                if ($results === null) {
                    return $results;
                }
                if ($results instanceof \Illuminate\Support\Collection) {
                    return $storage->applySandboxState($sandboxId, $results, $tableName, $modelClass);
                }
                if (is_array($results)) {
                    $collection = collect($results);
                    $applied = $storage->applySandboxState($sandboxId, $collection, $tableName, $modelClass);
                    return $applied->all();
                }
                return $results;
            });
        });

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
