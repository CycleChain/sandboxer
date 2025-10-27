<?php

namespace Cyclechain\Sandboxer\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool isActive()
 * @method static string|null currentId()
 * 
 * @see \Cyclechain\Sandboxer\SandboxManager
 */
class Sandboxer extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'sandboxer';
    }
}
