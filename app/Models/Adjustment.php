<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class Adjustment extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'adjustments';
    protected $guarded    = ['_id'];
    
    
}
