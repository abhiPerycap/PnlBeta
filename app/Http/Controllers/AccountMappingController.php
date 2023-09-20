<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Broker;
use App\Models\Acmap;
use App\Models\TradeAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use App\Traits\AccountMappingTrait;

use Session;

class AccountMappingController extends Controller
{
	use AccountMappingTrait;

	public function index(Request $request)
	{
		$data = Acmap::with('executor', 'effectedUser')->get();
		return $data;
	}

	public function segregated(Request $request)
	{
		// $data = Acmap::with('executor', 'effectedUser')->get();
		$activeMapping = $this->getMappingByDate('today', 'active', 'NA', false, true);
		// $activeMappingIds = [];
		// foreach ($activeMapping as $key => $value) {
		// 	$activeMappingIds[] = $value->_id;
		// }
        $logMapping = $this->getMappingByDate('today', 'log', 'NA', false, true);
		// $logMappingIds = [];
		// foreach ($logMapping as $key => $value) {
		// 	$logMappingIds[] = $value->_id;
		// }

		// return ['active' => Acmap::whereIn('_id', $activeMappingIds)->with('executor', 'effectedUser')->get(), 'log' => Acmap::whereIn('_id', $logMappingIds)->with('executor', 'effectedUser')->get()];
		return ['active' => $activeMapping, 'log' => $logMapping];
	}

	public function gate(Request $request, $userId)
	{
		if(Session::has('primaryUser')){
			return view('step1');

		}else{
			if($request->isMethod('get')){
				$user = User::where('_id', $userId)->first();
				return view('gate')->with('user', $user);
			}else{
				if($request->has('password')){
					$user = User::where('_id', $request->_id)->first();
					if(Hash::check($request->password, $user['password'])){
						// return $user;
						Session::put('primaryUser', $user);
						return view('step1');
					}
				}
				return redirect()->back()->with('error', 'Login failed! Try Again');
			}

		}
	}

	public function checkForIssueInRow(Request $request)
    {
    	// return $request->all();
        return $this->checkForIssue($request);
    }

    public function multiAccMapperAction(Request $request)
    {
        return $this->mapMultiUserToAccount($request);
    }

    public function treeMapper(Request $request)
    {
        return $this->reMap($request);
        return view('dashboard.workgroups.mappingTree')->with('mappingArray', $retArr);
        exit;
        // $data = $request->all();
        // unset($data['_token']);
        // unset($data['_method']);
        // $retArr = [];
        // foreach ($data as $key => $value) {
        //     $tmp = explode('_', $key);
        //     $retArr = $this->insert_using_keys($retArr, $tmp, $value);
        // }
        // return $retArr;
    }

}