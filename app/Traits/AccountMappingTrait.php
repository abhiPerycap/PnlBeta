<?php 
namespace App\Traits;

use Carbon\Carbon;

use App\Models\TradeAccount;
use App\Models\User;
use App\Models\Acmap;
use Log;
use Session;
use Carbon\CarbonPeriod;


const DEBUG = false;

$currentTR = ['koi'];
$nodeFlow = false;

trait AccountMappingTrait
{

	public function checkForIssue($request)
    {
    	global $currentTR;

    	
    	// dd($request->all());
    	// return $this->getUnassigned();
    	$newTR = [];
    	if($request->Has('all')){
	    	$allTR = json_decode($request['trDetails'], true);
	    	foreach ($allTR as $key => $value) {
	    		$currentTR = [];
	    		$currentTR = array_filter($allTR, function($keyF) use($key){
	    			return $keyF!=$key;
	    		}, ARRAY_FILTER_USE_KEY);

	    		$request1 = [];

	    		if(strpos($value['role'], 'disable_')!==false){
		    		$type = explode('_', $value['role']);
		    		if($type[1]==='user'){
		    			unset($request['groupid']);
		    			$request1 = [
		    				'setDisabled' => 'on',
			    			'startdate'=> $value['startdate'],
							// 'groupid'=> $value['mappedTo'],
							'role'=> 'disable',
							'newmems'=> $value['user'],
			    		];
		    		}else{
		    			$request1 = [
		    				'setDisabled' => 'on',
			    			'startdate'=> $value['startdate'],
							'groupid'=> $value['mappedTo'],
							'role'=> 'disable',
							'mappedUserAction'=> 'mi',
			    		];
		    		}
		    		// dd($request);		    		
		    	}else{
		    		$request1 = [
		    			'startdate'=> $value['startdate'],
						'groupid'=> $value['mappedTo'],
						'role'=> $value['role'],
						'newmems'=> $value['user'],
		    		];
		    	}

	    		$res = '';
		            // dd($curren);
	   //  		if($request1['role']=='master')
				// 	$res = $this->mappingForMaster($request1, true);
				// else
				// 	$res = $this->mappingForSub($request1, true);

				if($request1['role']=='master')
					$res = $this->mappingForMaster($request1, true);
				elseif($request1['role']=='sub')
					$res = $this->mappingForSub($request1, true);
				else
					$res = $this->mappingForDisable($request1, true);


				// $obj = $value;
				$obj6 = '';
				$obj7 = '';

				if(is_numeric($res) && $res==0){
		            $obj6 = '<i class="fa fa-check" aria-hidden="true" style="color:green;"></i>';
		            $obj7 = 'success';
					// table.row(i).data($obj).draw();
	        	}else if(is_numeric($res) && $res>0){
	        		// $obj6 = '<i class="fa fa-ban" aria-hidden="true" style="color:red;"></i>';
	        		$obj6 = '<i class="fa fa-exclamation-circle" aria-hidden="true" style="color:#e15f00;">'.$res.'</i>';
		            $obj7 = 'issue';
					// table.row(i).data($obj).draw();

	        	}else if(!is_numeric($res) && strlen($res)<=100){
					$obj6 = $res;
		            $obj7 = 'issue';
					// table.row(i).data($obj).draw();

	        	}else{
					$obj6 = '<i class="fa fa-ban" aria-hidden="true" style="color:red;"></i>';
		            $obj7 = 'cancelled';
					// table.row(i).data($obj).draw();
	        	}
				$obj = [
					'',
					$value['startdate'],
					$value['mappedTo'],
					implode(', ', User::whereIn('_id', $value['user'])->get()->pluck('name')->toArray()),
					$value['user'],
					ucwords($value['role']),
					$obj6,
					$obj7,
				];
	        	$newTR[] = $obj;
	    	}
	    	return (json_encode($newTR));
    	}else{

    		if(strpos($request->role, 'disable_')!==false){
	    		$type = explode('_', $request->role);
	    		if($type[1]==='user'){
	    			unset($request['groupid']);
	    		}
	    		else{

	    			unset($request['newmems']);
	    			$request['mappedUserAction'] = 'mi';
	    		}

	    		$request['role'] = 'disable';
	    		$request['setDisabled'] = 'on';
	    		// dd($request);
	    	}

	    	$currentTR = json_decode($request['trDetails'], true);
	    	// dd(json_decode($request['trDetails'], true));
			// return $this->getUnassigned();
	    	// return view('dashboard.workgroups.mapMultiAccount');	
	    	if($request['role']=='master')
				return $this->mappingForMaster($request, true);
			elseif($request['role']=='sub')
				return $this->mappingForSub($request, true);
			else
				return $this->mappingForDisable($request, true);
	    	// return $request;	
    	}
    }

    public function mappingForMaster($request, $cfif = false, $isMultiAcc = false){
		global $currentTR;
		// dd($currentTR);
		$userArray = $this->getUserArray($request['newmems']);
		$this->checkUserCountRestriction($userArray, $request['role']);
		// echo 'hi';
		if($request['role']==='master'){
			$this->checkIfAccountDisabled($request['groupid'], $request['startdate'], $cfif);
			$this->checkIfUserDisabled($request['newmems'], $request['startdate'], $cfif);
			$retArr = $this->makeArrayFromRequest($request);

	    	$today = Carbon::parse(Carbon::now()->toDateString());
	    	$effectiveDate = Carbon::parse($retArr[0]['startdate']);

			$retArr = $this->compileMasterMapping($retArr);
			// dd($retArr);
			// return $retArr;
			
			if($isMultiAcc)
				return $retArr[0];
			$flag = $this->checkForOpenEnd($retArr);
			if($cfif)
				return $flag;
			if($flag==0){
				$quaArray = $this->makeQueryArray($retArr);
			
				// dd($quaArray);
				$this->processQueryArray($quaArray['queryArray']);
				
				

				# This Portion of Code will be enabled after implementation of Manual Execution and Report Section
				
				/*
				$this->relocateUserData($effectiveDate->toDateString().' - '.$today->toDateString(), $quaArray['users']);
				$trId = $this->resetReportForMapChange($effectiveDate->toDateString().' - '.$today->toDateString(), $quaArray['accounts'], null, 'Acmap-NoMasterEffect');

				Session::put('uploadStartDate', $effectiveDate->toDateString());
	            Session::put('uploadEndDate', $today->toDateString());
	            Session::put('TR_ID', $trId);

	            return view('pages.compilereportloader');
	            */

	            return view('greetings');
			}else{
				return view('mappingTree')->with('mappingArray', $retArr);
			}
		}
	}

	public function mappingForSub($request, $cfif = false, $isMultiAcc = false){
		global $currentTR;
		// dd($currentTR);
		$userArray = $this->getUserArray($request['newmems']);
		$this->checkUserCountRestriction($userArray, $request['role']);
		// echo 'hi';
		if($request['role']==='sub'){
			$this->checkIfAccountDisabled($request['groupid'], $request['startdate'], $cfif);
			$this->checkIfUserDisabled($request['newmems'], $request['startdate'], $cfif);
			$tmf = $this->checkIfAccountHasMaster($request['groupid'], $request['startdate'], $cfif);
			// dd($request->all());
			// dd($this->verifyFromCurrentTransaction($request, $currentTR, 'master'));
			if(!in_array($tmf, ['0']) && !$this->verifyFromCurrentTransaction($request, $currentTR, 'master')['status'])
				return $tmf;
			// dd(!in_array($tmf, ['0']));
			$retArr = $this->makeArrayFromRequest($request);


	    	$today = Carbon::parse(Carbon::now()->toDateString());
	    	$effectiveDate = Carbon::parse($retArr[0]['startdate']);


			$retArr = $this->compileSubMapping($retArr);
			// dd($retArr);
			// return $retArr;
			if($isMultiAcc)
				return $retArr[0];
			$flag = $this->checkForOpenEnd($retArr);
			if($cfif)
				return $flag;
			if($flag==0){
				$quaArray = $this->makeQueryArray($retArr);
			
				// dd($quaArray);
				$this->processQueryArray($quaArray['queryArray']);

				

				# This Portion of Code will be enabled after implementation of Manual Execution and Report Section
				
				/*
				$this->relocateUserData($effectiveDate->toDateString().' - '.$today->toDateString(), $quaArray['users']);
				$trId = $this->resetReportForMapChange($effectiveDate->toDateString().' - '.$today->toDateString(), $quaArray['accounts'], null, 'Acmap-NoMasterEffect');

				Session::put('uploadStartDate', $effectiveDate->toDateString());
	            Session::put('uploadEndDate', $today->toDateString());
	            Session::put('TR_ID', $trId);
				
	            return view('pages.compilereportloader');
	            */
	            return view('greetings');


			}else{
				return view('mappingTree')->with('mappingArray', $retArr);
			}
		}
	}


	public function mappingForDisable($request, $cfif = false, $isMultiAcc = false)
	{
		global $currentTR;

		if($request['role']==='disable'){
			$retArr = [];

			if(isset($request['groupid'])){
				$retArr = [[
					'setDisabled' => 'on',
					'mappedUserAction' => 'mi',
					'startdate' => $request['startdate'],
					'groupid' => $request['groupid'],
					'role' => $request['role'],
				]];
			}else{
				$retArr = [[
					'setDisabled' => 'on',
					'newmems' => $this->getUserArray($request['newmems']),
					'startdate' => $request['startdate'],
					'role' => $request['role'],
				]];	
			}
			


	    	$today = Carbon::parse(Carbon::now()->toDateString());
	    	$effectiveDate = Carbon::parse($retArr[0]['startdate']);


			$retArr = $this->compileDisableMapping($retArr);
			// dd($retArr);
			// return $retArr;
			if($isMultiAcc)
				return $retArr[0];
			$flag = $this->checkForOpenEnd($retArr);
			if($cfif)
				return $flag;
			if($flag==0){
				$quaArray = $this->makeQueryArray($retArr);
			
				// dd($quaArray);
				$this->processQueryArray($quaArray['queryArray']);

				

				# This Portion of Code will be enabled after implementation of Manual Execution and Report Section
				
				/*
				$this->relocateUserData($effectiveDate->toDateString().' - '.$today->toDateString(), $quaArray['users']);
				$trId = $this->resetReportForMapChange($effectiveDate->toDateString().' - '.$today->toDateString(), $quaArray['accounts'], null, 'Acmap-NoMasterEffect');

				Session::put('uploadStartDate', $effectiveDate->toDateString());
	            Session::put('uploadEndDate', $today->toDateString());
	            Session::put('TR_ID', $trId);

	            return view('pages.compilereportloader');
	            */
	            return view('greetings');
			}else{
				return view('mappingTree')->with('mappingArray', $retArr);
			}
		}
	}




	public function mapMultiUserToAccount($request){
		global $currentTR;
		// dd($request->all());
		$dataArray = json_decode($request['multiData']);
        
        $rowArr = [];
        $currArr = [];
        foreach($dataArray as $key => $row){

        	if(strpos(strtolower($row[5]), 'disable_')!==false){

        		$roleModified = explode('_', $row[5]);

        		if(isset($row[2]) && $row[2]!=null){
        			$rowArr[$key] = [
		                'groupid' => $row[2],
		                'trFor' => 'account',
		                'startdate' => Carbon::parse($row[1])->toDateString(),
		                'setDisabled' => 'on',
		                'mappedUserAction' => 'mi',
		                'role' => 'disable',
		            ];

		            $currArr[] = [
		            	'_id' => $key,
						'startdate' => Carbon::parse($row[1])->toDateString(),
						'user' => null,
						'mappedTo' => $row[2],
						'role' => 'disable',
						'status' => strtolower($row[7]),
		            ];
        		}else{
        			$rowArr[$key] = [
		                'trFor' => 'user',
	                	'newmems' => $row[4],
		                'startdate' => Carbon::parse($row[1])->toDateString(),
		                'setDisabled' => 'on',
		                'role' => 'disable',
		            ];

		            $currArr[] = [
		            	'_id' => $key,
						'startdate' => Carbon::parse($row[1])->toDateString(),
						'user' => $row[4],
						'mappedTo' => null,
						'role' => 'disable',
						'status' => strtolower($row[7]),
		            ];
        		}
        	}else{
	            $rowArr[$key] = [
	                'groupid' => $row[2],
	                'startdate' => Carbon::parse($row[1])->toDateString(),
	                'newmems' => $row[4],
	                'role' => strtolower($row[5]),
	            ];
	            $currArr[] = [
	            	'_id' => $key,
					'startdate' => Carbon::parse($row[1])->toDateString(),
					'user' => $row[4],
					'mappedTo' => $row[2],
					'role' => strtolower($row[5]),
					'status' => strtolower($row[7]),
	            ];

        	}

        }
		// dd($rowArr);
		$retArr = [];
		foreach($rowArr as $key => $data){
			$currentTR = [];
			$currentTR = array_filter($currArr, function($dataF) use($key){
				return $dataF['_id']!=$key;
			});

			if($data['role']=='master')
				$retArr[] = $this->mappingForMaster($data, false, true);
			elseif($data['role']=='sub')
				$retArr[] = $this->mappingForSub($data, false, true);
			else
				$retArr[] = $this->mappingForDisable($data, false, true);
		}

		$flag = $this->checkForOpenEnd($retArr);
		
			// dd($retArr);
		if($flag==0){
			// echo 'no issue';
			// dd($retArr);
			$today = Carbon::today();
			$quaArray = $this->makeQueryArray($retArr);
			// dd($quaArray);

			if(sizeof($quaArray['dates'])>0){
				$dateArr = $quaArray['dates'];
				usort($dateArr, function($date1, $date2){
				  if (strtotime($date1) < strtotime($date2))
				     return 1;
				  else if (strtotime($date1) > strtotime($date2))
				     return -1;
				  else
				     return 0;
				});
				$effectiveDate = Carbon::parse($dateArr[0]);
				$this->processQueryArray($quaArray['queryArray']);

				

				# This Portion of Code will be enabled after implementation of Manual Execution and Report Section
				
				/*
				$this->relocateUserData($effectiveDate->toDateString().' - '.$today->toDateString(), $quaArray['users']);
				$trId = $this->resetReportForMapChange($effectiveDate->toDateString().' - '.$today->toDateString(), $quaArray['accounts'], null, 'Acmap-NoMasterEffect');

				Session::put('uploadStartDate', $effectiveDate->toDateString());
	            Session::put('uploadEndDate', $today->toDateString());
	            Session::put('TR_ID', $trId);

	            return view('pages.compilereportloader');
	            */
	            return view('greetings');

			}else{
				$this->processQueryArray($quaArray['queryArray']);
	            
				# This below line which is comment out, got executed when there is no report to regenerate
	            //return redirect('/workgroups');
	            return view('greetings');

			}
		}else{
			// return ['mappingArray' => $retArr, 'trDetails' => $currArr];
			return view('mappingTree', ['mappingArray' => $retArr, 'trDetails' => $currArr]);
		}
	}




	# Helper Function
	public function processQueryArray($qArray)
    {
    	// dd($qArray);
		$uidArr = [];
		$accArr = [];
    	foreach ($qArray as $key => $qa) {
    		if($qa['trType']==="mapping"){
    			$tmp = $qa;
    			$tmp['startdate'] = Carbon::parse($tmp['startdate']);
    			// unset($tmp['trType']);
    			// dd($tmp);
    			if($tmp['role']=='master'){
    				$tmpMapArr = Acmap::where('startdate', $tmp['startdate']->toDateString())->where('mappedTo', $tmp['mappedTo'])->whereIn('role', ['disabled', $tmp['role']])->get()->pluck('_id');
    				if(isset($tmpMapArr)){
    					Acmap::whereIn('_id', $tmpMapArr)->delete();
    				}

    				$tmpMapArr1 = Acmap::where('startdate', $tmp['startdate']->toDateString())->where('user', $tmp['user'])->get()->pluck('_id');
    				if(isset($tmpMapArr1)){
    					Acmap::whereIn('_id', $tmpMapArr1)->delete();
    					// $tmpMap->update(['mappedTo' => $tmp['mappedTo'], 'role' => $tmp['role']]);
    				}
    			}else{
    				$tmpMapArr = Acmap::where('startdate', $tmp['startdate']->toDateString())->where('user', $tmp['user'])->get()->pluck('_id');
    				if(isset($tmpMapArr)){
    					Acmap::whereIn('_id', $tmpMapArr)->delete();
    					// $tmpMap->update(['mappedTo' => $tmp['mappedTo'], 'role' => $tmp['role']]);
    				}
    				// Acmap::create($tmp);
    			}
    			Acmap::create($tmp);
    		}
    		if($qa['trType']==="accountDisable"){
    			$disableEntry = [
    				'startdate' => $qa['startdate'],
    				'user' => $qa['account'],
    				'role' => 'disabled',
    				'type' => 'log',
    				'mappedTo' => $qa['account'],
    			];
    			$tmp = $qa;
    			$tmp['startdate'] = Carbon::parse($tmp['startdate']);
    			
    			if($qa['accountType']==='user'){
					// dd($tmp);
    				$tmpMapArr = Acmap::where('startdate', $tmp['startdate']->toDateString())->where('user', $tmp['account'])->get()->pluck('_id');
    				if(isset($tmpMapArr)){
    					Acmap::whereIn('_id', $tmpMapArr)->delete();
    					// $tmpMap->update(['mappedTo' => $tmp['mappedTo'], 'role' => $tmp['role']]);
    				}
    				$disableEntry['mappedTo'] = '-';
    				// array_push($uidArr, $qa['account']);
    			}else{
    				$tmpMapArr = Acmap::where('startdate', $tmp['startdate']->toDateString())->where('mappedTo', $tmp['account'])->where('role', '!=', 'sub')->get()->pluck('_id');
    				if(isset($tmpMapArr)){
    					Acmap::whereIn('_id', $tmpMapArr)->delete();
    				}
    				$disableEntry['user'] = '-';
    				// array_push($accArr, $qa['account']);
    				// TradeAccount::find($qa['account'])->update(['status'=>0]);
    			}
    			Acmap::create($disableEntry);
    		}
    	}
    	if(sizeof($uidArr)>0)
			User::whereIn('_id', $uidArr)->update(['authorised'=>false]);
    	if(sizeof($accArr)>0)
			TradeAccount::whereIn('accountid', $accArr)->update(['authorised'=>false]);
    }


    # Helper Function
    public function checkIfAccountHasMaster($account, $date, $cfif = false)
	{
		$date = Carbon::parse($date);
		$masterMap = Acmap::whereIn('role', ['master', 'disabled'])->where('mappedTo', $account)->where('startdate', '<=', $date->toDateString())->orderBy('startdate', 'DESC')->first();
		if(!isset($masterMap)){

			if($cfif){
				return 'Assign Master First';
				// exit;
			}
			Session::flash('error', 'You must Assign a master for the selected Account');
            return redirect()->back();
		}elseif(($masterMap->role==='disabled')){
		// dd('disabled');

			if($cfif){
				return 'Account Disabled, Assign Master First';
				exit;
			}
			Session::flash('error', 'Account is Disabled. You must Assign a master for the selected Account');
            return redirect()->back();
		}
		return '0';
	}




	# Helper Function
	public function getUserArray($input, $needCountReturn=false){
        if($input!=null)
            foreach ($input as $key => $value)
                if($value==null)
                    unset($input[$key]);
        $input = array_values($input);
        if($needCountReturn)
        	return sizeof($input);
    	else
        	return $input;
	}


	# Helper Function
	public function checkUserCountRestriction($userArray, $type){
		if($type==='master'){
			if(sizeof($userArray)<1){
				Session::flash('error', 'At least one User must be selected');
            	return redirect()->back();
			}
			if(sizeof($userArray)>1){
				Session::flash('error', 'Master Should be Only One Person');
            	return redirect()->back();
			}
		}
		if($type==='sub'){
			if(sizeof($userArray)<1){
				Session::flash('error', 'At least one User must be selected');
            	return redirect()->back();
			}
		}
	}


	# Helper Function
	public function checkIfAccountDisabled($account, $date='today', $cfif= false)
	{
		// if($date!='today')
		// 	$date = Carbon::parse($date);
		// else
		// 	$date = Carbon::today();
		// $tmp = Acmap::where('startdate', '<=', $date->toDateString())->where('mappedTo', $account)->whereIn('role', ['disabled', 'master'])->orderBy('startdate', 'desc')->first();
		// if(isset($tmp)){
		// 	if($tmp->role==='disabled'){
		// 		Session::flash('error', 'Selected Account is Disabled, please choose another Account');
  		//           	return redirect()->back();
		// 	}
		// }else{
		// 	Session::flash('error', 'You must Assign a Master first for the selected Account');
  		//           return redirect()->back();
		// }
		$account = TradeAccount::where('accountid', $account)->first();
		// if(is_string($effectiveFromDate))
		// 	$effectiveFromDate = Carbon::parse($effectiveFromDate);
		if(!$account->authorised){
			if($cfif){
				echo 'Un-authorised Account';
				exit;
			}
			Session::flash('error', 'Selected Account is not Authorised, please choose another Account');
            return redirect()->back();
		}
	}


	# Helper Function
	public function checkIfUserDisabled($userArray, $date='today', $cfif = false)
	{
		
		$account = User::whereIn('_id', $userArray)->get();
		$flag = false;
		foreach ($account as $key => $value) {
			if(!$value->authorised){
				$flag = true;
				break;
			}
		}
		// if(is_string($effectiveFromDate))
		// 	$effectiveFromDate = Carbon::parse($effectiveFromDate);
		if($flag){			
			if($cfif){
				echo 'Un-authorised User';
				exit;
			}
			Session::flash('error', 'Selected Users contains Un-authorised Users. Please De-select them');
            return redirect()->back();
		}
	}


	# Helper Function
	public function makeArrayFromRequest($request)
	{
		return [[
			'newmems' => $this->getUserArray($request['newmems']),
			'startdate' => $request['startdate'],
			'groupid' => $request['groupid'],
			'role' => $request['role'],
		]];
	}


	# Helper Function
	public function decho($msg)
	{
		if(DEBUG)
			echo($msg);
	}


	# Helper Function
	public function makeQueryArray($retArr, $nodeNo = 'initial')
	{
		if(!array_key_exists(0, $retArr))
			$retArr = [$retArr];
		// dd($retArr);
		$qa = [];
		$ua = [];
		$aa = [];
		$dua = [];
		foreach ($retArr as $key => $value) {
			// if($key==0)
			// 	continue;
			$this->decho($nodeNo.'_'.$key.'_Started<br>');

			// if($nodeNo != 'initial')
				// $this->showProgress($nodeNo+$key, 'Processing Nodes');
			if(isset($value['nested'])){
				if(isset($value['setDisabled']) && $value['setDisabled']==='on'){
					if(isset($value['trFor'])){
						if($value['trFor']==='user'){
							foreach ($value['newmems'] as $valueNewmems) {
								$qa[] = [
									'trType' => 'accountDisable',
									'startdate' => $value['startdate'],
									'accountType' => $value['trFor'],
									'account' => $valueNewmems,
								];
							}
						}else{
							$qa[] = [
								'trType' => 'accountDisable',
								'startdate' => $value['startdate'],
								'accountType' => $value['trFor'],
								'account' => ($value['groupid']),
							];

						}

					}else{
						if(isset($value['groupid'])){
							$qa[] = [
								'trType' => 'accountDisable',
								'startdate' => $value['startdate'],
								'accountType' => 'account',
								'account' => ($value['groupid']),
							];

						}else{
							foreach ($value['newmems'] as $valueNewmems) {
								$qa[] = [
									'trType' => 'accountDisable',
									'startdate' => $value['startdate'],
									'accountType' => 'user',
									'account' => $valueNewmems,
								];
							}
						}
					}

					$recurArray = $this->makeQueryArray($value['nested'], $key);
					// dd($recurArray);
					$qa = array_merge($qa, $recurArray['queryArray']);
					$ua = array_merge($ua, $recurArray['users']);
					$dua = array_merge($dua, $recurArray['dateUsers']);
					$aa = array_merge($aa, $recurArray['accounts']);

				}else{

					$this->decho($nodeNo.'_'.$key.'_Nesting<br>');
					$tempo = [
						'trType' => 'mapping',
						'startdate' => Carbon::parse($value['startdate']),
						'mappedTo' => $value['groupid'],
						'role' => $value['role'],
						'user' => 0,
						'type' => 'log',
					];

					if($value['groupid']!='' && $value['groupid']!=null)
						array_push($aa, $value['groupid']);
					foreach ($value['newmems'] as $valueNewmems) {
						$tempo['user'] = $valueNewmems;
						$qa[] = $tempo;
						if($valueNewmems!='' && $valueNewmems!=null){
							$dua[$tempo['startdate']->toDateString()] = $valueNewmems;
							array_push($ua, $valueNewmems);
						}
					}
					$recurArray = $this->makeQueryArray($value['nested'], $key);
					// dd($recurArray);
					$qa = array_merge($qa, $recurArray['queryArray']);
					$ua = array_merge($ua, $recurArray['users']);
					$dua = array_merge($dua, $recurArray['dateUsers']);
					$aa = array_merge($aa, $recurArray['accounts']);
				}

			}else{
				// echo(json_encode($value));
				if(isset($value['setDisabled']) && $value['setDisabled']==='on'){
					// if($value['trFor']==='user'){
					// 	foreach ($value['newmems'] as $valueNewmems) {
					// 		$qa[] = [
					// 			'trType' => 'accountDisable',
					// 			'startdate' => $value['startdate'],
					// 			'accountType' => $value['trFor'],
					// 			'account' => $valueNewmems,
					// 		];
					// 	}
					// }else{
					// 	$qa[] = [
					// 		'trType' => 'accountDisable',
					// 		'startdate' => $value['startdate'],
					// 		'accountType' => $value['trFor'],
					// 		'account' => ($value['groupid']),
					// 	];

					// }

					if(isset($value['trFor'])){
						if($value['trFor']==='user'){
							foreach ($value['newmems'] as $valueNewmems) {
								$qa[] = [
									'trType' => 'accountDisable',
									'startdate' => $value['startdate'],
									'accountType' => $value['trFor'],
									'account' => $valueNewmems,
								];
							}
						}else{
							$qa[] = [
								'trType' => 'accountDisable',
								'startdate' => $value['startdate'],
								'accountType' => $value['trFor'],
								'account' => ($value['groupid']),
							];

						}

					}else{
						if(isset($value['groupid'])){
							$qa[] = [
								'trType' => 'accountDisable',
								'startdate' => $value['startdate'],
								'accountType' => 'account',
								'account' => ($value['groupid']),
							];

						}else{
							foreach ($value['newmems'] as $valueNewmems) {
								$qa[] = [
									'trType' => 'accountDisable',
									'startdate' => $value['startdate'],
									'accountType' => 'user',
									'account' => $valueNewmems,
								];
							}
						}
					}

				}else{
					if(!isset($value['startdate'])){
						$this->decho('Ghost Problem Occurred Again');
						dd($value);
					}
					$tempo = [
						'trType' => 'mapping',
						'startdate' => Carbon::parse($value['startdate']),
						'mappedTo' => $value['groupid'],
						'role' => $value['role'],
						'user' => 0,
						'type' => 'log',
					];
					if($value['groupid']!='' && $value['groupid']!=null)
						array_push($aa, $value['groupid']);
					foreach ($value['newmems'] as $valueNewmems) {
						$tempo['user'] = $valueNewmems;
						$qa[] = $tempo;
						if($valueNewmems!='' && $valueNewmems!=null){
							$dua[$tempo['startdate']->toDateString()] = $valueNewmems;
							array_push($ua, $valueNewmems);
						}
					}
				}
				$this->decho($nodeNo.'_'.$key.'_End Node Found<br>');
			}
			$this->decho("$key is Complete<br>");
		}
		$datesAA = (array_unique(array_keys($dua)));
		return ['queryArray' => $qa, 'dateUsers' => ($dua), 'dates' => $datesAA, 'users' => array_unique($ua), 'accounts' => array_unique($aa)];
	}



	# Helper Function
	public function verifyFromCurrentTransaction($acmap, $currentTR, $trType, $effectiveFromDate = 'today')
	{
		global $nodeFlow;

		if($effectiveFromDate!='today')
			$effectiveFromDate = Carbon::parse($effectiveFromDate);
		else	
			$effectiveFromDate = Carbon::today();
		

		$exceptId = 0.1;

		if($nodeFlow){
			foreach ($currentTR as $key => $value) {
				$aCm = '';

				if(isset($acmap['groupid']))
					$aCm = $acmap['groupid'];
				if(isset($acmap['mappedTo']))
					$aCm = $acmap['mappedTo'];

				$usrM = '';

				if(isset($acmap['newmems']))
					$usrM = $acmap['newmems'];
				if(isset($acmap['user']))
					$usrM = [$acmap['user']];
			// dd($usrM);
				if(
					strtolower($value['role'])===strtolower($acmap['role']) && 
					$value['mappedTo']===$aCm && 
					sizeof(array_intersect($usrM, $value['user']))===sizeof($usrM) && 
					sizeof(array_intersect($usrM, $value['user']))===sizeof($value['user']) && 
					Carbon::parse($value['startdate'])==Carbon::parse($acmap['startdate'])
				){
					$exceptId = $key;
					break;
				}
			}
		}

		$flag = false;
		foreach ($currentTR as $key => $value) {
			if($key!=$exceptId && $value['status']!='loading'){

				switch ($trType) {
					case 'master':
						$acmapMappedTo = '';

						if(isset($acmap['groupid']))
							$acmapMappedTo = $acmap['groupid'];
						if(isset($acmap['mappedTo']))
							$acmapMappedTo = $acmap['mappedTo'];

						if($acmapMappedTo===$value['mappedTo'] && $value['role']==='master' && Carbon::parse($value['startdate'])>=Carbon::parse($acmap['startdate']) && Carbon::parse($value['startdate'])<=$effectiveFromDate){
							$flag = true;
						}
					// dd([$flag, $effectiveFromDate, $acmap, $currentTR]);
						break;

					case 'user':
						// dd('User');
						if(in_array($acmap['user'], $value['user']) && Carbon::parse($value['startdate'])>=Carbon::parse($acmap['startdate'])&& Carbon::parse($value['startdate'])<=$effectiveFromDate){
							$flag = true;
						}
						break;
					case 'futureuser':
						// dd('FU');
						if(in_array($acmap['user'], $value['user']) && Carbon::parse($value['startdate'])<=Carbon::parse($acmap['startdate'])&& Carbon::parse($value['startdate'])>$effectiveFromDate){
							$flag = true;
						}
						break;
					
					case 'futuremaster':
						if($acmap['mappedTo']===$value['mappedTo'] && Carbon::parse($value['startdate'])<=Carbon::parse($acmap['startdate'])&& Carbon::parse($value['startdate'])>$effectiveFromDate){
							$flag = true;
						}
						break;
					
					case 'disable_account':
						$acmapMappedTo = '';

						if(isset($acmap['groupid']))
							$acmapMappedTo = $acmap['groupid'];
						if(isset($acmap['mappedTo']))
							$acmapMappedTo = $acmap['mappedTo'];
						if($acmapMappedTo===$value['mappedTo'] && (strpos($value['role'], 'disable')!==false) && Carbon::parse($value['startdate'])>=Carbon::parse($acmap['startdate']) && Carbon::parse($value['startdate'])<=$effectiveFromDate){
							$flag = true;
						}
						break;
					
					case 'disable_future_account':
						if($acmap['mappedTo']===$value['mappedTo'] && Carbon::parse($value['startdate'])<=Carbon::parse($acmap['startdate'])&& Carbon::parse($value['startdate'])>$effectiveFromDate){
							$flag = true;
						}
						break;			
					
					default:
						// code...
						break;
				}
			// dd($flag);

			}
			if($flag)
				break;
		}
		return ['status'=> $flag, 'trDetails'=>$currentTR];
	}




	# Helper Function
	public function getMappingByDate($date = 'today', $type = 'active', $isForCheck = 'NA', $withoutFuture = false, $withExecutorAndEffectedUser = false)
    {
		if($date === 'today')
			$date = Carbon::today()->toDateString();
    	else
			$date = Carbon::parse($date)->toDateString();
		
		// $userIds = user::where('authorised', 1)
		// ->get()
		// ->pluck('_id');

		// $users = user::where('authorised', 1)->get();
		// $accIds = TradeAccount::where('status', 1)->get()->pluck('accountid');
		
		// $currentArr = [];
		$currentArrId = [];
		// $currentUsrArrId = [];
		// $currentAccArrId = [];

		$acmapForAccMasterAndDisabled = Acmap::where('startdate', '<=', $date)->where('role', '!=', 'sub')->get()->pluck('_id')->toArray();
		// $acmapForAccDisabled = Acmap::whereIn('role', ['disabled'])->where('user', "-")->get()->pluck('_id')->toArray();
		// dd($acmapForAccMaster);
		// $acmapForAccMasterAndDisabled = array_merge($acmapForAccMaster, $acmapForAccDisabled);

		$acmaps = Acmap::whereIn('_id', $acmapForAccMasterAndDisabled)
			->orderBy('mappedTo', 'ASC')
			->orderBy('startdate', 'DESC')
			->get();
		$acmaps = $acmaps->groupBy(function($item) {
			return $item->mappedTo;
		});
		// dd($acmaps->toArray());
		foreach ($acmaps as $key => $acmap) {
			if(sizeof($acmap)>0){
				array_push($currentArrId, $acmap[0]['_id']);
				// array_push($currentArr, $acmap[0]);
				// array_push($currentUsrArrId, $acmap[0]['user']);
				// if($isForCheck!='NA'){
				// 	array_push($currentUsrArrId, $acmap[0]['user']);
				// 	array_push($currentAccArrId, $acmap[0]['mappedTo']);
					
				// }
			}
		}

		$acmapMasterUseIds = Acmap::whereIn('_id', $currentArrId)->get()->pluck('user')->toArray();
		// $acmapMasterUseIds[] = '-';
		$acmaps = Acmap::where('startdate', '<=', $date)->whereNotIn('_id', $currentArrId)->where('role', '!=', 'master')->whereNotIn('user', $acmapMasterUseIds)->orderBy('user', 'ASC')
			->orderBy('startdate', 'DESC')
			->get();
		$acmaps = $acmaps->groupBy(function($item) {
			return $item->user;
		});

		foreach ($acmaps as $key => $acmap) {
			if(sizeof($acmap)>0){
				array_push($currentArrId, $acmap[0]['_id']);
				// array_push($currentArr, $acmap[0]);
				// array_push($currentUsrArrId, $acmap[0]['user']);
				// if($isForCheck!='NA'){
				// 	array_push($currentUsrArrId, $acmap[0]['user']);
				// 	array_push($currentAccArrId, $acmap[0]['mappedTo']);
					
				// }
			}
		}

		if(!$withoutFuture){
			$acMapTmp = Acmap::where('startdate', '>', $date)->get()->pluck('_id')->toArray();
			$currentArrId = array_merge($acMapTmp, $currentArrId);
		}

		if($type==='active'){
			// dd(Acmap::whereIn('_id', $currentArrId)->orderBy('startdate', 'desc')->get()->pluck('startdate')->toArray());
			// $tmp = Acmap::whereIn('_id', $currentArrId)->orderBy('startdate', 'desc')->get();
			// foreach ($tmp as $key => $value) {
			// 	$tmp[$key]->mappedTo .='@';
			// }
			// return $tmp;
			if ($withExecutorAndEffectedUser) {
				return Acmap::whereIn('_id', $currentArrId)->with([
					'executor' => function ($query) {
						$query->select('memberId');
					},
					'effectedUser' => function ($query) {
						$query->select('memberId');
					}
				])->orderBy('startdate', 'desc')->get();

			} else {
				return Acmap::whereIn('_id', $currentArrId)->orderBy('startdate', 'desc')->get();

			}
		}else{
			if ($withExecutorAndEffectedUser) {
				return Acmap::whereNotIn('_id', $currentArrId)->with([
					'executor' => function ($query) {
						$query->select('memberId');
					},
					'effectedUser' => function ($query) {
						$query->select('memberId');
					}
				])->orderBy('startdate', 'desc')->get();

			} else {
				return Acmap::whereNotIn('_id', $currentArrId)->orderBy('startdate', 'desc')->get();

			}
		}

		foreach ($users as $key => $user) {
			// dd($user);
			if($user->getMapData($date)!=null && $user->tradeMode($date)=='sub'){
				// dd($user->getMapData($date));
				// array_push($currentArr, $user->getMapData($date));
				array_push($currentArrId, $user->getMapData($date)->_id);
				if($isForCheck!='NA'){
					array_push($currentUsrArrId, $user->getMapData($date)->user);
					array_push($currentAccArrId, $user->getMapData($date)->mappedTo);
					
				}
			}
		}
		
		
		
		$accMasterMapping = Acmap::whereIn('mappedTo', $accIds)
		->where('startdate', '<=', $date->toDateString())
		->where('role', 'master')
		->orderBy('mappedTo', 'ASC')
		->orderBy('startdate', 'DESC')
		->get();
		// dd($accMasterMapping);
		$accMasterMapping = $accMasterMapping->groupBy(function($item) {
			return $item->mappedTo;
		});
		
		// $currentArr = [];
		
		foreach ($accMasterMapping as $key => $value) {
			if(sizeof($value)>0){
				// array_push($currentArr, $value[0]);
				array_push($currentArrId, $value[0]['_id']);
				// array_push($currentUsrArrId, $value[0]['user']);
				if($isForCheck!='NA'){
					array_push($currentUsrArrId, $value[0]['user']);
					array_push($currentAccArrId, $value[0]['mappedTo']);
					
				}
			}
		}
		if($type=='active'){
			// $sorted = $currentArr->sortByDesc(function ($element) {
			// 	return $element->data ? $element->data->created_at : $element->created_at;
			// });
			// $sorted = collect($currentArr)->sortByDesc(function ($obj, $key) {
			// 	return Carbon::parse($obj->startdate);
			// });
			if($isForCheck!='NA'){
				$tempArr = explode('_##_', $isForCheck);
				if($tempArr[0]=='usr')
					return in_array($tempArr[1], $currentUsrArrId);
				else
					return in_array($tempArr[1], $currentAccArrId);
			}else{
				if ($withExecutorAndEffectedUser) {
					return Acmap::whereIn('_id', $currentArrId)
						->with('executor', 'effectedUser')
						->orderBy('created_at', 'desc')
						->get();

				} else {
					return Acmap::whereIn('_id', $currentArrId)
						->orderBy('created_at', 'desc')
						->get();
				}
			}
			// return $sorted;
		}else{
			if($isForCheck!='NA'){
				$tempArr = explode('_##_', $isForCheck);
				if($tempArr[0]=='usr')
					return in_array($tempArr[1], $currentUsrArrId);
				else
					return in_array($tempArr[1], $currentAccArrId);
			}else{
				if ($withExecutorAndEffectedUser) {
					return Acmap::whereNotIn('_id', $currentArrId)
						->with('executor', 'effectedUser')
						->orderBy('created_at', 'DESC')
						->get();

				} else {
					return Acmap::whereNotIn('_id', $currentArrId)
						->orderBy('created_at', 'DESC')->get();

				}
			}
		}
		
		// $users = User::whereNotIn('user', $currentUsrArrId)->get();
		
		
    	// $mapping = Acmap::whereIn('user', $userIds)
		// ->where('startdate', '<=', $date->toDateString())
		// ->orderBy('user', 'ASC')
		// ->orderBy('startdate', 'DESC')
		// ->get();
		
    	// $mapping = $mapping->groupBy(function($item) {
		// 	return $item->user;
		// });

		// $currentArr = [];
		// $currentArrId = [];

		// foreach ($mapping as $key => $value) {
		// 	if(sizeof($value)>0){
		// 		array_push($currentArr, $value[0]);
		// 		array_push($currentArrId, $value[0]['_id']);
		// 	}
		// }
		// if($type=='active'){
		// 	return $currentArr;
		// }else{
		// 	return Acmap::whereNotIn('_id', $currentArrId)
    	// 	->orderBy('created_at', 'DESC')->get();
		// }
    }


    # Helper Function
    public function checkForOpenEnd($retArr)
	{
		$flag = 0;
		foreach ($retArr as $key => $value) {
			// echo "$key relooping<br>";
			if(!isset($value['nested'])){
				// echo '	Found last node<br>';
				if(isset($value['setDisabled']) && $value['setDisabled']==='on'){
					if(isset($value['nested'])){
						// $flag +=
						$flag += $this->checkForOpenEnd($value['nested']);
					}
					// echo '	Setting Account Disabled<br>';
					// if($value['setDisabled']=='on'){
						// $tmpFlag = 0;
						// foreach ($value as $key1 => $value1) {
						// 	if($value1=='' || $value1==null){
						// 		$tmpFlag = 1;
						// 		break;
						// 	}
						// }return $tmpFlag;
						$flag += 0;
						// break;
					// }else{
						// return 1;
					// }
				}else{
					// echo($value['triggerFor'].'<br>');
					// exit;
					// if($value['setDisabled']==0){
						// $tmpFlag = 0;
						foreach ($value as $key1 => $value1) {
							if(is_array($value1)){
								if(sizeof($value1)==0){
									$flag ++;
									break;
								}
							}else if($value1==='' || $value1===null){
								$flag ++;
								break;
							}
						}

						// $tmpFlag;

					// }else{
						// return false;
					// }
				}
				// echo "____Flag $flag<br>";
			}else{
				// echo 'Recalling...<br>';
				// echo "Flag $flag<br>";
				$flag += $this->checkForOpenEnd($value['nested']);
				// echo '____________________________';
			}
		}
		return $flag;
	}


	public function compileDisableMapping($retArr){
		global $currentTR;
		// $reAttentionFlag = 0;
		if(!array_key_exists(0, $retArr))
			$retArr = [$retArr];
		foreach ($retArr as $entryIndex => $entryValue) {
			if(isset($entryValue['nested'])){
				foreach ($retArr[$entryIndex]['nested'] as $keyNested => $valueNested) {
					// code...
					if(valueNested['role']==='sub')
						$retArr[$entryIndex]['nested'][$keyNested] = $this->compileSubMapping([$retArr[$entryIndex]['nested'][$keyNested]]);
					if(valueNested['role']==='master')
						$retArr[$entryIndex]['nested'][$keyNested] = $this->compileMasterMapping([$retArr[$entryIndex]['nested'][$keyNested]]);
					if(valueNested['role']==='disable')
						$retArr[$entryIndex]['nested'][$keyNested] = $this->compileDisableMapping([$retArr[$entryIndex]['nested'][$keyNested]]);
				}
			}
			else{
				if(isset($entryValue['setDisabled']) && $entryValue['setDisabled']==='on'){
					$effectiveFromDate = $entryValue['startdate'];
					if(is_string($effectiveFromDate))
						$effectiveFromDate = Carbon::parse($effectiveFromDate);
					if(isset($entryValue['groupid'])){
		    			// $retArr = [];
		    			##This is copied from Master Mapping
						if(isset($entryValue['mappedUserAction'])){
							if($entryValue['mappedUserAction']==='da'){
								$activeusers = $this->getMappingByDate($entryValue['startdate'], 'active', 'NA', true);
								
								foreach ($activeusers as $key => $au) {
									if($au->mappedTo===$entryValue['groupid']){
										$retArr[$entryIndex]['nested'][] = [
											'triggerFor' => User::find($au->user)->name.' is Sub of '.$entryValue['groupid'],
											'newmems' => [$au->user],
											'startdate' => Carbon::parse($entryValue['startdate'])->toDateString(),
											'role' => '',
											'groupid' => '',
											'trFor' => 'user',
											'setDisabled' => 'on',
										];	
									}
								}
							}
							if($entryValue['mappedUserAction']==='mta'){
								$activeusers = $this->getMappingByDate($entryValue['startdate'], 'active', 'NA', true);
								$subUsersID = [];
								foreach ($activeusers as $key => $au) {
									if($au->mappedTo===$entryValue['groupid']){
										$subUsersID[] = $au->user;
									}
								}
								// dd($activeusers->toArray());

								if(sizeof($subUsersID)>0)
									$retArr[$entryIndex]['nested'][] = [
										'triggerFor' => 'For all Sub assigned to '.$entryValue['groupid'],
										'newmems' => $subUsersID,
										'startdate' => Carbon::parse($entryValue['startdate'])->toDateString(),
										'role' => 'sub',
										'groupid' => '',
										'trFor' => 'user',
										'setDisabled' => 0,
									];	
							}
							if($entryValue['mappedUserAction']==='mi'){
								$activeusers = $this->getMappingByDate($entryValue['startdate'], 'active', 'NA', true);
								// dd($activeusers->toArray());
								foreach ($activeusers as $key => $au) {
									if($au->mappedTo===$entryValue['groupid']){
										if(!$this->verifyFromCurrentTransaction($au->toArray(), $currentTR, 'user', $effectiveFromDate)['status'])
											$retArr[$entryIndex]['nested'][] = [
												'triggerFor' => User::find($au->user)->name.' is Sub of '.$entryValue['groupid'],
												'newmems' => [$au->user],
												'startdate' => Carbon::parse($entryValue['startdate'])->toDateString(),
												'role' => '',
												'groupid' => '',
												'trFor' => 'user',
												'setDisabled' => 0,
											];	
									}
								}
							}

						}

		    		}else{
		    			##This is copied from Sub Mapping
		    			$activeusers = $this->getMappingByDate($entryValue['startdate'], 'active', 'NA', true);
						foreach ($entryValue['newmems'] as $userId) {
							foreach ($activeusers as $key => $au) {
								if($au->user===$userId && $au->role==='master'){
									if(!$this->verifyFromCurrentTransaction($au->toArray(), $currentTR, 'master', $effectiveFromDate)['status'] && !$this->verifyFromCurrentTransaction($au->toArray(), $currentTR, 'disable_account', $effectiveFromDate)['status'])
										$retArr[$entryIndex]['nested'][] = [
											'triggerFor' => User::find($au->user)->name.' is Master of '.$au->mappedTo,
											'newmems' => [],
											'startdate' => Carbon::parse($entryValue['startdate'])->toDateString(),
											'role' => 'master',
											'groupid' => $au->mappedTo,
											'trFor' => 'account',
											'setDisabled' => 0,
										];	
								}
							}
						}
		    		}
				}
			}
		}
		return $retArr;
	}


	# Helper Function
	public function compileSubMapping($retArr){
		global $currentTR;
		// $reAttentionFlag = 0;
		if(!array_key_exists(0, $retArr))
			$retArr = [$retArr];
		foreach ($retArr as $entryIndex => $entryValue) {
			if(isset($entryValue['nested']))
				$retArr[$entryIndex]['nested'] = $this->compileSubMapping($retArr[$entryIndex]['nested']);
			else{
				$setDisabled = 'off';
				if(isset($entryValue['setDisabled']))
					$setDisabled = $entryValue['setDisabled'];
				if($setDisabled!='on' && isset($entryValue['groupid']) && $entryValue['groupid']!='' && isset($entryValue['newmems']) && sizeof($entryValue['newmems'])>0){
					// if()
					$effectiveFromDate = $entryValue['startdate'];
					if(is_string($effectiveFromDate))
						$effectiveFromDate = Carbon::parse($effectiveFromDate);
					
					if($entryValue['role']==='sub' && sizeof($entryValue['newmems'])>0){
		// dd('hik');
						foreach($entryValue['newmems'] as $mUser){
							$mUser = User::find($mUser);

							## Parent Account Based Query
							$previousMapping = Acmap::where('user', $mUser->_id)->where('startdate', '<=', $effectiveFromDate->toDateString())->orderBy('startdate', 'DESC')->first();


							// if($this->getMappingByDate($effectiveFromDate->toDateString(), 'active', 'usr_##_'.$mUser->_id) && isset($previousMapping) && $previousMapping->role==='master'){
							if(isset($previousMapping) && $previousMapping->role==='master'){
								if(!$this->verifyFromCurrentTransaction($previousMapping->toArray(), $currentTR, 'master', $effectiveFromDate)['status'] && !$this->verifyFromCurrentTransaction($previousMapping->toArray(), $currentTR, 'disable_account', $effectiveFromDate)['status']){
									$previousMaster = User::find($previousMapping->user);
									
									// if(!in_array($previousMaster->_id, $entryValue['newmems'])){
										$retArr[$entryIndex]['nested'][] = [
											// 'triggerFor' => $mUser->name.' is assigned as Master for '. $previousMapping->mappedTo,
											'triggerFor' => $previousMapping->mappedTo.' is mapped with '.$mUser->name.' as Master',

											'newmems' => [],
											'startdate' => $effectiveFromDate,
											'role' => 'master',
											'groupid' => $previousMapping->mappedTo,
											'trFor' => 'account',
											'setDisabled' => 0,
										];
										// $reAttentionFlag++;
									// }
								}

							}
						}

						$accChecker = Acmap::where('mappedTo', $entryValue['groupid'])->whereIn('role', ['disabled','master'])->where('startdate', '<=', $effectiveFromDate->toDateString())->orderBy('startdate', 'DESC')->first();
						if(isset($accChecker)){
							if($accChecker->role==='disabled' && !$this->verifyFromCurrentTransaction($entryValue, $currentTR, 'master', $effectiveFromDate)['status'] && !$this->verifyFromCurrentTransaction($entryValue, $currentTR, 'disable_account', $effectiveFromDate)['status']){
								$retArr[$entryIndex]['nested'][] = [
									// 'triggerFor' => $mUser->name.' is assigned as Master for '. $previousMapping->mappedTo,
									'triggerFor' => $entryValue['groupid'].' doesn\'t have Master',

									'newmems' => [],
									'startdate' => $entryValue['startdate'],
									'role' => 'master',
									'groupid' => $entryValue['groupid'],
									'trFor' => 'account',
									'setDisabled' => 0,
								];
							}
						}else if(!$this->verifyFromCurrentTransaction($entryValue, $currentTR, 'master', $effectiveFromDate)['status'] && !$this->verifyFromCurrentTransaction($entryValue, $currentTR, 'disable_account', $effectiveFromDate)['status']){
							$retArr[$entryIndex]['nested'][] = [
								// 'triggerFor' => $mUser->name.' is assigned as Master for '. $previousMapping->mappedTo,
								'triggerFor' => $entryValue['groupid'].' doesn\'t have Master',

								'newmems' => [],
								'startdate' => $entryValue['startdate'],
								'role' => 'master',
								'groupid' => $entryValue['groupid'],
								'trFor' => 'account',
								'setDisabled' => 0,
							];
						}

					}else{
						// if($entryValue['groupid']==='CTS059')
							// dd($entryValue);
						$retArr[$entryIndex] = $this->compileMasterMapping([$entryValue])[0];
					}

				}

				if($setDisabled==='on'){
					if(isset($entryValue['mappedUserAction'])){
						if($entryValue['mappedUserAction']==='da'){
							$activeusers = $this->getMappingByDate($entryValue['startdate'], 'active', 'NA', true);
							
							foreach ($activeusers as $key => $au) {
								if($au->mappedTo===$entryValue['groupid'] && $au->role==='sub'){
									$retArr[$entryIndex]['nested'][] = [
										'triggerFor' => User::find($au->user)->name.' is Sub of '.$entryValue['groupid'],
										'newmems' => [$au->user],
										'startdate' => Carbon::parse($entryValue['startdate'])->toDateString(),
										'role' => '',
										'groupid' => '',
										'trFor' => 'user',
										'setDisabled' => 'on',
									];	
								}
							}
						}
						if($entryValue['mappedUserAction']==='mta'){
							$activeusers = $this->getMappingByDate($entryValue['startdate'], 'active', 'NA', true);
							$subUsersID = [];
							foreach ($activeusers as $key => $au) {
								if($au->mappedTo===$entryValue['groupid'] && $au->role==='sub'){
									$subUsersID[] = $au->user;
								}
							}
							// dd($activeusers->toArray());

							if(sizeof($subUsersID)>0)
								$retArr[$entryIndex]['nested'][] = [
									'triggerFor' => 'For all Sub assigned to '.$entryValue['groupid'],
									'newmems' => $subUsersID,
									'startdate' => Carbon::parse($entryValue['startdate'])->toDateString(),
									'role' => 'sub',
									'groupid' => '',
									'trFor' => 'user',
									'setDisabled' => 0,
								];	
						}
						if($entryValue['mappedUserAction']==='mi'){
							$activeusers = $this->getMappingByDate($entryValue['startdate'], 'active', 'NA', true);
							
							foreach ($activeusers as $key => $au) {
								if($au->mappedTo===$entryValue['groupid'] && $au->role==='sub'){
									$retArr[$entryIndex]['nested'][] = [
										'triggerFor' => User::find($au->user)->name.' is Sub of '.$entryValue['groupid'],
										'newmems' => [$au->user],
										'startdate' => Carbon::parse($entryValue['startdate'])->toDateString(),
										'role' => '',
										'groupid' => '',
										'trFor' => 'user',
										'setDisabled' => 0,
									];	
								}
							}
						}

					}
				}
			}
		}
		return $retArr;
	}


	# Helper Function
	public function compileMasterMapping($retArr, $quaArr = null)
	{
		global $currentTR;
		// dd($retArr);

		// $reAttentionFlag = 0;
		if($quaArr==null)
			$quaArr = $this->makeQueryArray($retArr);
		if(!array_key_exists(0, $retArr))
			$retArr = [$retArr];
		// dd($retArr);
		foreach ($retArr as $entryIndex => $entryValue) {
			if(isset($entryValue['nested']))
				$retArr[$entryIndex]['nested'] = $this->compileMasterMapping($retArr[$entryIndex]['nested'], $quaArr);
			else{
				$setDisabled = 'off';
				if(isset($entryValue['setDisabled'])){
					// dd($entryValue['setDisabled']);
					$setDisabled = $entryValue['setDisabled'];
				}

				if($entryValue['role']==='sub' && sizeof($entryValue['newmems'])>0){
					$retArr[$entryIndex] = $this->compileSubMapping([$entryValue])[0];
				}elseif($setDisabled!='on' && isset($entryValue['groupid']) && $entryValue['groupid']!='' && isset($entryValue['newmems']) && sizeof($entryValue['newmems'])>0){
					// if()
					$effectiveFromDate = $entryValue['startdate'];
					if(is_string($effectiveFromDate))
						$effectiveFromDate = Carbon::parse($effectiveFromDate);
					
					// if(Acmap::where('mappedTo', $entryValue['groupid'])->count()>0){
					// dd('loi');

						## Parent Account Based Query
						$previousMapping = Acmap::where('mappedTo', $entryValue['groupid'])->whereIn('role', ['disabled', $entryValue['role']])->where('startdate', '<=', $effectiveFromDate->toDateString())->orderBy('startdate', 'DESC')->first();
						
						// if($this->getMappingByDate($effectiveFromDate->toDateString(), 'active', 'acc_##_'.$entryValue['groupid']) && isset($previousMapping) && $previousMapping->role===$entryValue['role']){
						if(isset($previousMapping) && $previousMapping->role===$entryValue['role']){
							
							$previousMaster = User::find($previousMapping->user);
							
							if(!in_array($previousMaster->_id, $entryValue['newmems'])){
								// dd($previousMapping);
								if(!in_array($previousMaster->_id, $quaArr['users']) && !$this->verifyFromCurrentTransaction($previousMapping->toArray(), $currentTR, 'user', $effectiveFromDate)['status'])
									$retArr[$entryIndex]['nested'][] = [
										'triggerFor' => $previousMaster->name.' is Master of '.$entryValue['groupid'],
										'newmems' => [$previousMaster->_id],
										'startdate' => $effectiveFromDate,
										'role' => '',
										'groupid' => '',
										'trFor' => 'user',
										'setDisabled' => 0,
									];
								// $reAttentionFlag++;
							}

						}


						$futureMapping = Acmap::where('mappedTo', $entryValue['groupid'])->where('startdate', '>', $effectiveFromDate->toDateString())->orderBy('startdate', 'ASC')->first();
						if(isset($futureMapping) && $futureMapping->role===$entryValue['role']){
							$futureMaster = User::find($futureMapping->user);
							
							if(!in_array($futureMaster->_id, $entryValue['newmems'])){
								if(!in_array($entryValue['newmems'][0], $quaArr['users']) && !$this->verifyFromCurrentTransaction($futureMapping->toArray(), $currentTR, 'futureuser', $effectiveFromDate)['status'])
									$retArr[$entryIndex]['nested'][] = [
										'triggerFor' => User::find($entryValue['newmems'][0])->name.' will loose its master in future',
										'newmems' => [User::find($entryValue['newmems'][0])->_id],
										'startdate' => Carbon::parse($futureMapping->startdate),
										'role' => '',
										'groupid' => '',
										'trFor' => 'user',
										'setDisabled' => 0,
									];
								// $reAttentionFlag++;
							}
						}

						######################################

						## Parent User Based Query

						$previousMapping = Acmap::where('user', $entryValue['newmems'][0])->where('startdate', '<=', $effectiveFromDate->toDateString())->orderBy('startdate', 'DESC')->first();
						// if($this->getMappingByDate($effectiveFromDate->toDateString(), 'active', 'usr_##_'.$entryValue['newmems'][0]) && isset($previousMapping) && $previousMapping->role===$entryValue['role']){
						if(isset($previousMapping) && $previousMapping->role===$entryValue['role']){
							// $previousMaster = User::find($previousMapping->user);
							// dd($previousMapping);
							if($previousMapping->mappedTo!=$entryValue['groupid']){
								if(!in_array($previousMapping->mappedTo, $quaArr['accounts']) && !$this->verifyFromCurrentTransaction($previousMapping->toArray(), $currentTR, 'master', $effectiveFromDate)['status'] && !$this->verifyFromCurrentTransaction($previousMapping->toArray(), $currentTR, 'disable_account', $effectiveFromDate)['status'])
									$retArr[$entryIndex]['nested'][] = [
										'triggerFor' => $previousMapping->mappedTo.' is mapped with '.User::find($entryValue['newmems'][0])->name.' as Master',
										'newmems' => [],
										'startdate' => $effectiveFromDate,
										'role' => 'master',
										'groupid' => $previousMapping->mappedTo,
										'trFor' => 'account',
										'setDisabled' => 0,
									];
								// $reAttentionFlag++;
							}

						}

						$futureMapping = Acmap::where('user', $entryValue['newmems'][0])->where('startdate', '>', $effectiveFromDate->toDateString())->orderBy('startdate', 'ASC')->first();
						if(isset($futureMapping) && $futureMapping->mappedTo!=$entryValue['groupid']){
							// $futureMaster = User::find($futureMapping->user);
							
							// if(!in_array($futureMaster->_id, $entryValue['newmems']){
							// dd($futureMapping);
							// if(!in_array($entryValue['groupid'], $quaArr['accounts']))
							if(!$this->verifyFromCurrentTransaction($futureMapping->toArray(), $currentTR, 'futuremaster', $effectiveFromDate)['status'])
								$retArr[$entryIndex]['nested'][] = [
									'triggerFor' => $entryValue['groupid'].' will loose its Master User in Future',
									'newmems' => [],
									'startdate' => Carbon::parse($futureMapping->startdate),
									'role' => 'master',
									'groupid' => $entryValue['groupid'],
									'trFor' => 'account',
									'setDisabled' => 0,
								];
								// $reAttentionFlag++;
							// }
						}

						######################################
					// }

				}

				if($setDisabled==='on' && $entryValue['role']!='sub'){
					if(isset($entryValue['mappedUserAction'])){
						if($entryValue['mappedUserAction']==='da'){
							$activeusers = $this->getMappingByDate($entryValue['startdate'], 'active', 'NA', true);
							
							foreach ($activeusers as $key => $au) {
								if($au->mappedTo===$entryValue['groupid'] && $au->role==='sub'){
									$retArr[$entryIndex]['nested'][] = [
										'triggerFor' => User::find($au->user)->name.' is Sub of '.$entryValue['groupid'],
										'newmems' => [$au->user],
										'startdate' => Carbon::parse($entryValue['startdate'])->toDateString(),
										'role' => '',
										'groupid' => '',
										'trFor' => 'user',
										'setDisabled' => 'on',
									];	
								}
							}
						}
						if($entryValue['mappedUserAction']==='mta'){
							$activeusers = $this->getMappingByDate($entryValue['startdate'], 'active', 'NA', true);
							$subUsersID = [];
							foreach ($activeusers as $key => $au) {
								if($au->mappedTo===$entryValue['groupid'] && $au->role==='sub'){
									$subUsersID[] = $au->user;
								}
							}
							// dd($activeusers->toArray());

							if(sizeof($subUsersID)>0)
								$retArr[$entryIndex]['nested'][] = [
									'triggerFor' => 'For all Sub assigned to '.$entryValue['groupid'],
									'newmems' => $subUsersID,
									'startdate' => Carbon::parse($entryValue['startdate'])->toDateString(),
									'role' => 'sub',
									'groupid' => '',
									'trFor' => 'user',
									'setDisabled' => 0,
								];	
						}
						if($entryValue['mappedUserAction']==='mi'){
							$activeusers = $this->getMappingByDate($entryValue['startdate'], 'active', 'NA', true);
							
							foreach ($activeusers as $key => $au) {
								if($au->mappedTo===$entryValue['groupid'] && $au->role==='sub'){
									$retArr[$entryIndex]['nested'][] = [
										'triggerFor' => User::find($au->user)->name.' is Sub of '.$entryValue['groupid'],
										'newmems' => [$au->user],
										'startdate' => Carbon::parse($entryValue['startdate'])->toDateString(),
										'role' => '',
										'groupid' => '',
										'trFor' => 'user',
										'setDisabled' => 0,
									];	
								}
							}
						}

					}
				}
			}
		}
		return $retArr;
	}


	# Helper Function
	public function mapFormRequestToArray($request)
    {
        $data = $request->all();
        unset($data['_token']);
        unset($data['_method']);
        $retArr = [];
        foreach ($data as $key => $value) {
            $tmp = explode('_', $key);
            if(in_array('newmems', $tmp)){
            	$tmp1 = $value;
            	foreach ($tmp1 as $key11=>$value11) {
            		if($value11==null)
            			unset($tmp1[$key11]);
            	}
            	$tmp1 = array_values($tmp1);
            	$retArr = $this->insert_using_keys($retArr, $tmp, $tmp1);
            }else
            $retArr = $this->insert_using_keys($retArr, $tmp, $value);
        }
        return $retArr;
    }




    # Helper Function
    public function getAllTr($retArr)
	{
		$curr = [];
		foreach ($retArr as $key => $value) {
			if(isset($value['setDisabled']) && $value['setDisabled']==='on'){
				// $curr[] = [
				// 	'id' => '',
				// 	'startdate' => $value['startdate'],
				// 	// 'user' => $value['newmems'],
				// 	// 'mappedTo' => $value['groupid'],
				// 	// 'role' => $value['role'],
				// 	// 'status' => '',
				// ];
				if(isset($value['nested']))
					$curr = array_merge($curr, $this->getAllTr($value['nested']));
			}else if(isset($value['nested'])){
					$curr[] = [
						'_id' => '',
						'startdate' => $value['startdate'],
						'user' => $value['newmems'],
						'mappedTo' => $value['groupid'],
						'role' => $value['role'],
						'status' => '',
					];
					$curr = array_merge($curr, $this->getAllTr($value['nested']));

			}else{
				$curr[] = [
					'_id' => '',
					'startdate' => $value['startdate'],
					'user' => $value['newmems'],
					'mappedTo' => $value['groupid'],
					'role' => $value['role'],
					'status' => '',
				];
			}
		}
		return $curr;
	}


	public function reMap($request)
    {
    	global $currentTR, $nodeFlow;
    	$retArr1 = $this->mapFormRequestToArray($request);
    	$currentTR = [];
    	$currentTR = $this->getAllTr($retArr1);
    	$nodeFlow = true;
    	

	    $today = Carbon::parse(Carbon::now()->toDateString());
	    $retArr = [];
    	foreach ($retArr1 as $key => $value) {
	    	$effectiveDate = Carbon::parse($value['startdate']);

	    	if($value['role']==='master'){
	    		$retArr[$key] = $this->compileMasterMapping($retArr1[$key], false, true)[0];
	    	}else{
	    		$retArr[$key] = $this->compileSubMapping($retArr1[$key], false, true)[0];
	    	}
    	}
    	$nodeFlow = false;
    	// dd($retArr);


			// dd($retArr);
		$flag = $this->checkForOpenEnd($retArr);
			// dd($flag);
		// return $retArr;
		if($flag==0){
			// echo 'remap No Issue';
			$quaArray = $this->makeQueryArray($retArr);
			if(sizeof($quaArray['dates'])>0){
				$dateArr = $quaArray['dates'];
				usort($dateArr, function($date1, $date2){
				  if (strtotime($date1) < strtotime($date2))
				     return 1;
				  else if (strtotime($date1) > strtotime($date2))
				     return -1;
				  else
				     return 0;
				});
				$effectiveDate = Carbon::parse($dateArr[0]);
				// dd($quaArray);
				$this->processQueryArray($quaArray['queryArray']);

				# This Portion of Code will be enabled after implementation of Manual Execution and Report Section
				
				/*
				$this->relocateUserData($effectiveDate->toDateString().' - '.$today->toDateString(), $quaArray['users']);
				$trId = $this->resetReportForMapChange($effectiveDate->toDateString().' - '.$today->toDateString(), $quaArray['accounts'], null, 'Acmap-NoMasterEffect');

				Session::put('uploadStartDate', $effectiveDate->toDateString());
	            Session::put('uploadEndDate', $today->toDateString());
	            Session::put('TR_ID', $trId);

	            return view('pages.compilereportloader');
	            */
	            return view('greetings');

			}else{
				$this->processQueryArray($quaArray['queryArray']);
				// return 'লোক গুরুধি';
				# This below line which is comment out, got executed when there is no report to regenerate
	            //return redirect('/workgroups');
	            return view('greetings');
			}
		}else{
			// $mappingArray = $retArr;
			// $trDetails = $currentTR;
			// dd($currentTR);
			return view('mappingTree', ['mappingArray' => $retArr, 'trDetails' => $currentTR]);
		}
    }


    # Helper Function
    public function insert_using_keys($arr, $keys, $value){
        // we're modifying a copy of $arr, but here
        // we obtain a reference to it. we move the
        // reference in order to set the values.
        $a = &$arr;

        while( count($keys) > 0 ){
            // get next first key
            $k = array_shift($keys);

            // if $a isn't an array already, make it one
            if(!is_array($a)){
                $a = array();
            }

            // move the reference deeper
            $a = &$a[$k];
        }
        $a = $value;

        // return a copy of $arr with the value set
        return $arr;
    }

}