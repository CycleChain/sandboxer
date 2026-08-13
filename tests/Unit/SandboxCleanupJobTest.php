<?php

namespace Cyclechain\Sandboxer\Tests\Unit;

use Cyclechain\Sandboxer\Tests\TestCase;
use Cyclechain\Sandboxer\Models\SandboxSession;
use Cyclechain\Sandboxer\Jobs\SandboxCleanupJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SandboxCleanupJobTest extends TestCase
{
    public function test_it_cleans_up_expired_sandbox_sessions()
    {
        $expiredId = Str::uuid()->toString();
        $activeId = Str::uuid()->toString();

        SandboxSession::create([
            'id' => $expiredId,
            'session_token' => 'expired_token',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'expires_at' => now()->subHour(),
        ]);

        SandboxSession::create([
            'id' => $activeId,
            'session_token' => 'active_token',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'expires_at' => now()->addHour(),
        ]);

        DB::table('sandbox_storage')->insert([
            'id' => Str::uuid()->toString(),
            'sandbox_id' => $expiredId,
            'table_name' => 'posts',
            'record_id' => '1',
            'operation' => 'INSERT',
            'data' => json_encode(['title' => 'Expired']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $job = new SandboxCleanupJob();
        $job->handle();

        $this->assertDatabaseMissing('sandbox_sessions', ['id' => $expiredId]);
        $this->assertDatabaseMissing('sandbox_storage', ['sandbox_id' => $expiredId]);

        $this->assertDatabaseHas('sandbox_sessions', ['id' => $activeId]);
    }
}
