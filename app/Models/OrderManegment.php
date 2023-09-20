<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class OrderManegment extends Model
{
    protected $connection = 'mongodb';
    protected $guarded    = ['_id'];
}
