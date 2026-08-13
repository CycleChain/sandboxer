<?php

namespace Cyclechain\Sandboxer\Tests\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class TestUser extends Authenticatable
{
    protected $table = 'users';
    protected $guarded = [];
}
