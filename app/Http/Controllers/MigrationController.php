<?php


namespace App\Http\Controllers;

ini_set('max_execution_time', 300);
use Illuminate\Http\Request;
use App\Models\PreviousDayOpen;
use App\Models\Oreport;
// use App\Models\User;
use Carbon\Carbon;
use App\Imports\PreviousDayOpenImport;
use Excel;

use App\Traits\DataAdapterTrait;
use App\Traits\ReportGeneratorTrait;
use Illuminate\Support\Str;
use Exception;

use App\Models\DetailedBase;
use App\Models\Detailed;
use App\Models\Adjustment;
use App\Models\Locatedata;
use App\Models\Closedata;
use App\Models\Open;
// use App\Models\Oreport;
use App\Models\Dreport;
use App\Models\Areport;
use App\Models\Userlocate;
use App\Models\TradeAccount;
use App\Models\Locreport;
use App\Utils\DataMapperClass;
use App\Utils\ReportGeneratorClass;


use Illuminate\Support\Facades\Http;
use App\Models\Settings;
use App\Models\User;
use App\Models\Symbol;
use App\Models\Acmap;
use App\Models\UserData;
use MongoDB;
// use Carbon;
use Illuminate\Support\Facades\Hash;


class MigrationController extends Controller
{

	use DataAdapterTrait;
	use ReportGeneratorTrait;

	public function migrateFromAPI($type)
	{
		switch ($type) {
			case 'user':
			exit;
				$response = $this->callApi([
					'method' => 'GET',
					'url' => 'http://159.223.52.224/api/getUsersJson',
					'data' => []
				]);
				if($response->ok()){
					$userDefaultRole = Settings::first()['userDefaultRole']['id'];
					
					$users = [];
					$userIds = [];

					foreach ($response[0] as $key => $value) {
						$userIds[] = $value['oldUserId'];
						$users[] = [
				            'name' => strtoupper($value['name']),
				            'memberId' => strtoupper($value['memberId']),
				            'type' => 'Traders',
				            'email' => $value['email'],
				            'emailverified' => $value['emailverified'],
				            'mobile' => $value['mobile'],
				            'address' => $value['address'],
				            'authorised' => $value['authorised'],
				            'password' => Hash::make('12345'),
				            'helpdeskpass' => 'secret',
				            'status' => $value['status'],
				            'islocal' => $value['isLocal'],
				            'oldUserId' => $value['oldUserId'],
				        ];
					}

					if(User::insert($users)){
						$userIdsforRole = User::whereIn('oldUserId', $userIds)->get()->pluck('_id')->toArray();
						$userIds = User::whereIn('oldUserId', $userIds)->get()->pluck('_id', 'memberId')->toArray();
						$settings = Settings::getSettingsData();
						
						if($settings!=null){
							$defaultRoleForNewUser = $settings['userDefaultRole'];
							if($defaultRoleForNewUser!=null){
							  $defaultRoleForNewUser->users()->sync(array_merge($defaultRoleForNewUser['user_ids'], $userIdsforRole));
							}
						}
						
						$acmaps = [];
						foreach ($response[1] as $key => $value) {
							
							$acmaps[] = [
  								"startdate"=> $value['startdate'],
  								"mappedTo"=> $value['mappedTo']??'-',
  								"role"=> $value['role'],
  								"user"=> $userIds[$value['user']]??$value['user'],
  								"type"=> "log",
  								"executedBy"=> $userIds[$value['executed_by']]??$value['executed_by'],
  								"created_at" => new MongoDB\BSON\UTCDateTime(Carbon::parse($value['created_at'])),
  								"updated_at" => new MongoDB\BSON\UTCDateTime(Carbon::parse($value['updated_at']))
							];
						}
						if(Acmap::insert($acmaps)){
							return 'User & Mapping Data Migration Complete';
						}else{
							return 'User Data Migration Complete but Mapping Data Migration Failed';
						}
					}else{
						return 'User Data Migration Failed';
					}
				}else{
					return 'Failed to Fetch Migration Data from PNL Server';
				}
				
				break;

			case 'mapping':
			exit;
				$response = $this->callApi([
					'method' => 'GET',
					'url' => 'http://159.223.52.224/api/getUsersJson',
					'data' => []
				]);
				// return Settings::getSettingsData()['userDefaultRole']['user_ids'];
				if($response->ok()){
					// $userDefaultRole = Settings::first()['userDefaultRole']['id'];
					
					// $users = [];
					// $userIds = [];

					// foreach ($response[0] as $key => $value) {
					// 	$userIds[] = $value['oldUserId'];
					// 	$users[] = [
				    //         'name' => strtoupper($value['name']),
				    //         'memberId' => strtoupper($value['memberId']),
				    //         'type' => 'Traders',
				    //         'email' => $value['email'],
				    //         'emailverified' => $value['emailverified'],
				    //         'mobile' => $value['mobile'],
				    //         'address' => $value['address'],
				    //         'authorised' => $value['authorised'],
				    //         'password' => Hash::make('12345'),
				    //         'helpdeskpass' => 'secret',
				    //         'status' => $value['status'],
				    //         'islocal' => $value['isLocal'],
				    //         'oldUserId' => $value['oldUserId'],
				    //         // 'role_ids' => [
				    //         //   $userDefaultRole,
				    //         // ]
				    //     ];
					// }

					// if(User::insert($users)){
						// $userIdsforRole = User::whereIn('oldUserId', $userIds)->get()->pluck('_id')->toArray();
						$userIds = User::all()->pluck('_id', 'memberId')->toArray();
						// $settings = Settings::getSettingsData();
						
						// if($settings!=null){
						// 	$defaultRoleForNewUser = $settings['userDefaultRole'];
						// 	if($defaultRoleForNewUser!=null){
						// 	  $defaultRoleForNewUser->users()->sync(array_merge($defaultRoleForNewUser['user_ids'], $userIdsforRole));
						// 	}
						// }
						
						$acmaps = [];
						foreach ($response[1] as $key => $value) {
							$executedBy = '';
							if($value['executed_by']!=' ' && array_key_exists($value['executed_by'], $userIds))
								$executedBy = $userIds[$value['executed_by']];
							$acmaps[] = [
  								"startdate"=> $value['startdate'],
  								"mappedTo"=> $value['mappedTo']??'-',
  								"role"=> $value['role'],
  								"user"=> $userIds[strtoupper($value['user'])]??'-',
  								"type"=> "log",
  								"executedBy"=> $executedBy,
  								"created_at" => new MongoDB\BSON\UTCDateTime(Carbon::parse($value['created_at'])),
  								"updated_at" => new MongoDB\BSON\UTCDateTime(Carbon::parse($value['updated_at']))
							];
							// User::where('oldUserId', $value['user'])->first()['_id'];
						}
						return Acmap::insert($acmaps);
					// }
				}else{
					return 'error';
				}
				
				break;

			case 'crm-user':
			exit;
				$response = $this->callApi([
					'method' => 'GET',
					'url' => 'https://crm.perycap.com/api/members',
					'data' => []
				], 'perycapdwwte342Wrrt34eeffnfWjfedfhWEEfsfne');
				// return Settings::getSettingsData()['userDefaultRole']['user_ids'];
				if($response->ok()){
					$userDefaultRole = Settings::first()['userDefaultRole']['id'];
					$existingMemberIds = User::all()->pluck('memberId')->toArray();
					// return $userDefaultRole;
					// return $response[1];
					$users = [];
					$userIds = [];
					// return $response;
					foreach ($response->collect() as $key => $value) {
						if(!in_array(strtoupper('mem'.$value['id']), $existingMemberIds)){
							$userIds[] = strtoupper('mem'.$value['id']);
							$users[] = [
					            'name' => ($value['name']),
					            'memberId' => strtoupper('mem'.$value['id']),
					            'type' => 'Traders',
					            'email' => 'mem'.$value['id'].'@perycap.com',
					            'emailId' => $value['email'],
					            'emailverified' => true,
					            'mobile' => $value['phone'],
					            'address' => $value['city'],
					            'authorised' => true,
					            'password' => Hash::make('12345'),
					            'helpdeskpass' => '12345',
					            'status' => 'Not Active',
					            'islocal' => true,
					            'crmUserId' => $value['id'],
					            // 'role_ids' => [
					            //   $userDefaultRole,
					            // ]
					        ];
						}
					}

					// return $users;

					if(User::insert($users)){
						$userIdsforRole = User::whereIn('memberId', $userIds)->get()->pluck('_id')->toArray();
						$settings = Settings::getSettingsData();
						
						if($settings!=null){
							$defaultRoleForNewUser = $settings['userDefaultRole'];
							if($defaultRoleForNewUser!=null){
							  $defaultRoleForNewUser->users()->sync(array_merge($defaultRoleForNewUser['user_ids'], $userIdsforRole));
							}
						}
						return 'Users Inserted';
					}
				}else{
					return 'error';
				}
				
				break;
			
			case 'symbol':
			exit;
				$response = $this->callApi([
					'method' => 'GET',
					'url' => 'http://159.223.52.224/api/getSymbolsJson',
					'data' => []
				]);
				// return Settings::getSettingsData()['userDefaultRole']['user_ids'];
				if($response->ok()){
					// return $response[0];
					$symbols = [];
					foreach ($response->collect() as $key => $value) {
						// $userIds[] = $value['oldUserId'];
						$symbols[] = [
				            'name' => strtoupper($value['name']),
				            'fullName' => ($value['fullname']),
				            'status' => strtolower($value['verified'])=='verified'?"approved":"rejected",
				            'user_id' => "634ac7ac5654a817920d6684",
				            "created_at" => new MongoDB\BSON\UTCDateTime(Carbon::parse($value['created_at'])),
  							"updated_at" => new MongoDB\BSON\UTCDateTime(Carbon::parse($value['updated_at']))
				        ];
					}
					return Symbol::insert($symbols);
					// return $symbols;
				}
				break;
			
			case 'user-data':
			// return 'Hi';
			// $userIds = User::get()->pluck('_id', 'oldUserId')->toArray();
			// //return $userIds;
			// $userDatas = UserData::all();
			// foreach ($userIds as $key => $value) {

			// 	UserData::where('user_id', (string)$key)->update(['user_id' => $value]);
			// }
			// $users = User::all();
			// foreach ($users as $key => $value) {
			// 	$value['password_reset_at'] = null;
			// 	$value->update();
			// }
			
			// foreach ($userDatas as $key => $value) {
				// $value['user_id'] = $userIds[$value['user_id']]??$value['user_id'];
				// $value['status'] = strtolower($value['status']);
				//$value["verifiedBy"] = strtoupper($value['verifiedBy']);
				//$value["symbol"] = strtoupper($value['symbol']);
				//$value["executionId"] = 'TR'.strtoupper($value['oid']);
				// $value["oid"] = ($value['id']);
				// $value["created_at"] = new MongoDB\BSON\UTCDateTime(Carbon::parse($value['created_at']));
			  	//$value["updatedAt"] = new MongoDB\BSON\UTCDateTime(Carbon::parse($value['updated_at']));
			  	// $value->update();
			// }
			return 'done';




			exit;

				$response = $this->callApi([
					'method' => 'POST',
					'url' => 'http://159.223.52.224/api/getUserDataIdArrayJson',
					'data' => []
				]);

				if($response->ok()){

					$userIds = User::get()->pluck('_id', 'oldUserId')->toArray();
					$udIds = $response->collect()->toArray();
					$chunkSize = 30000;
					$allData = [];
					for($i= 0; ; ) {
// return $udIds;
						if($i>sizeof($udIds)-1)
							break;
						else{
							if(($i+$chunkSize)>sizeof($udIds)-1)
								$ci = sizeof($udIds)-1;
							else
								$ci = $i+$chunkSize;

							// $start = $udIds[$i];
							// $end = $end[$ci];

							$slicedIds = array_slice($udIds, $i, ($ci-$i));

							$response = $this->callApi([
								'method' => 'POST',
								'url' => 'http://159.223.52.224/api/getUserDataJson',
								'data' => [
									"ids" => $slicedIds
								]
							]);
							// return $response;
							if($response->ok()){
								// $userDatas = [];
								foreach ($response->collect() as $key => $value) {
									// $userIds[] = $value['oldUserId'];
									$allData[] = [
										  "date" => $value['date'],
										  "accountid" => $value['accountid'],
										  "status" => strtolower($value['status']),
										  "verifiedBy" => strtoupper($value['verifiedby']),
										  "symbol" => strtoupper($value['symbol']),
										  "grossPnl" => $value['grosspnl'],
										  "quantity" => $value['qty'],
										  "user_id" => $userIds[$value['user_id']]??null,
										  // "executionId" => "TR1665855057",
										  "created_at" => new MongoDB\BSON\UTCDateTime(Carbon::parse($value['created_at'])),
			  							  "updated_at" => new MongoDB\BSON\UTCDateTime(Carbon::parse($value['updated_at']))
							        ];
								}
							}else
								return $response;

							// if($ci==$udIds[sizeof($udIds)-1])
								$i = $ci+1;
							// else
						}
					}

					
					return $allData;
					// return UserData::insert($userDatas);
					// return $userDatas;
				}else
					return $response;
				break;
			
			default:
				// code...
				break;
		}
	}


	public function callApi($data, $token=null)
	{
		if(strtolower($data['method'])=='get'){
			if($token!=null)
				$response = Http::withToken($token)->get($data['url']);
			else
				$response = Http::get($data['url']);
		}else{
			if($token!=null)
				$response = Http::withToken($token)->post($data['url'], $data['data']);	
			else
				$response = Http::post($data['url'], $data['data']);	
		}
		// $response = Http::withHeaders([
		//     'Authorization' => 'token' 
		// ])->post('http://example.com/users', [
		//     'name' => 'Akash'
		// ]);

		// $response->body() : string;
		// $response->json() : array|mixed;
		// $response->object() : object;
		// $response->collect() : Illuminate\Support\Collection;
		// $response->status() : int;
		// $response->ok() : bool;
		// $response->successful() : bool;
		// $response->failed() : bool;
		// $response->serverError() : bool;
		// $response->clientError() : bool;
		// $response->header($header) : string;
		// $response->headers() : array;

		return $response;
	}





}