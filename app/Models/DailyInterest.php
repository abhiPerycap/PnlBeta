<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;

class DailyInterest extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'dailyInterests';
    protected $guarded    = ['_id'];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tradeAccount()
    {
        return $this->belongsTo(TradeAccount::class);
    }
}
