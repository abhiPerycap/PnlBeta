<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;
Use Illuminate\Notifications\Notifiable;


class ReportGroup extends Model
{
     use Notifiable;
     protected $connection = 'mongodb';
     protected $collection = 'reportGroups';

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

    public function users()
    {
        return $this->belongsToMany(User::class, 'reportGroup_users');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'reportGroup_roles');
    }

    // public function reportGroups()
    // {
    //     return $this->belongsToMany(ReportGroup::class, 'reportGroup_reportGroups');
    // }

    public function tradeAccounts()
    {
        return $this->belongsToMany(TradeAccount::class, 'reportGroup_tradeAccounts');
    }

    public function getAllMembers($groups = null)
    {
        // return $this->tradeAccounts;
        $data = [
            'users'=> [],
            'accounts'=> [],
        ];
        if($groups==null){
            if(sizeof($this->users)>0)
                $data['users'] = array_merge($data['users'], $this->users()->pluck('memberId', '_id')->toArray());
            if(sizeof($this->tradeAccounts)>0)
                $data['accounts'] = array_merge($data['accounts'], $this->tradeAccounts()->pluck('accountid', '_id')->toArray());
            if(sizeof($this['reportGroups'])>0){
                $temp = $this->getAllMembers($this['reportGroups']);

                $data['users'] = array_unique(array_merge($data['users'], $temp['users']));
                $data['accounts'] = array_unique(array_merge($data['accounts'], $temp['accounts']));
            }
            return ($data);
        }else{
            foreach ($groups as $value) {
                $group = ReportGroup::find($value);
                if(sizeof($group->users)>0)
                    $data['users'] = array_merge($data['users'], $group->users()->pluck('memberId', '_id')->toArray());
                if(sizeof($group->tradeAccounts)>0)
                    $data['accounts'] = array_merge($data['accounts'], $group->tradeAccounts()->pluck('accountid', '_id')->toArray());
                // if(sizeof($group->reportGroups)>0){
                //     $temp = $group->getAllMembers($group->reportGroups);
                //     $data['users'] = array_unique(array_merge($data['users'], $temp['users']));
                //     $data['accounts'] = array_unique(array_merge($data['accounts'], $temp['accounts']));
                // }
            }
            return ($data);
        }
    }
}
