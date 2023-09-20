<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class RoleController extends Controller
{
  public function index()
  {

    return Role::with('reportGroups')->get(); //->attach(Role::first());
  }

  public function view(Request $request)
  {
    // auth()->user()->roles;
    // return [ 'user' => auth()->user() ];
  }

  public function store(Request $request)
  {
    if ($request->has('role')) {
      $roleData = $request->role;
      $role = new Role();
      if (Role::where('name', $roleData['name'])->get()->count() > 0) {
        return response()
          ->json(['message' => 'Role Name Already Exists'], 500);
      } else {
        foreach ($roleData as $roleAttr => $roleVal) {
          if ($roleAttr == 'permission') {
            foreach ($roleVal as $moduleName => $modulePermi) {
              if (isset($modulePermi['permission'])) {
                $isAuthorised = false;
                foreach ($modulePermi['permission'] as $key1 => $value1) {
                  if ($value1 == true) {
                    $isAuthorised = true;
                    break;
                  }
                }
                $roleData[$roleAttr][$moduleName]['authorised'] = $isAuthorised;
              }
            }
            $role[$roleAttr] = $roleData[$roleAttr];
          } else {
            $role[$roleAttr] = $roleData[$roleAttr];
          }
        }
        if ($role->save()) {
          return response()->json(['message' => 'success', 'role' => $role], 200);
        } else {
          return response()
            ->json(['message' => 'Couldn\'t Save the Data'], 500);
        }

      }
    } else
      return response()->json(['message' => 'Bad Request'], 401);
  }

  public function update(Request $request, $role)
  {
    if ($request->has('role')) {
      $role = Role::find($role);
      $roleData = $request->role;
      if (Role::where('_id', '!=', $role->_id)->where('name', $roleData['name'])->get()->count() > 0) {
        return response()
          ->json(['message' => 'Role Name Already Exists'], 500);
      } else {
        foreach ($roleData as $roleAttr => $roleVal) {
          if ($roleAttr == 'permission') {
            foreach ($roleVal as $moduleName => $modulePermi) {
              if (isset($modulePermi['permission'])) {
                $isAuthorised = false;
                foreach ($modulePermi['permission'] as $key1 => $value1) {
                  if ($value1 == true) {
                    $isAuthorised = true;
                    break;
                  }
                }
                $roleData[$roleAttr][$moduleName]['authorised'] = $isAuthorised;
              }
            }
            $role[$roleAttr] = $roleData[$roleAttr];
          } else {
            $role[$roleAttr] = $roleData[$roleAttr];
          }
        }
        if ($role->update() == 1) {
          return response()->json(['message' => 'success', 'role' => $role], 200);
        } else {
          return response()
            ->json(['message' => 'Couldn\'t Save the Data'], 500);
        }
      }
    } else {
      return response()->json(['message' => 'Bad Request'], 401);

    }

  }

  public function destroy($role)
  {
    try {
      $role = Role::findOrFail($role);
    } catch (ModelNotFoundException $e) {
      return response()->json(['message' => 'role not found'], 404);
    }

    if (isset($role->name) && $role->name == 'SysAdmin') {
      return response()->json(['message' => 'Cannot Delete a Super Admin'], 401);
    } else {
      // $role->roles()->detach();
      $role->active = false;
      $role->save();
      $role->delete();
      return response()->json(['message' => 'success'], 200);
    }
  }


  public function destroyMultiple(Request $request)
  {
    try {
      $roles = Role::whereIn('_id', $request->ids)->where('name', '!=', 'SysAdmin')->get();
    } catch (ModelNotFoundException $e) {
      return response()->json(['message' => 'roles not found'], 404);
    }
    $ids = [];
    foreach ($roles as $role) {
      $ids[] = $role->_id;
    }

    Role::whereIn('_id', $ids)->update(['active' => false]);
    Role::whereIn('_id', $ids)->delete();
    return response()->json(['message' => 'success'], 200);
  }

  public function forceDestroy($role)
  {
    try {
      $role = User::withTrashed()->findOrFail($role);
    } catch (ModelNotFoundException $e) {
      return response()->json(['message' => 'role not found'], 404);
    }

    if (isset($role->name) && $role->name == 'SysAdmin') {
      return response()->json(['message' => 'Cannot Delete a Super Admin'], 401);
    } else {
      $role->roles()->detach();
      $role->active = false;
      $role->save();
      $role->forceDelete();
      return response()->json(['message' => 'success'], 200);
    }
  }

  public function forceDestroyMultiple(Request $request)
  {
    try {
      $roles = User::withTrashed()->whereIn('_id', $request->ids)->where('type', '!=', 'sysadmin')->get();
    } catch (ModelNotFoundException $e) {
      return response()->json(['message' => 'roles not found'], 404);
    }
    $ids = [];
    foreach ($roles as $role) {
      $ids[] = $role->_id;
    }

    User::whereIn('_id', $ids)->save(['active' => false]);
    User::whereIn('_id', $ids)->forceDelete();
    return response()->json(['message' => 'success'], 200);
  }

  public function restoreDelete($role)
  {
    try {
      $role = User::withTrashed()->findOrFail($role);
    } catch (ModelNotFoundException $e) {
      return response()->json(['message' => 'role not found'], 404);
    }

    if (isset($role->name) && $role->name == 'sysadmin') {
      return response()->json(['message' => 'SysAdmin Role cannot be Deleted'], 401);
    } else {
      $role->restore();
      return response()->json(['message' => 'success'], 200);
    }
  }

  public function restoreMultipleDelete($role)
  {
    try {
      $roles = User::withTrashed()->whereIn('_id', $request->ids)->where('name', '!=', 'SysAdmin')->get();
    } catch (ModelNotFoundException $e) {
      return response()->json(['message' => 'role not found'], 404);
    }

    if (isset($role->name) && $role->name == 'sysadmin') {
      return response()->json(['message' => 'SysAdmin Role cannot be Deleted'], 401);
    } else {
      $role->restore();
      return response()->json(['message' => 'success'], 200);
    }

    $ids = [];
    foreach ($roles as $user) {
      $ids[] = $user->_id;
    }

    User::whereIn('_id', $ids)->restore();
    return response()->json(['message' => 'success'], 200);
  }

  public function reportGroupsManager(Request $request, $role)
  {
    if (isset($role) && Role::where('_id', $role)->exists()) {
      $role = Role::find($role);
      $data = $request->data;
      $role['accessToGroups'] = $data['accessToGroups'];
      $role->update();
      $role->reportGroups()->sync($data['reportGroups']);
      return response()->json(['message' => 'success', 'role' => $role], 200);
    } else {
      return response()->json(['message' => 'role not found'], 201);
    }
  }
}