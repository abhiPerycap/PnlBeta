<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SymbolGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Auth;
use Validator;
use App\Models\User;
use App\Models\IpList;
use App\Models\Symbol;
use App\Models\Settings;
use App\Models\TradeAccount;
use App\Models\Role;
use Carbon\Carbon;

use App\Traits\AccountMappingTrait;

class ApiController extends Controller
{

    use AccountMappingTrait;

    // public function register(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'name' => 'required|string|max:255',
    //         'email' => 'required|string|email|max:255|unique:users',
    //         'password' => 'required|string|min:5'
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json($validator->errors());
    //     }

    //     $user = new User();
    //     $user->name = $request->name;
    //     $user->email = $request->email;
    //     $user->password = Hash::make($request->password);
    //     $user->save();

    //     $token = $user->createToken('auth_token')->plainTextToken;

    //     return response()
    //         ->json(['data' => $user, 'access_token' => $token, 'token_type' => 'Bearer',]);
    // }


    // public function loginForChart(Request $request)
    // {


    //     if (Auth::attempt($request->only('email', 'password'))) {
    //         $user = User::where('email', $request['email'])->firstOrFail();
    //         if ($user['authorised'] == true) {
    //             $user['status'] = 'Online';
    //             $user->save();
    //             // $token = $user->createToken('auth_token')->plainTextToken;
    //             // $timeZone = Settings::all();
    //             // $timeZone = $timeZone[0]['timeZone']['id'];
    //             // $needsPasswordReset = true;
    //             // if ($user['password_reset_at'] != null) {
    //             //     if ($user['password_reset_at']->diffInDays(Carbon::now()) <= $passwordResetDuration) {
    //             //         $needsPasswordReset = false;
    //             //     }
    //             // }
    //             $permission = $user->getPermission(true);
    //             if ($permission['accessToCharts']) {
    //                 $symbolIdlist = SymbolGroup::whereIn('_id', $permission['symbolGroups'])->get()->pluck('symbol_ids')->toArray();
    //                 $list = [];
    //                 foreach ($symbolIdlist as $key => $value) {
    //                     $list = array_unique(array_merge($list, $value));
    //                 }
    //                 $symbols = Symbol::whereIn('_id', $list)->get()->toArray();
    //                 $symbolList = [];
    //                 foreach ($symbols as $key => $value) {
    //                     if (isset($value['exchange'])) {
    //                         $symbolList[] = $value['exchange'] . ':' . $value['name'];
    //                     }
    //                     // else {
    //                     //     $symbolList[] = $value['name'];
    //                     // }
    //                     // $symbolList[] = $value['exchange'];
    //                 }

    //                 return response()->json([

    //                         "message" => "success",
    //                         // "status" => "success",
    //                         "symbolList" => $symbolList
    //                 ]);
    //             } else {
    //                 return response()->json([
    //                     'message' => 'You are not Authorised to Login. Please Contact the System Administrator',
    //                     // 'status' => 'error',
    //                 ], 200);
    //             }

    //             // // exit;
    //             // if ($permission['ipRestriction']) {
    //             //     $list = IpList::where('authorised', true)->whereIn('branch', $permission['ipBranches'])->get()->pluck('ipAddress')->toArray();
    //             //     if (in_array($request->ip(), $list)) {
    //             //         // return response()->json(['list'=>$list]);
    //             //         return response()
    //             //             ->json(['message' => 'Login Successful', 'user' => $user, 'permission' => $permission['permission'], 'mapData' => $user->getMapData(), 'requirePasswordReset' => $needsPasswordReset, 'timeZone' => $timeZone, 'access_token' => $token, 'token_type' => 'Bearer',]);

    //             //     } else {

    //             //         return response()->json(['message' => 'You are not Authorised to Login from IP ' . $request->ip() . '. Please Contact the System Administrator'], 200);
    //             //     }
    //             // } else {
    //             //     return response()
    //             //         ->json(['message' => 'Login Successful', 'user' => $user, 'permission' => $permission['permission'], 'mapData' => $user->getMapData(), 'requirePasswordReset' => $needsPasswordReset, 'timeZone' => $timeZone, 'access_token' => $token, 'token_type' => 'Bearer',]);

    //             // }

    //         } else {
    //             return response()->json(['message' => 'You are not Authorised to Login. Please Contact the System Administrator'], 200);

    //         }
    //     } else {
    //         if (Auth::attempt(['memberId' => strtoupper($request['email']), 'password' => $request['password']])) {
    //             $user = User::where('memberId', strtoupper($request['email']))->firstOrFail();
    //             if ($user['authorised'] == true) {
    //                 $user['status'] = 'Online';
    //                 $user->save();
    //                 // $token = $user->createToken('auth_token')->plainTextToken;
    //                 // $timeZone = Settings::all();
    //                 // $timeZone = $timeZone[0]['timeZone']['id'];
    //                 // $needsPasswordReset = true;
    //                 // if ($user['password_reset_at'] != null) {
    //                 //     if ($user['password_reset_at']->diffInDays(Carbon::now()) <= $passwordResetDuration) {
    //                 //         $needsPasswordReset = false;
    //                 //     }
    //                 // }
    //                 $permission = $user->getPermission(true);
    //                 // exit;
    //                 // if ($permission['ipRestriction']) {
    //                 //     $list = IpList::where('authorised', true)->whereIn('branch', $permission['ipBranches'])->get()->pluck('ipAddress')->toArray();
    //                 //     if (in_array($request->ip(), $list)) {
    //                 //         // return response()->json(['list'=>$list]);
    //                 //         return response()
    //                 //             ->json(['message' => 'Login Successful', 'user' => $user, 'permission' => $permission['permission'], 'mapData' => $user->getMapData(), 'requirePasswordReset' => $needsPasswordReset, 'timeZone' => $timeZone, 'access_token' => $token, 'token_type' => 'Bearer',]);

    //                 //     } else {

    //                 //         return response()->json(['message' => 'You are not Authorised to Login from IP ' . $request->ip() . '. Please Contact the System Administrator'], 200);
    //                 //     }
    //                 // } else {
    //                 //     return response()
    //                 //         ->json(['message' => 'Login Successful', 'user' => $user, 'permission' => $permission['permission'], 'mapData' => $user->getMapData(), 'requirePasswordReset' => $needsPasswordReset, 'timeZone' => $timeZone, 'access_token' => $token, 'token_type' => 'Bearer',]);

    //                 // }

    //             } else {
    //                 return response()->json(['message' => 'You are not Authorised to Login. Please Contact the System Administrator'], 200);
    //             }
    //         } else {
    //             return response()
    //                 ->json(['message' => 'Unauthorized'], 200);
    //         }
    //     }

    // }

    // public function login(Request $request)
    // {

    //     $passwordResetDuration = 10;

    //     if (Auth::attempt($request->only('email', 'password'))) {
    //         $user = User::where('email', $request['email'])->firstOrFail();
    //         if ($user['authorised'] == true) {
    //             $user['status'] = 'Online';
    //             $user->save();
    //             $token = $user->createToken('auth_token')->plainTextToken;
    //             $timeZone = Settings::all();
    //             $timeZone = $timeZone[0]['timeZone']['id'];
    //             $needsPasswordReset = true;
    //             if ($user['password_reset_at'] != null) {
    //                 if ($user['password_reset_at']->diffInDays(Carbon::now()) <= $passwordResetDuration) {
    //                     $needsPasswordReset = false;
    //                 }
    //             }
    //             $permission = $user->getPermission(true);
    //             // exit;
    //             if ($permission['ipRestriction']) {
    //                 $list = IpList::where('authorised', true)->whereIn('branch', $permission['ipBranches'])->get()->pluck('ipAddress')->toArray();
    //                 if (in_array($request->ip(), $list)) {
    //                     // return response()->json(['list'=>$list]);
    //                     return response()
    //                         ->json(['message' => 'Login Successful', 'user' => $user, 'permission' => $permission['permission'], 'mapData' => $user->getMapData(), 'requirePasswordReset' => $needsPasswordReset, 'timeZone' => $timeZone, 'access_token' => $token, 'token_type' => 'Bearer',]);

    //                 } else {

    //                     return response()->json(['message' => 'You are not Authorised to Login from IP ' . $request->ip() . '. Please Contact the System Administrator'], 200);
    //                 }
    //             } else {
    //                 return response()
    //                     ->json(['message' => 'Login Successful', 'user' => $user, 'permission' => $permission['permission'], 'mapData' => $user->getMapData(), 'requirePasswordReset' => $needsPasswordReset, 'timeZone' => $timeZone, 'access_token' => $token, 'token_type' => 'Bearer',]);

    //             }

    //         } else {
    //             return response()->json(['message' => 'You are not Authorised to Login. Please Contact the System Administrator'], 200);

    //         }
    //     } else {
    //         if (Auth::attempt(['memberId' => strtoupper($request['email']), 'password' => $request['password']])) {
    //             $user = User::where('memberId', strtoupper($request['email']))->firstOrFail();
    //             if ($user['authorised'] == true) {
    //                 $user['status'] = 'Online';
    //                 $user->save();
    //                 $token = $user->createToken('auth_token')->plainTextToken;
    //                 $timeZone = Settings::all();
    //                 $timeZone = $timeZone[0]['timeZone']['id'];
    //                 $needsPasswordReset = true;
    //                 if ($user['password_reset_at'] != null) {
    //                     if ($user['password_reset_at']->diffInDays(Carbon::now()) <= $passwordResetDuration) {
    //                         $needsPasswordReset = false;
    //                     }
    //                 }
    //                 $permission = $user->getPermission(true);
    //                 // exit;
    //                 if ($permission['ipRestriction']) {
    //                     $list = IpList::where('authorised', true)->whereIn('branch', $permission['ipBranches'])->get()->pluck('ipAddress')->toArray();
    //                     if (in_array($request->ip(), $list)) {
    //                         // return response()->json(['list'=>$list]);
    //                         return response()
    //                             ->json(['message' => 'Login Successful', 'user' => $user, 'permission' => $permission['permission'], 'mapData' => $user->getMapData(), 'requirePasswordReset' => $needsPasswordReset, 'timeZone' => $timeZone, 'access_token' => $token, 'token_type' => 'Bearer',]);

    //                     } else {

    //                         return response()->json(['message' => 'You are not Authorised to Login from IP ' . $request->ip() . '. Please Contact the System Administrator'], 200);
    //                     }
    //                 } else {
    //                     return response()
    //                         ->json(['message' => 'Login Successful', 'user' => $user, 'permission' => $permission['permission'], 'mapData' => $user->getMapData(), 'requirePasswordReset' => $needsPasswordReset, 'timeZone' => $timeZone, 'access_token' => $token, 'token_type' => 'Bearer',]);

    //                 }

    //             } else {
    //                 return response()->json(['message' => 'You are not Authorised to Login. Please Contact the System Administrator'], 200);
    //             }
    //         } else {
    //             return response()
    //                 ->json(['message' => 'Unauthorized'], 401);
    //         }
    //     }
    // }



    // // method for user logout and delete token
    // public function fetchUserByToken(Request $request)
    // {
    //     $passwordResetDuration = 10;
    //     // auth()->user()->tokens()->delete();
    //     // return auth()->user();
    //     return auth()->user()->tokens();
    //     // return $request->bearerToken();
    //     if (auth()->user()) {
    //         // foreach (auth()->user()->tokens() as $token) {
    //         //     if($request->bearerToken()==$token)
    //         // }
    //         $user = User::findOrFail(auth()->user()['_id']);
    //         if ($user['authorised'] == true) {
    //             $user['status'] = 'Online';
    //             $user->save();
    //             $timeZone = Settings::all();
    //             $timeZone = $timeZone[0]['timeZone']['id'];
    //             $needsPasswordReset = true;
    //             if ($user['password_reset_at'] != null) {
    //                 if ($user['password_reset_at']->diffInDays(Carbon::now()) <= $passwordResetDuration) {
    //                     $needsPasswordReset = false;
    //                 }
    //             }
    //             $permission = $user->getPermission(true);
    //             // exit;
    //             if ($permission['ipRestriction']) {
    //                 $list = IpList::where('authorised', true)->whereIn('branch', $permission['ipBranches'])->get()->pluck('ipAddress')->toArray();
    //                 if (in_array($request->ip(), $list)) {
    //                     // return response()->json(['list'=>$list]);
    //                     return response()
    //                         ->json(['message' => 'Login Successful', 'user' => $user, 'permission' => $permission['permission'], 'mapData' => $user->getMapData(), 'requirePasswordReset' => $needsPasswordReset, 'timeZone' => $timeZone, 'access_token' => $token, 'token_type' => 'Bearer',]);

    //                 } else {

    //                     return response()->json(['message' => 'You are not Authorised to Login from IP ' . $request->ip() . '. Please Contact the System Administrator'], 200);
    //                 }
    //             } else {
    //                 return response()
    //                     ->json(['message' => 'Login Successful', 'user' => $user, 'permission' => $permission['permission'], 'mapData' => $user->getMapData(), 'requirePasswordReset' => $needsPasswordReset, 'timeZone' => $timeZone, 'access_token' => $token, 'token_type' => 'Bearer',]);

    //             }

    //         } else {
    //             return response()->json(['message' => 'You are not Authorised to Login. Please Contact the System Administrator'], 200);
    //         }

    //     } else {
    //         return response()->json(['message' => 'Invalid/Expired Token Found. Please Login to Continue'], 200);
    //     }
    // }

    // // method for user logout and delete token
    // public function logout()
    // {
    //     $user = auth()->user();
    //     $user['status'] = 'Not Active';
    //     $user->save();
    //     auth()->user()->tokens()->delete();

    //     return [
    //         'message' => 'success'
    //     ];
    // }

    public function getEnvironment(Request $request)
    {
        $response = [];
        $timeZone = Settings::all();
        $response['timezone'] = $timeZone[0]['timeZone']['id'];
        return json_encode($response);
    }

    public function getAllUsers(Request $request)
    {
        $userArr = [];
        $users = [];
        if ($request->Has('search'))
            $users = User::where('name', 'like', '%' . $request->search . '%')->get();
        else
            $users = User::all();
        // return $users;

        foreach ($users as $user) {
            // if($user->verified=='Verified'){
            $userArr[] = [
                'id' => $user->id,
                'mem_id' => $user->name,
                'name' => $user->name,
                'type' => $user->tradeMode(),
            ];
            // }
        }

        return $userArr;
    }

    public function getAllAccounts(Request $request)
    {
        $accountArr = [];
        $accounts = [];
        if ($request->Has('search'))
            $accounts = TradeAccount::where('accountid', 'like', $request->search . '%')->get();
        else
            $accounts = TradeAccount::all();
        // return $accounts;

        foreach ($accounts as $account) {
            // if($account->verified=='Verified'){
            $accountArr[] = [
                'id' => $account->id,
                // 'acco_id'=> $account->name,
                'accountid' => $account->accountid,
            ];
            // }
        }

        return $accountArr;
    }

    public function getMappingData(Request $request)
    {
        $date = Carbon::today();
        if ($request->has('date')) {
            $date = Carbon::parse($request->get('date'));
        }

        if ($request->has('user')) {
            $mappingData = $this->getMappingByDate($date, 'active', 'NA', true);
            foreach ($mappingData as $key => $mapData) {
                if ($mapData->user === $request->get('user'))
                    return $mapData->toArray();
            }

        } else {
            return $this->getMappingByDate($date, 'active', 'NA', true)->toArray();
        }
    }

    public function getAccountByUser(Request $request)
    {
        $accArr = [];
        foreach ($request['users'] as $key => $userId) {
            $user = User::find($userId);
            $accId = $user->getAccountId();

            if ($accId != null)
                $accArr[] = $accId;
        }
        return $accArr; //json_encode($accArr, true);
    }


    public function login(Request $request)
    {
        // $email = $request->email;
        // $password = $request->password;

        if (Auth::attempt($request->only('email', 'password'))) {
            $user = User::where('email', $request['email'])->firstOrFail();
            if ($user['authorised'] == true) {
                // $user['status'] = 'Online';
                // $user->save();
                $token = $user->createToken('auth_token')->plainTextToken;
                $timeZone = Settings::all();
                $timeZone = $timeZone[0]['timeZone']['id'];
                // $needsPasswordReset = true;
                // if ($user['password_reset_at'] != null) {
                //     if ($user['password_reset_at']->diffInDays(Carbon::now()) <= $passwordResetDuration) {
                //         $needsPasswordReset = false;
                //     }
                // }
                $permission = $user->getPermission(true);
                // exit;
                // if ($permission['ipRestriction']) {
                //     $list = IpList::where('authorised', true)->whereIn('branch', $permission['ipBranches'])->get()->pluck('ipAddress')->toArray();
                //     if (in_array($request->ip(), $list)) {
                //         // return response()->json(['list'=>$list]);
                //         return response()
                //             ->json(['message' => 'Login Successful', 'user' => $user, 'permission' => $permission['permission'], 'mapData' => $user->getMapData(), 'requirePasswordReset' => $needsPasswordReset, 'timeZone' => $timeZone, 'access_token' => $token, 'token_type' => 'Bearer',]);

                //     } else {

                //         return response()->json(['message' => 'You are not Authorised to Login from IP ' . $request->ip() . '. Please Contact the System Administrator'], 200);
                //     }
                // } else {
                $user = $user->toArray();
                $user['timezone'] = $timeZone;
                $user['avatar'] = 'user-avatar.gif';
                $user['roles'] = [
                    [
                        ...$permission['permission'],
                        'name' => $permission['roleNames'],
                        'canmanagerisk' => $permission['permission']['riskManagement']['permission']['canAdd']
                    ]
                ];
                return response()
                    ->json(
                        // 'message' => 'Login Successful',
                        $user
                        // 'permission' => $permission['permission'],
                        // 'mapData' => $user->getMapData(),
                        // 'timezone' => $timeZone,
                        // 'access_token' => $token,
                        // 'token_type' => 'Bearer',
                    );

                // }

            } else {
                return 'account blocked';
                // return response()->json(['message' => 'You are not Authorised to Login. Please Contact the System Administrator'], 200);

            }
        } else {
            if (Auth::attempt(['memberId' => strtoupper($request['email']), 'password' => $request['password']])) {
                $user = User::where('memberId', strtoupper($request['email']))->firstOrFail();
                if ($user['authorised'] == true) {
                    // $user['status'] = 'Online';
                    // $user->save();
                    $token = $user->createToken('auth_token')->plainTextToken;
                    $timeZone = Settings::all();
                    $timeZone = $timeZone[0]['timeZone']['id'];
                    // $needsPasswordReset = true;
                    // if ($user['password_reset_at'] != null) {
                    //     if ($user['password_reset_at']->diffInDays(Carbon::now()) <= $passwordResetDuration) {
                    //         $needsPasswordReset = false;
                    //     }
                    // }
                    $permission = $user->getPermission(true);
                    // exit;
                    // if ($permission['ipRestriction']) {
                    //     $list = IpList::where('authorised', true)->whereIn('branch', $permission['ipBranches'])->get()->pluck('ipAddress')->toArray();
                    //     if (in_array($request->ip(), $list)) {
                    //         // return response()->json(['list'=>$list]);
                    //         return response()
                    //             ->json(['message' => 'Login Successful', 'user' => $user, 'permission' => $permission['permission'], 'mapData' => $user->getMapData(), 'requirePasswordReset' => $needsPasswordReset, 'timeZone' => $timeZone, 'access_token' => $token, 'token_type' => 'Bearer',]);

                    //     } else {

                    //         return response()->json(['message' => 'You are not Authorised to Login from IP ' . $request->ip() . '. Please Contact the System Administrator'], 200);
                    //     }
                    // } else {
                    return response()
                        ->json(['message' => 'Login Successful', 'user' => $user, 'permission' => $permission['permission'], 'mapData' => $user->getMapData(), 'requirePasswordReset' => $needsPasswordReset, 'timeZone' => $timeZone, 'access_token' => $token, 'token_type' => 'Bearer',]);

                    // }

                } else {
                    // return response()->json(['message' => 'You are not Authorised to Login. Please Contact the System Administrator'], 200);
                }
            } else {
                return 'account blocked';
                // return response()
                //     ->json(['message' => 'Unauthorized'], 401);
            }
        }

    } // app/login

    public function getUsers(Request $request)
    {
        $search = $request->search;

    } // app/getUsers


    public function getAccounts(Request $request)
    {
        $search = $request->search;

    } // app/getAccounts
}