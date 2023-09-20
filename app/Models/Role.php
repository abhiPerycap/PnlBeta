<?php

namespace App\Models;

use Jenssegers\Mongodb\Eloquent\Model;
Use Illuminate\Notifications\Notifiable;


class Role extends Model
{
     use Notifiable;
     protected $connection = 'mongodb';
     protected $collection = 'roles';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    // protected $fillable = [
    //     'name', 'active','piority','caneditadj', 'dateinput', 'showaccid', 'canmodifyarp', 'showiacc', 'canresetdata', 'canfetchdata', 'showgroup', 'canhardreset', 'canbeaproxy', 'enableproxytrade', 'caninsertprevdayopen', 'caneditud', 'helpdeskenabled', 'showserverpass', 'canmanagerisk'
    // ];

    protected $fillable = [
        'name',
        'permission',
        'authorised',
    ];

    public function users(){
      return $this->belongsToMany(User::class,'role_users');
    }

    public function reportGroups(){
      return $this->belongsToMany(ReportGroup::class,'reportGroup_roles');
    }
    // public function modules(){
    //   return $this->belongsToMany(Module::class,'role_modules');
    // }
}
