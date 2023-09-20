<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PermissionController extends Controller
{
    public function index($mode)
    {
        $permissions = [];
        if($mode=='role'){
            $permissions = Role::with('users')->get();
            return $permissions;
        }else{
            $users = User::with('roles')->get();
            foreach ($users as $user) {
                $permissions[] = [
                    'user' => $user,
                    'permission' => $user->getPermission(),
                    'userPermission' => (isset($user->userPermission)?$user->userPermission:'Not Assigned')
                ];
            }
            return $permissions;
        }
    }

    public function update(Request $request, $id)
    {
        if($request->Has('users')){
            $role = Role::find($id);
            $role->users()->sync($request->users);
            $role->users;
            return response()->json(['message' => 'success', 'role' => $role], 200);

        }else if($request->Has('updateFor')){
            $updateFor = $request->updateFor;
            $user = User::find($id);
            if($updateFor=='customRole'){
                $user['userPermission'] = $request->data;
                if($user->update()==1){
                    $user->roles;
                    return response()->json([
                        'message' => 'success', 
                        'user' => [
                            'user' => $user,
                            'permission' => $user->getPermission(),
                            'userPermission' => (isset($user->userPermission)?$user->userPermission:'Not Assigned')
                        ]
                    ], 200);
                }else{
                    return response()
                    ->json(['message' => 'Couldn\'t Save the Data'], 500);
                }
            }if($updateFor=='role'){
                $role = $user->roles()->first();
                $role['permission'] = $request->data;
                if($role->update()==1){
                    $role->users;
                    return response()->json([
                        'message' => 'success', 
                        'role' => $role
                    ], 200);
                }else{
                    return response()
                    ->json(['message' => 'Couldn\'t Save the Data'], 500);
                }
            }
        }
    }

    public function removeCustomRole(Request $request)
    {
        if($request->Has('ids')){
            $users = User::whereIn('_id', $request->ids)->get();
            foreach ($users as $key => $value) {
                $value->unset('userPermission');
                $value->update();
            }
            return response()->json([
                'message' => 'success'
            ], 200);
        }else
            return response()->json(['message' => 'Couldn\'t Save the Data'], 500);
    }

}