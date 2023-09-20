<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;
Use Illuminate\Notifications\Notifiable;

use App\Models\Acmap;
use App\Models\DetailedBase;
use App\Models\Adjustment;
use App\Models\Open;
use App\Models\PreviousDayOpen;


class Broker extends Model
{
     use Notifiable;
     protected $connection = 'mongodb';
     protected $collection = 'brokers';

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

    public function accounts()
    {
        return $this->hasMany(TradeAccount::class);
        
        // return $this->hasMany(TradeAccount::class);
    }

    // public static function boot() {
    //     parent::boot();

    //     static::deleting(function() { // before delete() method call this
    //          $this->accounts()->destroy();
    //          // do the rest of the cleanup...
    //     });
    // }
}
