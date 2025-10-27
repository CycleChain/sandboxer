<?php

namespace Cyclechain\Sandboxer\Models;

use Illuminate\Database\Eloquent\Model;

class SandboxSession extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    
    protected $fillable = [
        'id',
        'session_token',
        'ip_address',
        'user_agent',
        'expires_at',
        'initial_state',
        'metadata',
    ];
    
    protected $casts = [
        'initial_state' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
    ];
    
    protected $table = 'sandbox_sessions';
    
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
    
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }
}
