<?php

namespace Cyclechain\Sandboxer\Tests\Unit;

use Cyclechain\Sandboxer\Tests\TestCase;
use Cyclechain\Sandboxer\SandboxManager;
use Cyclechain\Sandboxer\Helpers\SandboxAuthHelper;
use Cyclechain\Sandboxer\Tests\Models\TestUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SandboxAuthHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        config()->set('auth.providers.users.model', TestUser::class);
        config()->set('sandboxer.demo_credentials', [
            'email' => 'admin@admin.com',
            'password' => 'admin',
        ]);
    }

    public function test_it_handles_sandbox_login_successfully()
    {
        TestUser::create([
            'name' => 'Demo Admin',
            'email' => 'admin@admin.com',
            'password' => bcrypt('admin'),
        ]);

        $request = Request::create('http://localhost/demo');
        app(SandboxManager::class)->initialize($request);

        $loginRequest = Request::create('/login', 'POST', [
            'email' => 'admin@admin.com',
            'password' => 'admin',
        ]);

        $response = SandboxAuthHelper::handleSandboxLogin($loginRequest, '/dashboard');

        $this->assertNotNull($response);
        $this->assertTrue(Auth::check());
        $this->assertEquals('admin@admin.com', Auth::user()->email);
    }
}
