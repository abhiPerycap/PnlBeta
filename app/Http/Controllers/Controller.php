<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use App\Models\Acmap;
use App\Models\User;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function checkPermission($module, $permission){
        return true;
      $permissions = auth()->user()->getPermission();
      if(array_key_exists($module, $permissions)){
        if($permission=='authorised')
          return $permissions[$module]['authorised'];
        if($permissions[$module]['authorised']){
          if(array_key_exists($permission, $permissions[$module]['permission'])){
            return $permissions[$module]['permission'][$permission];
          }else{
              return true;
          }
        }else{
          return $permissions[$module]['authorised'];
        }
      }else{
        return false;
      }
    }

    public function sendError($message, $errorCode)
    {
      return response()->json(['message' => $message, 'statusCode' => $errorCode]);
    }

    public function getAllTraders($accountid, $date = null)
    {
        if($date!=null){
            $val = Acmap::where('mappedTo', $accountid)->where('startdate', '<=', $date)->orderBy('startdate', 'desc')->pluck('user')->toArray();
        }
        else
            $val = Acmap::where('mappedTo', $accountid)->orderBy('startdate', 'desc')->pluck('user')->toArray();
        if($val!=null)
            return $val;
        return null;
    }

    public function getAccountMaster($accountid, $date=null)
    {
        $accountid = strtoupper($accountid);
        if($date!=null){
            $val = Acmap::where('role', 'master')->
                            where('mappedTo', $accountid)->
                            where('startdate', '<=', $date)->
                            orderBy('startdate', 'desc')->
                            first();
            if(isset($val)){
                $user = User::find($val->user);
                if(isset($user))
                    return $user;
                else
                    return null;
            }else
                return null;
        }else{
            $val = Acmap::where('role', 'master')->
                            where('mappedTo', $accountid)->
                            orderBy('startdate', 'desc')->
                            first();
            if(isset($val)){
                $user = User::find($val->user);
                if(isset($user))
                    return $user;
                else
                    return null;
            }else
                return null;
        }
    }
}
