<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;
Use Illuminate\Notifications\Notifiable;


class Symbol extends Model
{
     use Notifiable;
     protected $connection = 'mongodb';
     protected $collection = 'symbols';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    // protected $fillable = [
    //     'name', 'active','piority','caneditadj', 'dateinput', 'showaccid', 'canmodifyarp', 'showiacc', 'canresetdata', 'canfetchdata', 'showgroup', 'canhardreset', 'canbeaproxy', 'enableproxytrade', 'caninsertprevdayopen', 'caneditud', 'helpdeskenabled', 'showserverpass', 'canmanagerisk'
    // ];

    // protected $fillable = [
    //     'memberId',
    //     'accountid',
    //     'symbol',
    //     'date',
    //     'qty',
    //     'grossPnl',
    //     'status',
    //     'verifiedBy',
    // ];

    public function requestedBy()
    {
        return $this->belongsTo(User::class);
    }
    
    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verifiedBy');
    }

    // public static function boot() {
    //     parent::boot();

    //     static::deleting(function() { // before delete() method call this
    //          $this->accounts()->destroy();
    //          // do the rest of the cleanup...
    //     });
    // }
}
