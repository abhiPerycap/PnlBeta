<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class Whitelist extends Model
{
    protected $connection = 'mongodb';
}
