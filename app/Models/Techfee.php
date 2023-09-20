<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class Techfee extends Model
{
    protected $connection = 'mongodb';
    protected $guarded    = ['_id'];
}
