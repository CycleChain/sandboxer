<?php

namespace Cyclechain\Sandboxer\Facades;

use Illuminate\Support\Facades\Facade;

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
