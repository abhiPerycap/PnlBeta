<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Acmap;
use App\Models\Role;
use App\Models\Settings;
use App\Models\TradeAccount;
use App\Models\Dreport;
use App\Models\UserData;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UserController extends Controller
{
  public function index(Request $request)
  {
    // $da = 'role_ids.02';
    // return User::first()->{$da};//->attach(Role::first());
    // return User::all();
    // return $request->all();
    $users = ($request->has('withTrashed') && $request['withTrashed'] == '1') ? User::withTrashed()->
      // with(['roles' => function ($query) {
      // 			$query->select('user_ids', 'name');
      // 		}])->
      get() : User::
        // with(['roles' => function ($query) {
        // 	$query->select('user_ids', 'name');
        // }])->
        get();
    // foreach ($users as $key => $user) {
    //   $roleNames = '';
    //   foreach ($user->roles as $role) {
    //     if($roleNames=='')
    //       $roleNames = $role['name'];
    //     else
    //       $roleNames = $roleNames.', '.$role['name'];
    //   }
    //   $users[$key]['roleNames'] = $roleNames;
    // }
    return $users;
  }

  public function traderAccounts()
  {
    $users = TradeAccount::all('accountid');
    foreach ($users as $user) {
      $data[] = array(
        'id' => $user->accountid,
        'text' => $user->accountid
      );
    }
    return response()->json($data);
  }

  public function view(Request $request)
  {
    return ['message' => 'success', 'user' => auth()->user(), 'permission' => auth()->user()->getPermission(), 'mapData' => auth()->user()->getMapData()];
  }


  public function update(Request $request, $user)
  {
    // return $request->all();
    $user = User::withTrashed()->find($user);
    if ($request->has('user')) {
      $userData = $request->user;
      foreach ($userData as $key => $value) {
        if ($key == 'password') {
          $user['password_reset_at'] = Carbon::now();
          $user['password'] = Hash::make($value);
        } else if ($key == 'memberId') {
          $user[$key] = trim(strtoupper($value));
        } else
          $user[$key] = $value;
      }
      // $users = User::all();
      // foreach ($users as $user) {
      //   $user->memberId = trim(strtoupper($user->memberId));
      //   $user->update();
      // }
      if ($user->update() == 1) {
        return response()->json(['message' => 'success', 'user' => $user], 200);
      } else {
        return response()
          ->json(['message' => 'Couldn\'t Save the Data'], 500);
      }
    } else {
      return response()->json(['message' => 'Bad Request'], 401);

    }

  }

  public function store(Request $request)
  {
    if ($request->Has('user')) {
      $userData = $request->user;
      $user = new User();
      if (
        User::withTrashed()->where(function ($query) use ($userData) {
          $query->where('memberId', '=', $userData['memberId'])
            ->orWhere('email', '=', $userData['email']);
        })->get()->count() == 0
      ) {
        foreach ($userData as $key => $value) {
          if ($key == 'password') {
            $user['password_reset_at'] = null;
            $user['password'] = Hash::make($value);
          } else if ($key == 'memberId') {
            $user[$key] = trim(strtoupper($value));
          } else
            $user[$key] = $value;
        }
        if ($user->save()) {
          $settings = Settings::getSettingsData();
          if ($settings != null) {
            $defaultRoleForNewUser = $settings['userDefaultRole'];
            if ($defaultRoleForNewUser != null)
              $defaultRoleForNewUser->users()->attach($user);
          }
          return response()->json(['message' => 'success', 'user' => $user], 200);
        } else {
          return response()
            ->json(['message' => 'Couldn\'t Save the Data'], 500);
        }
      } else {
        return response()->json(['message' => 'MemberId/Email already exists'], 201);
      }
    } else {
      return response()->json(['message' => 'Bad Request'], 401);
    }
  }

  // public function view(Request $request)
  // {
  //   if($user = User::where('_id', $request->id)->first()){
  //     return response()
  //         ->json(['message' => 'success', 'user' => $user, 'mapData' => $user->getMapData() ]);
  //   }else{
  //     return response()->json(['message' => 'Bad Request'], 401)
  //   }

  // }

  public function destroy($user)
  {
    try {
      $user = User::findOrFail($user);
    } catch (ModelNotFoundException $e) {
      return response()->json(['message' => 'user not found'], 404);
    }

    if (isset($user->type) && $user->type == 'sysadmin') {
      return response()->json(['message' => 'Cannot Delete a Super Admin'], 401);
    } else {
      // $user->roles()->detach();
      $user->active = false;
      $user->save();
      $user->delete();
      return response()->json(['message' => 'success'], 200);
    }
  }


  public function destroyMultiple(Request $request)
  {
    try {
      $users = User::whereIn('_id', $request->ids)->where('type', '!=', 'sysadmin')->get();
    } catch (ModelNotFoundException $e) {
      return response()->json(['message' => 'users not found'], 404);
    }
    $ids = [];
    foreach ($users as $user) {
      $ids[] = $user->_id;
    }

    User::whereIn('_id', $ids)->update(['active' => false]);
    User::whereIn('_id', $ids)->delete();
    return response()->json(['message' => 'success'], 200);
  }

  public function forceDestroy($user)
  {
    try {
      $user = User::withTrashed()->findOrFail($user);
    } catch (ModelNotFoundException $e) {
      return response()->json(['message' => 'user not found'], 404);
    }

    if (isset($user->type) && $user->type == 'sysadmin') {
      return response()->json(['message' => 'Cannot Delete a Super Admin'], 401);
    } else {
      $user->roles()->detach();
      $user->active = false;
      $user->save();
      $user->forceDelete();
      return response()->json(['message' => 'success'], 200);
    }
  }

  public function forceDestroyMultiple(Request $request)
  {
    try {
      $users = User::withTrashed()->whereIn('_id', $request->ids)->where('type', '!=', 'sysadmin')->get();
    } catch (ModelNotFoundException $e) {
      return response()->json(['message' => 'users not found'], 404);
    }
    $ids = [];
    foreach ($users as $user) {
      if (UserData::where('user_id', $user['_id'])->get()->count() == 0 && Dreport::where('userId', $user['_id'])->get()->count() == 0 && Acmap::where('user', $user['_id'])->get()->count() == 0) {
        $user->roles()->detach();
        $ids[] = $user->_id;
      }
    }

    // User::whereIn('_id', $ids)->save(['active'=>false]);
    User::whereIn('_id', $ids)->forceDelete();
    return response()->json(['message' => 'success'], 200);
  }

  public function restoreDelete($user)
  {
    try {
      $user = User::onlyTrashed()->findOrFail($user);
    } catch (ModelNotFoundException $e) {
      return response()->json(['message' => 'user not found'], 404);
    }

    if (isset($user->type) && $user->type == 'sysadmin') {
      return response()->json(['message' => 'You Cannot Perform this action on Super Admin'], 401);
    } else {
      $user->restore();
      return response()->json(['message' => 'success'], 200);
    }
  }

  public function restoreMultipleDelete(Request $request)
  {
    try {
      $users = User::onlyTrashed()->whereIn('_id', $request->ids)->where('type', '!=', 'sysadmin')->get();
    } catch (ModelNotFoundException $e) {
      return response()->json(['message' => 'user not found'], 404);
    }

    $ids = [];
    foreach ($users as $user) {
      // if(isset($user->type) && $user->type=='sysadmin'){
      //   return response()->json(['message' => 'You Cannot Perform this action on Super Admin'], 401);
      // }else{
      //   $user->restore();
      //   return response()->json(['message' => 'success'], 200);
      // }

      // $settings = Settings::getSettingsData();
      // if($settings!=null){
      //   $defaultRoleForNewUser = $settings['userDefaultRole'];
      //   if($defaultRoleForNewUser!=null)
      //     $defaultRoleForNewUser->users()->attach($user);
      // }

      $ids[] = $user->_id;
    }

    User::whereIn('_id', $ids)->restore();
    return response()->json(['message' => 'success'], 200);
  }

  public function updateProfile(Request $request, $type)
  {
    if ($type == 'info') {
      $user = auth()->user();
      $user['email'] = $request['data']['email'];
      $user['mobile'] = $request['data']['mobile'];
      $user['address'] = $request['data']['address'];
      $user->update();
    }
    if ($type == 'password') {
      $user = auth()->user();
      $user['password_reset_at'] = Carbon::now();
      $user['password'] = Hash::make($request['password']);
      $user->update();
    }

    return response()->json(['message' => 'success'], 200);
  }

  public function editMultiUser(Request $request)
  {
    $data = $request->all();
    foreach ($data['data'] as $key => $value) {
      if ($value == null) {
        unset($data['data'][$key]);
      }
      if ($key == 'memberId') {
        $data['data'][$key] = trim(strtoupper($value));
      }
    }
    // return $data;
    $response = User::whereIn('_id', $data['ids'])->update($data['data']);
    return ($response) ? response()->json(['message' => 'success'], 200) : response()->json(['message' => 'Something went wrong'], 200);
  }

}