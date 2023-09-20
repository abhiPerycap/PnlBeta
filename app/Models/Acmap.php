<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;
Use Illuminate\Notifications\Notifiable;
use Session;

class Acmap extends Model
{
     use Notifiable;
     protected $connection = 'mongodb';
     protected $collection = 'acmaps';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $fillable = [
        'startdate', 'mappedTo', 'user', 'role', 'type', 'executedBy', 'created_at', 'updated_at'
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

    function getStartdateAttribute($value) {
        return \Carbon\Carbon::parse($value)->toDateString();
    }


    function setStartdateAttribute($value) {
            $this->attributes['startdate'] = \Carbon\Carbon::parse($value)->toDateString();
            // $this->attributes['startdate1'] = $value;
    }

    public function executor(){
        return $this->belongsTo(User::class, 'executedBy'); 
    }
    public function effectedUser()
    {
        return $this->belongsTo(User::class, 'user');
    }

    /**
     * Define model event callbacks.
     *
     * @return void
     */
    public static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if (method_exists($model, 'beforeSave')) $model->beforeSave();
        });
        static::updating(function ($model) {
            if (method_exists($model, 'beforeSave')) $model->beforeSave();
        });
    }

    /**
     * Before save event listener.
     *
     * @return void
     */
    public function beforeSave()
    {
        $this->executedBy = Session::get('primaryUser')->_id;
    }
}