<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class DetailedBase extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'detailedBases';
    public $timestamps = true;
    // function getDateAttribute($value) {
    //     return \Carbon\Carbon::parse($value)->toDateString();
    // }


    // function setDateAttribute($value) {
    //     $this->attributes['date'] = \Carbon\Carbon::parse($value)->toDateString();
    //     // $this->attributes['startdate1'] = $value;
    // }
    
}
