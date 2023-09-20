<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;
Use Illuminate\Notifications\Notifiable;


class ManualTrade extends Model
{
     use Notifiable;
     protected $connection = 'mongodb';
     protected $collection = 'userDatas';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    // protected $fillable = [
    //     'name', 'active','piority','caneditadj', 'dateinput', 'showaccid', 'canmodifyarp', 'showiacc', 'canresetdata', 'canfetchdata', 'showgroup', 'canhardreset', 'canbeaproxy', 'enableproxytrade', 'caninsertprevdayopen', 'caneditud', 'helpdeskenabled', 'showserverpass', 'canmanagerisk'
    // ];

    protected $fillable = [
        'user_id',
        'accountid',
        'symbol',
        'date',
        'qty',
        'grossPnl',
        'status',
        'verifiedBy',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
