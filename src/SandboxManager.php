<?php

namespace Cyclechain\Sandboxer;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Cyclechain\Sandboxer\Models\SandboxSession;
use Cyclechain\Sandboxer\Storage\StorageManager;

class SandboxManager
{
    protected ?string $currentSandboxId = null;
    protected StorageManager $storage;
    
    public function __construct(StorageManager $storage)
    {
        $this->storage = $storage;
    }
    
    public function initialize(Request $request): void
    {
        if (!config('sandboxer.enabled')) {
            return;
        }
        
        // Check if sandbox should be activated via query parameter
        if (!$this->shouldActivateSandbox($request)) {
            return;
        }
        
        $token = $request->cookie('sandbox_session');
        
        if (!$token) {
            $token = $this->createNewSandbox($request);
        }
        
        $session = SandboxSession::where('session_token', $token)
            ->where('expires_at', '>', now())
            ->first();
        
        if (!$session) {
            $token = $this->createNewSandbox($request);
            $session = SandboxSession::where('session_token', $token)->first();
        }
        
        $this->currentSandboxId = $session->id;
        
        // Initial state'i yükle
        if ($session->initial_state) {
            $this->loadInitialState($session->initial_state);
        }
    }
    
    protected function shouldActivateSandbox(Request $request): bool
    {
        // Check query parameters
        $params = config('sandboxer.auto_detection.parameters', []);
        foreach ($params as $key => $value) {
            if ($request->query($key) == $value) {
                return true;
            }
        }
        
        // Check paths
        $paths = explode(',', config('sandboxer.auto_detection.paths', ''));
        foreach ($paths as $path) {
            $path = trim($path);
            if ($path && $request->is($path . '*')) {
                return true;
            }
        }
        
        // If cookie exists, activate
        if ($request->cookie('sandbox_session')) {
            return true;
        }
        
        return false;
    }
    
    public function createNewSandbox(Request $request): string
    {
        $token = Str::random(64);
        
        $session = SandboxSession::create([
            'id' => Str::uuid()->toString(),
            'session_token' => $token,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'expires_at' => now()->addSeconds((int) config('sandboxer.ttl', 3600)),
            'initial_state' => $this->captureInitialState()
        ]);
        
        // Demo user için auth token oluştur
        $this->createSandboxAuth($session->id);
        
        $this->currentSandboxId = $session->id;
        
        return $token;
    }
    
    protected function captureInitialState(): array
    {
        $state = [];
        
        foreach (config('sandboxer.snapshot_tables', ['users']) as $table) {
            $state[$table] = DB::table($table)
                ->whereIn('id', config('sandboxer.demo_record_ids', [1]))
                ->get()
                ->toArray();
        }
        
        return $state;
    }
    
    protected function loadInitialState(array $state): void
    {
        foreach ($state as $table => $records) {
            foreach ($records as $record) {
                $recordArray = (array) $record;
                $this->storage->store([
                    'sandbox_id' => $this->currentSandboxId,
                    'table_name' => $table,
                    'record_id' => $recordArray['id'],
                    'operation' => 'SNAPSHOT',
                    'data' => $recordArray,
                    'sequence' => 0
                ]);
            }
        }
    }
    
    protected function createSandboxAuth(string $sandboxId): void
    {
        $demoUser = DB::table('users')
            ->where('email', config('sandboxer.demo_credentials.email'))
            ->first();
        
        if ($demoUser) {
            $demoUserArray = (array) $demoUser;
            $this->storage->store([
                'sandbox_id' => $sandboxId,
                'table_name' => 'users',
                'record_id' => $demoUserArray['id'],
                'operation' => 'AUTH',
                'data' => $demoUserArray
            ]);
        }
    }
    
    public static function isActive(): bool
    {
        return app(self::class)->currentSandboxId !== null;
    }
    
    public static function currentId(): ?string
    {
        return app(self::class)->currentSandboxId;
    }
    
    public function getCurrentId(): ?string
    {
        return $this->currentSandboxId;
    }
    
    public function destroy(): void
    {
        if ($this->currentSandboxId) {
            // Storage'ı temizle
            DB::table('sandbox_storage')
                ->where('sandbox_id', $this->currentSandboxId)
                ->delete();
            
            // Session'ı sil
            SandboxSession::destroy($this->currentSandboxId);
            
            $this->currentSandboxId = null;
        }
    }
}
