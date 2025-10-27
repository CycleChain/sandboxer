<?php

namespace Cyclechain\Sandboxer\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Cyclechain\Sandboxer\SandboxManager;

class SandboxMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (!config('sandboxer.enabled')) {
            return $next($request);
        }
        
        // Sandbox'ı başlat
        $sandboxManager = app(SandboxManager::class);
        $sandboxManager->initialize($request);
        
        $response = $next($request);
        
        // Cookie'yi set et eğer sandbox aktifse
        if ($sandboxManager->getCurrentId() && !$request->cookie('sandbox_session')) {
            // Session'dan token'ı al
            $session = \Cyclechain\Sandboxer\Models\SandboxSession::find($sandboxManager->getCurrentId());
            if ($session) {
                $ttlMinutes = (int) config('sandboxer.ttl', 3600) / 60;
                return $response->cookie('sandbox_session', $session->session_token, $ttlMinutes);
            }
        }
        
        return $response;
    }
}
