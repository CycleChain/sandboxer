<?php

namespace Cyclechain\Sandboxer\Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Cyclechain\Sandboxer\SandboxerServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = require __DIR__ . '/../../../../bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        config()->set('sandboxer.enabled', true);
        config()->set('sandboxer.ttl', 3600);
        config()->set('sandboxer.auto_detection', [
            'domains' => 'demo.*.com,sandbox.*.com',
            'paths' => '/demo,/sandbox,/try',
            'parameters' => ['sandbox' => '1', 'demo' => 'true'],
        ]);

        // Register service provider
        $this->app->register(SandboxerServiceProvider::class);

        $this->artisan('migrate', ['--path' => 'packages/cyclechain/sandboxer/database/migrations']);
        $this->createTestTables();
    }

    protected function createTestTables(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 8, 2);
            $table->timestamps();
        });
    }
}
