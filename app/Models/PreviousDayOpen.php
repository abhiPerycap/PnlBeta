<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;
Use Illuminate\Notifications\Notifiable;
use Session;

class PreviousDayOpen extends Model
{
     use Notifiable;
     protected $connection = 'mongodb';
     protected $collection = 'previousDayOpen';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $fillable = [
        // 'startdate', 'mappedTo', 'user', 'role', 'type', 'executedBy', 'created_at', 'updated_at'
    ];
    // protected $dates = ['startdate1'];

    // public function users(){
    //   return $this->belongsToMany(User::class,'role_users');
    // }
    // public function modules(){
    //   return $this->belongsToMany(Module::class,'role_modules');
    // }

    // public static function boot() {

    //     parent::boot();

    //     static::updating(function($model) {

    //         // do somthing here
    //         if(!is_string($model->startdate)){
    //             $model->startdate = $model->startdate->toDateString();
    //         }

    //     });

    //     static::saving(function($model) {

    //         // do somthing here
    //         if(!is_string($model->startdate)){
    //             $model->startdate = $model->startdate->toDateString();
    //         }

    //     });
    // }

    
}