<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;
Use Illuminate\Notifications\Notifiable;


class DemoUserData extends Model
{
     use Notifiable;
     protected $connection = 'mongodb';
     protected $collection = 'userDatas';
     
     // public $tableName = 'userDatas';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    // protected $fillable = [
    //     'name', 'active','piority','caneditadj', 'dateinput', 'showaccid', 'canmodifyarp', 'showiacc', 'canresetdata', 'canfetchdata', 'showgroup', 'canhardreset', 'canbeaproxy', 'enableproxytrade', 'caninsertprevdayopen', 'caneditud', 'helpdeskenabled', 'showserverpass', 'canmanagerisk'
    // ];

    protected $fillable = [
        'executionId',
        'user_id',
        'accountid',
        'symbol',
        'date',
        'qty',
        'quantity',
        'grossPnl',
        'status',
        'verifiedBy',
    ];

    // public function user()
    // {
    //     return $this->belongsTo(User::class);
    // }

    public static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if (method_exists($model, 'beforeSave')) $model->beforeSave();
        });
        // static::updating(function ($model) {
        //     if (method_exists($model, 'beforeSave')) $model->beforeSave();
        // });
    }

    public function beforeSave()
    {
        $this->executionId = 'TR'.time();
    }

    // function getDateAttribute($value) {
    //     return \Carbon\Carbon::parse($value)->toDateString();
    // }


    // function setDateAttribute($value) {
    //         $this->attributes['date'] = \Carbon\Carbon::parse($value)->toDateString();
    //         // $this->attributes['startdate1'] = $value;
    // }
}
