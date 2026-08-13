<?php

namespace Cyclechain\Sandboxer\Tests\Models;

use Illuminate\Database\Eloquent\Model;

class TestPost extends Model
{
    protected $table = 'posts';
    protected $guarded = [];
}
