<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class Oreport extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'oreports';

    
    
}
