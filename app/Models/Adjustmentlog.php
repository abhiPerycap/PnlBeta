<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class Adjustmentlog extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'adjustmentlogs';
    protected $guarded    = ['_id'];

    
    function getUpdatedAtAttribute($value) {
        return \Carbon\Carbon::parse($value)->diffForHumans();
    }
}

