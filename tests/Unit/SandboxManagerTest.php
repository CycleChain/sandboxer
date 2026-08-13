<?php

namespace Cyclechain\Sandboxer\Tests\Unit;

use Cyclechain\Sandboxer\Tests\TestCase;
use Cyclechain\Sandboxer\SandboxManager;
use Cyclechain\Sandboxer\Models\SandboxSession;
use Cyclechain\Sandboxer\Facades\Sandboxer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SandboxManagerTest extends TestCase
{
    protected SandboxManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = app(SandboxManager::class);
    }

    public function test_it_can_activate_via_query_parameter()
    {
        $request = Request::create('http://localhost/dashboard?sandbox=1');
        $this->manager->initialize($request);

        $this->assertTrue(SandboxManager::isActive());
        $this->assertNotNull($this->manager->getCurrentId());
    }

    public function test_it_can_activate_via_path()
    {
        $request = Request::create('http://localhost/demo/dashboard');
        $this->manager->initialize($request);

        $this->assertTrue(SandboxManager::isActive());
    }

    public function test_it_can_activate_via_domain_wildcard()
    {
        $request = Request::create('http://demo.mysite.com/dashboard');
        $this->manager->initialize($request);

        $this->assertTrue(SandboxManager::isActive());
    }

    public function test_it_creates_new_sandbox_session()
    {
        DB::table('users')->insert([
            'id' => 1,
            'name' => 'Demo Admin',
            'email' => 'admin@admin.com',
        ]);

        $request = Request::create('http://localhost/demo');
        $token = $this->manager->createNewSandbox($request);

        $this->assertNotEmpty($token);
        
        $session = SandboxSession::where('session_token', $token)->first();
        $this->assertNotNull($session);
        $this->assertEquals($this->manager->getCurrentId(), $session->id);
        $this->assertFalse($session->isExpired());
    }

    public function test_it_destroys_sandbox_session()
    {
        $request = Request::create('http://localhost/demo');
        $this->manager->initialize($request);

        $sandboxId = $this->manager->getCurrentId();
        $this->assertNotNull($sandboxId);

        $this->manager->destroy();

        $this->assertNull($this->manager->getCurrentId());
        $this->assertFalse(SandboxManager::isActive());
        $this->assertDatabaseMissing('sandbox_sessions', ['id' => $sandboxId]);
    }
}
