<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use Auth;
use App\Models\ReportGroup;
use App\Models\Settings;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ReportGroupController extends Controller
{
	public function index(Request $request)
	{
		if(sizeof(ReportGroup::all())>0)
			return response()->json(['message' => 'success', 'data' => ReportGroup::with('users', 'tradeAccounts')->get()->toArray()], 200);
		else
			return response()->json(['message' => 'Report Group is still not created', 'data' => []], 200);
	}

	public function getReportGroupsByUser(Request $request)
	{
		
		$user = auth()->user();
		$roles = $user->roles;
		$groupIds = [];

		foreach ($roles as $role) {
			foreach ($role->reportGroups as $group) {
				if(!in_array($group['_id'], $groupIds))
					$groupIds[] = $group['_id'];
			}
		}

		if(sizeof($groupIds)>0)
			return response()->json(['message' => 'success', 'data' => ReportGroup::with('users', 'tradeAccounts')->whereIn('_id', $groupIds)->get()->toArray()], 200);
		else
			return response()->json(['message' => 'No Group is Mapped to your Role', 'data' => []], 200);

		return $roles;
	}


	public function store(Request $request)
	{
		$data = $request->data;
		$obj = new ReportGroup();
		
		$obj['name'] = $data['name'];
		$obj['authorised'] = $data['authorised'];
		$obj['reportGroups'] = $data['reportGroups'];

		$status = $obj->save();
		// return $obj;
		if($status){
			// if(sizeof($data['users'])>0){
				// $users = User::whereIn('_id', $data['users'])->get();
				$obj->users()->sync($data['users']);
			// }
			// if(sizeof($data['reportGroups'])>0)
				// $obj->reportGroups()->sync($data['reportGroups']);
			// if(sizeof($data['tradeAccounts'])>0)
				$obj->tradeAccounts()->sync($data['tradeAccounts']);
			return response()->json([
				'message' => "success", 
				'reportGroup' => ReportGroup::with('users', 'tradeAccounts')->find($obj->_id)
			], 200);
		}else{
			return response()->json(['message' => "Could'nt create the Group.Please Try Again"], 200);
		}

	}

	public function update(Request $request, $group)
	{
		$data = $request->data;
		$obj = ReportGroup::find($group);
		
		$obj['name'] = $data['name'];
		$obj['authorised'] = $data['authorised'];

		$obj['reportGroups'] = $data['reportGroups'];
		$status = $obj->update();
		// if($status){
			// if(sizeof($data['users'])>0)
				$obj->users()->sync($data['users']);
			// if(sizeof($data['reportGroups'])>0)
				// $obj->reportGroups()->sync($data['reportGroups']);
			// if(sizeof($data['tradeAccounts'])>0)
				$obj->tradeAccounts()->sync($data['tradeAccounts']);
			return response()->json([
				'message' => "success", 
				'reportGroup' => ReportGroup::with('users', 'tradeAccounts')->find($obj->_id)
			], 200);
		// }else{
			// return response()->json(['message' => "Could'nt create the Group.Please Try Again"], 200);
		// }

	}

	public function destroyMultiple(Request $request)
	{
		if($request->Has('ids')){
			$groups = ReportGroup::whereIn('_id', $request->ids)->get();
			foreach ($groups as $group) {
				$group->users()->sync([]);
				// $group->reportGroups()->sync([]);
				$group->tradeAccounts()->sync([]);
				ReportGroup::where("reportGroups", "all", [$group['_id']])->pull('reportGroups', $group['_id']);
				$group->delete();
			}
			return response()->json(['message' => "success"], 200);
		}else{
			return response()->json(['message' => 'Groups not Selected'], 404);
		}
	}

	public function getAllMembers(Request $request, $group)
	{
		if($data = ReportGroup::find($group)){
			$temp = $data->getAllMembers();
			$tmp = [];

			foreach ($temp['users'] as $key => $value) {
				$tmp['users'][] = [
					'id' => $key,
					'text' => $value
				];
				$tmp['all'][] = [
					'id' => $key,
					'text' => $value
				];
			}
			foreach ($temp['accounts'] as $key => $value) {
				$tmp['accounts'][] = [
					'id' => 'acc_'.$value,
					'text' => $value
				];
				$tmp['all'][] = [
					'id' => 'acc_'.$value,
					'text' => $value
				];
			}
			// return $tmp;
			return response()->json(['message' => "success", 'data' => $tmp], 200);
		}else
			return response()->json(['message' => "Could'nt load Members of the selected Group"], 200);
	}




}