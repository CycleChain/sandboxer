<?php

namespace Cyclechain\Sandboxer\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Cyclechain\Sandboxer\Models\SandboxSession;

class SandboxCleanupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (!config('sandboxer.cleanup.enabled')) {
            return;
        }
        
        // Expired session'ları bul
        $expiredSessions = SandboxSession::where('expires_at', '<', now())
            ->pluck('id');
        
        foreach ($expiredSessions as $sandboxId) {
            DB::transaction(function () use ($sandboxId) {
                // Storage'ı temizle
                DB::table('sandbox_storage')
                    ->where('sandbox_id', $sandboxId)
                    ->delete();
                
                // Session'ı sil
                SandboxSession::destroy($sandboxId);
            });
        }
    }
}
