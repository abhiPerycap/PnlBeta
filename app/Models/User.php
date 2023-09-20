<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Jenssegers\Mongodb\Auth\User as Authenticatable;
// use Jenssegers\Mongodb\Eloquent\Model;
use Jenssegers\Mongodb\Eloquent\SoftDeletes;
use Carbon\Carbon;

use App\Models\DefaultCharges;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $connection = 'mongodb';
    protected $collection = 'users';
    protected $appends = array('roleNames');
    public function getRoleNamesAttribute()
    {
        $roleNames = '';
        foreach ($this->roleNames as $role) {
            if ($roleNames == '')
                $roleNames = $role['name'];
            else
                $roleNames = $roleNames . ', ' . $role['name'];
        }
        return $roleNames;
    }
    // protected $appends = array('roleNames');

    // public function getRoleNamesAttribute()
    // {
    //     $roleNames = '';
    //     foreach ($this->roles as $role) {
    //       if($roleNames=='')
    //         $roleNames = $role['name'];
    //       else
    //         $roleNames = $roleNames.', '.$role['name'];
    //     }
    //     return $roleNames;  
    // }
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    // protected $fillable = [
    //     'name',
    //     'email',
    //     'password',
    // ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $dates = ['created_at', 'updated_at', 'deleted_at', 'password_reset_at'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_users');
    }

    public function roleNames()
    {
        return $this->belongsToMany(Role::class, 'role_users')->select('name');
    }


    public function reportGroups()
    {
        return $this->belongsToMany(ReportGroup::class, 'reportGroup_users');
    }

    public function userDatas()
    {
        return $this->hasMany(UserData::class);
    }

    public function dailyInterests()
    {
        return $this->hasMany(DailyInterest::class);
    }

    public function preferences()
    {
        return $this->hasMany(Preference::class);
    }

    public function mappingData()
    {
        return $this->hasMany(Acmap::class, 'user');
    }

    public function executedMappingByMe()
    {
        return $this->hasMany(Acmap::class, 'executedBy');
    }

    public function requestedSymbols()
    {
        return $this->hasMany(Symbol::class);
    }

    public function symbolsVefrifiedByUser()
    {
        return $this->hasMany(Symbol::class, '_id', 'verifiedBy');
    }

    public function getMemberId()
    {
        return $this->hasOne(UserData::class, 'user_id', '_id');
    }

    public function getDailyInterestByDate($date = null)
    {
        if ($date == null)
            $date = Carbon::now()->toDateString();
        else
            $date = Carbon::parse($date)->toDateString();

        if ($this->dailyInterests()->where('effectiveFrom', '<=', $date)->count() > 0)
            return $this->dailyInterests()->where('effectiveFrom', '<=', $date)->orderBy('effectiveFrom', 'desc')->first()['value'];
        else
            return (DefaultCharges::all()->isNotEmpty() ? DefaultCharges::all()->first()->toArray()['dailyInterest'] : 1);
    }

    public function getPreferenceByDate($date = null)
    {
        if ($date == null)
            $date = Carbon::now()->toDateString();
        else
            $date = Carbon::parse($date)->toDateString();

        if ($this->preferences()->count() > 0) {

            $temp = $this->preferences()->where('effectiveFrom', '<=', $date)->orderBy('effectiveFrom', 'desc')->first();
            if (isset($temp))
                return $temp->preferenceCols;
            else
                return [];
        } else
            return (DefaultCharges::all()->isNotEmpty() ? DefaultCharges::all()->first()->toArray()['preferenceCols'] : []);
    }

    public function getPermission($flag = false)
    {
        if (isset($this->userPermission) && sizeof($this->userPermission) > 0) {
            return $this->userPermission;
        } else {
            $accessToGroups = false;
            $accessToCharts = false;
            $ipRestriction = false;
            $ipBranches = [];
            $symbolGroups = [];
            // $ipRestriction = false;
            $roles = $this->roles;

            if (sizeof($roles) > 0) {
                $accessToGroups = $this->roles()->first()->accessToGroups;
                $accessToCharts = $this->roles()->first()->accessToCharts;
                $ipRestriction = $this->roles()->first()->ipRestriction;
                $permission = $this->roles()->first()->permission;
                $roleNames = '';
                foreach ($roles as $role) {
                    $accessToGroups = $role->accessToGroups == true ? $role->accessToGroups : $accessToGroups;
                    $accessToCharts = $role->accessToCharts == true ? $role->accessToCharts : $accessToCharts;
                    $ipRestriction = $role->ipRestriction == true ? $role->ipRestriction : $ipRestriction;
                    $ipBranches = $role->ipRestriction == true ? array_unique(array_merge($ipBranches, $role->ipBranches)) : $ipBranches;
                    $symbolGroups = $role->accessToCharts == true ? array_unique(array_merge($symbolGroups, $role->symbolGroups)) : $symbolGroups;
                    $permission = $this->digForPermission($permission, $role->permission);
                    if ($roleNames == '') {
                        $roleNames = $role->name;
                    } else
                        $roleNames .= ', ' . $role->name;
                }
                if ($flag) {
                    return [
                        'accessToGroups' => $accessToGroups,
                        'accessToCharts' => $accessToCharts,
                        'ipRestriction' => $ipRestriction,
                        'ipBranches' => $ipBranches,
                        'symbolGroups' => $symbolGroups,
                        'permission' => $permission,
                        'roleNames' => $roleNames
                    ];

                } else {
                    return $permission;
                }

            } else
                return [];

        }
    }

    public function digForPermission($arg1, $arg2)
    {
        foreach ($arg2 as $key => $value) {
            if (array_key_exists($key, $arg1)) {
                if (gettype($value) == 'array') {
                    $arg1[$key] = $this->digForPermission($arg1[$key], $arg2[$key]);
                } else {
                    if ($value == true)
                        $arg1[$key] = $value;
                }
            } else {
                $arg1[$key] = $value;
            }
        }
        return $arg1;
    }

    public function getAccountId($date = null)
    {
        // $val = '';
        if ($date == null)
            $date = Carbon::today()->toDateString();


        $val = Acmap::where('user', $this->_id)->where('startdate', '<=', $date)->orderBy('startdate', 'desc')->first();
        // else{
        // $val = Acmap::where('user', $this->_id)->orderBy('startdate', 'desc')->first();
        // }
        if ($val != null)
            return $val->mappedTo;
        // if($this->adminshare!=null){
        //     return $this->adminshare->accountid;
        //     // $groupName = $this->admingroup->name;
        // }else if($this->share!=null){
        //     return $this->share->accountid;
        //     // $groupName = $this->maingroup->name;
        // }
        return null;
    }

    public function getAccountIds()
    {


        $val = Acmap::where('user', $this->_id)->get()->pluck('mappedTo')->toArray();
        return array_unique($val);

    }

    public function getMapData($date = null)
    {
        // $val = '';
        if ($date == null)
            $date = Carbon::today()->toDateString();


        $val = Acmap::where('user', $this->_id)->where('startdate', '<=', $date)->orderBy('startdate', 'desc')->first();
        // else{
        // $val = Acmap::where('user', $this->_id)->orderBy('startdate', 'desc')->first();
        // }
        if ($val != null) {
            if (Acmap::where('user', $this->_id)->where('startdate', $val->startdate)->where('role', 'disabled')->count() == 1)
                return Acmap::where('user', $this->_id)->where('startdate', $val->startdate)->where('role', 'disabled')->first();
            return $val;
        }
        // if($this->adminshare!=null){
        //     return $this->adminshare->accountid;
        //     // $groupName = $this->admingroup->name;
        // }else if($this->share!=null){
        //     return $this->share->accountid;
        //     // $groupName = $this->maingroup->name;
        // }
        return null;
    }

    public function getAccMasterAttribute()
    {
        return $this->getAccountMaster();
    }

    public function getAccountMaster($date = null)
    {
        $accountid = strtoupper($this->getAccountId($date));
        if ($date != null) {
            $val = Acmap::where('role', 'master')->
                where('mappedTo', $accountid)->
                where('startdate', '<=', $date)->
                orderBy('startdate', 'desc')->
                first();
            if (isset($val)) {
                $user = User::find($val->user);
                if (isset($user))
                    return $user;
                else
                    return null;
            } else
                return null;
        } else {
            $val = Acmap::where('role', 'master')->
                where('mappedTo', $accountid)->
                orderBy('startdate', 'desc')->
                first();
            if (isset($val)) {
                $user = User::find($val->user);
                if (isset($user))
                    return $user;
                else
                    return null;
            } else
                return null;
        }
    }

    public function array_unique_multiple($array)
    {
        $temp_array = array();
        foreach ($array as $key => $val) {
            foreach ($array as $key1 => $val1) {
                if ($key != $key1) {
                    $isSame = true;
                    foreach ($val as $key2 => $val3) {
                        if ($val1[$key2] != $val3) {
                            $isSame = false;
                            break;
                        }
                    }
                    if ($isSame) {
                        unset($array[$key1]);
                    }
                }
            }
            if (sizeof($array[$key]) > 0)
                $temp_array[] = $array[$key];
        }
        return $temp_array;
    }

    public function getReportGroupData()
    {
        // $groupIdList = [];
        // $groupList = [];
        // $accountList = [];
        // $userList = [];
        // $acList = [];
        // foreach ($this->roles() as $role) {
        //     if($role['accessToGroups']){
        //         if(!in_array($role['_id'], $groupIdList)){
        //             $groupIdList[] = $role['_id'];
        //             $groupList[] = [
        //                 'id' =>$role['_id'],
        //                 'text' =>$role['name']
        //             ];

        //             foreach ($role->reportGroups() as $group) {
        //                 $temp = $group->getAllMembers();
        //                 $userList[]
        //             }

        //         }
        //     }
        // }
    }

    public function tradeMode($date = null)
    {

        if ($date == null)
            $date = Carbon::today()->toDateString();

        $val = Acmap::where('user', $this->id)->
            where('startdate', '<=', $date)->
            orderBy('startdate', 'desc')->
            first();
        if (isset($val)) {
            if (
                Acmap::where('user', $this->id)->
                    where('startdate', $val->startdate)->
                    where('role', 'disabled')->
                    count() == 1
            ) {

                return 'disabled';
            } else
                return $val->role;
        } else
            return null;
        // }else{
        //     $val = Acmap::where('user', $this->id)->
        //                     orderBy('startdate', 'desc')->
        //                     first();
        //     if(isset($val))
        //         return $val->role;
        //     else
        //         return null;
        // }
    }
}