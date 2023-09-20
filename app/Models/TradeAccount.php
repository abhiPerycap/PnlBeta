<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;
Use Illuminate\Notifications\Notifiable;
use Carbon\Carbon;
use App\Models\DefaultCharges;

class TradeAccount extends Model
{
     use Notifiable;
     protected $connection = 'mongodb';
     protected $collection = 'tradeAccounts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    // protected $fillable = [
    //     'name', 'active','piority','caneditadj', 'dateinput', 'showaccid', 'canmodifyarp', 'showiacc', 'canresetdata', 'canfetchdata', 'showgroup', 'canhardreset', 'canbeaproxy', 'enableproxytrade', 'caninsertprevdayopen', 'caneditud', 'helpdeskenabled', 'showserverpass', 'canmanagerisk'
    // ];

    protected $fillable = [
        // 'memberId',
        // 'accountid',
        // 'symbol',
        // 'date',
        // 'qty',
        // 'grossPnl',
        // 'status',
        // 'verifiedBy',
        'propReportId'
    ];

    public function getPropReportIdAttribute($value)
    {
        return ($this->isDuplicateAccount==true?
            $this->getParentAccount()['propReportId']:
            $value);
    }

    public function broker()
    {
        return $this->belongsTo(Broker::class);
    }

    public function reportGroups()
    {
        return $this->belongsToMany(ReportGroup::class, 'reportGroup_tradeAccounts');
    }

    public function dailyInterests()
    {
        return $this->hasMany(DailyInterest::class);
    }

    public function preferences()
    {
        return $this->hasMany(Preference::class);
    }

    public function getDailyInterestByDate($date = null)
    {
        if($date==null)
            $date = Carbon::now()->toDateString();
        else
            $date = Carbon::parse($date)->toDateString();

        if($this->dailyInterests()->where('effectiveFrom', '<=', $date)->count()>0)
            return $this->dailyInterests()->where('effectiveFrom', '<=', $date)->orderBy('effectiveFrom', 'desc')->first()['value'];
        else
            return (DefaultCharges::all()->isNotEmpty()?DefaultCharges::all()->first()->toArray()['dailyInterest']:1);
    }

    public function getPreferenceByDate($date = null)
    {
        if($date==null)
            $date = Carbon::now()->toDateString();
        else
            $date = Carbon::parse($date)->toDateString();

        if($this->preferences()->count()>0){

            $temp = $this->preferences()->where('effectiveFrom', '<=', $date)->orderBy('effectiveFrom', 'desc')->first();
            if(isset($temp))
                return $temp->preferenceCols;
            else
                return [];
        }
        else
            return (DefaultCharges::all()->isNotEmpty()?DefaultCharges::all()->first()->toArray()['preferenceCols']:[]);
    }

    public function getApiDetails()
    {
        if($this->isDuplicateAccount){
            $parentAcc = TradeAccount::find($this->parentAccount['_id']);
            return $parentAcc->getApiDetails();
        }else{
            if(isset($this->apiDetails))
                if(isset($this->propReportId))
                    return $this->apiDetails;
                else
                    return false;
            else{
                if(isset($this->propReportId))
                    return $this->broker->apiDetails;
                else
                    return false;
            }

        }
    }

    public function getParentAccount()
    {
        if($this->isDuplicateAccount){
            return TradeAccount::with('broker')->find($this->parentAccount['_id']);
        }return null;
    }

    // public function getAllMappedUsers($date=null)
    // {
    //     if($date==null)
    //         $date = Carbon::now()->toDateString();
    //     else
    //         $date = Carbon::parse($date)->toDateString();
    //     $account = $this->accountid;

    //     $master = Acmap::where('startdate', '<=', $date)->where('role', 'master')->where('mappedTo', $account)->orderBy('startdate', 'desc')->first();
    //     $subs = Acmap::where('startdate', '<=', $date)->where('role', 'sub')->where('mappedTo', $account)->orderBy('startdate', 'desc')->first();



    // }
}
