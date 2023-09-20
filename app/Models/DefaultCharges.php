<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class DefaultCharges extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'defaultCharges';
    protected $guarded    = ['_id'];
}
