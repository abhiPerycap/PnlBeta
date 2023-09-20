<?php


namespace App\Http\Controllers;

ini_set('max_execution_time', 300);
use Illuminate\Http\Request;
use App\Models\PreviousDayOpen;
use App\Models\Oreport;
use App\Models\User;
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
use App\Models\CashData;
use App\Models\Open;
use App\Models\Acmap;
use App\Models\Dreport;
use App\Models\Areport;
use App\Models\Userlocate;
use App\Models\TradeAccount;
use App\Models\Locreport;
use App\Utils\DataMapperClass;
use App\Utils\ReportGeneratorClass;


class PDOController extends Controller
{

	use DataAdapterTrait;
	use ReportGeneratorTrait;

	public function downloadSamplePDO()
	{
		$myFile = public_path("PDO.xlsx");
    	$headers = ['Content-Type: application/xlsx'];
    	$newName = 'sample-PDO-'.time().'.xlsx';

      	return response()->download($myFile, $newName, $headers);
	}

	public function uploadPDO(Request $request)
	{
		$rows = Excel::toArray(new PreviousDayOpenImport, $request->file('pdoFile'));
		$excelData = [];
		$excelErrorData = [];
		foreach ($rows[0] as $key => $value) {
			// $colCount = 1;
			$errorFlag = false;
			foreach ($value as $key1 => $value1) {
				// if($colCount<=7){
					if(is_null($value1)){
						$errorFlag = true;
						break;
					}
					// $colCount++;
				// }
			}
			$date = $value['dateyyyy_mm_dd'];
			if($date!=null){
		        $date = Carbon::parse(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($date))->toDateString();
		    }
			$value['date'] = $date;
			$value['userId'] = null;
			$value['generatedOn'] = Carbon::parse($request->date)->toDateString();
			unset($value['dateyyyy_mm_dd']);
			if($errorFlag)
				$excelErrorData[] = $value;
			else
				$excelData[] = $value;//array_slice($value, 0, 7);
		}
		if(sizeof($excelErrorData)>0)
			return response()->json(["message" => 'excel contains error',"rows"=>$excelData, 'errorData'=>$excelErrorData]);
		else
			return response()->json(["message" => 'success',"rows"=>$excelData]);
	}

	public function getPDOUsersForSelectedData(Request $request)
    {

        $selectedData = $request->Input('data');
        $allUsers = [];
        
        foreach ($selectedData as $value) {
            $users = $this->getAllTraders($value['accountid'], $value['date']);
            if(!is_null($users))
	            foreach ($users as $valueU) {
	                if(!in_array($valueU, $allUsers))
	                    $allUsers[] = $valueU;
	            }
        }
        
        $allUsers = User::whereIn('_id', $allUsers)->get();
        $users = [];

        if(!is_null($users))
	        foreach ($allUsers as $value) {
	            $users[] = [
	            	'id' => $value->id,
	            	'text' => $value->name
	            ];
	        }
        
        return json_encode($users);
    }


	public function store(Request $request)
	{
		// $accountId = 
		$accountid = '';
		if($request->Has('data')){
			$pdoArray = [];
			$oReport = [];
			$generatedOnDate = Carbon::parse($request->data['date'])->toDateString();
			PreviousDayOpen::where('generatedOn', $generatedOnDate)->delete();
			foreach ($request->data['pdo'] as $key => $value) {
				$accountid = (string)$value['accountid'];
				
				$obj = new PreviousDayOpen();
				$obj['accountid'] = (string)$value['accountid'];
				$obj['userId'] = $value['userId'];
				$obj['symbol'] = $value['symbol'];
				$obj['date'] = $value['date'];
				$obj['qty'] = $value['qty'];
				$obj['price'] = $value['price'];
				$obj['closePrice'] = $value['closeprice'];
				$obj['position'] = $value['qty'];
				$obj['generatedOn'] = $value['generatedOn'];
				$obj['unrealizeddelta'] = $value['unrealizeddelta'];
				$obj['created_at'] = $value['generatedOn'];
				$obj['updated_at'] = $value['generatedOn'];
				// $pdoArray[$value['symbol']][$value['userId']][] = $obj;
				// $generatedOnDate = $value['generatedOn'];
				$obj->save();
			}

			$pdoArray = PreviousDayOpen::where('generatedOn', $generatedOnDate)->where('accountid', $accountid)->get();

			$pdoArray = $pdoArray->groupBy(function($item) {
				return $item->symbol;
			});
			
			foreach ($pdoArray as $key => $value) {
				$pdoArray[$key] = $value->groupBy(function($item) {
					return $item->userId;
				});
			}

			if(sizeof($pdoArray)>0){
				foreach ($pdoArray as $symbol => $dataSet1) {
					foreach ($dataSet1 as $userId => $dataSet2) {
						$oReport[$symbol][$userId] = [
							'userId' => $userId,
							'date' => $generatedOnDate,
							'accountid' => $dataSet2[0]['accountid'],
							'symbol' => $symbol,
							'qty' => 0,
							'avgprice' => 0,
							'closeprice' => $dataSet2[0]['closePrice'],
							'ccy' => '',
							'spot' => '',
							'cost' => 0,
							'marketvalue' => 0,
							'und' => $dataSet2[0]['unrealizeddelta'],
							'un' => 0,
						];
						foreach ($dataSet2 as $key => $data) {
							// foreach ($dataSet as $key => $data) {
								$oReport[$symbol][$userId]['qty'] = $oReport[$symbol][$userId]['qty'] + $data['qty'];
								$oReport[$symbol][$userId]['avgprice'] = $oReport[$symbol][$userId]['avgprice'] + $data['qty']*$data['price'];
								

							// }
							
						}
						$oReport[$symbol][$userId]['avgprice'] = abs($oReport[$symbol][$userId]['avgprice']/$oReport[$symbol][$userId]['qty']);
						$oReport[$symbol][$userId]['cost'] = $oReport[$symbol][$userId]['avgprice']*$oReport[$symbol][$userId]['qty'];
						$oReport[$symbol][$userId]['marketvalue'] = $oReport[$symbol][$userId]['closeprice']*$oReport[$symbol][$userId]['qty'];
						$oReport[$symbol][$userId]['un'] = $oReport[$symbol][$userId]['marketvalue']-$oReport[$symbol][$userId]['cost'];
					}
				}
			}
			foreach ($oReport as $symbol => $rows) {
				foreach ($rows as $userId => $row) {
					$obj = new Oreport();
					foreach ($row as $key => $value) {
						$obj[$key] = $value;
					}
					$obj->save();
				}
			}
			// $cashData = $this->fetchReportFromServerByAccount_Date(
			// 	$accountid, 
			// 	[
			// 		'fromDate' => $generatedOnDate, 
			// 		'toDate' => $generatedOnDate
			// 	],
			// 	time(),
			// 	'summaryByDate'
			// );
	
			// $cashData = $cashData['data'];
			
			// if(sizeof($cashData)==1){
			// 	$cashData = $cashData[0]['cash'];

			// 	$cashObj = new CashData();
			// 	$cashObj->date = $generatedOnDate;
			// 	$cashObj->accountid = $accountid.'-vh';
			// 	$cashObj->cash = $cashData;
			// 	$cashObj->save();
			// }
			return response()->json(['message' => 'success'], 200);
		}else
			return response()->json(['message' => 'Bad Request'], 401);
	}

	public function generatePdoByDateAndAccount($date, $accountid){
		$date = Carbon::parse($date)->toDateString();
		
		$trId = Str::uuid()->toString();
		
		$serverData = $this->fetchFromServerByAccount_Date(
			$accountid, 
			[
				'fromDate' => $date, 
				'toDate' => $date
			],
			$trId,
			'open'
		);

		$pdoDayOpenData = $serverData['data'][1];

		$pdoDayOpenSymbols = [];
		foreach ($pdoDayOpenData as $key => $row) {
			if($row['open_qty']!=0)
				$pdoDayOpenSymbols[] = $row['symbol'];
		}
		// return $pdoDayOpenSymbols;

		// $pdoDayOpenSymbols = ['AAMC', 'AE','NBY'];

		$symbolWisePdoDates = [];

		$trackerDate = Carbon::parse($date);

		while(sizeof($pdoDayOpenSymbols)!=0){
			
			$edate = $trackerDate->toDateString();
			$trackerDate = $trackerDate->subMonths(12);
			if($trackerDate->isWeekend())
				$trackerDate = $trackerDate->subDays(3);

			$trId = Str::uuid()->toString();
			$openServerData = $this->fetchFromServerByAccount_Date(
				$accountid, 
				[
					'fromDate' => $trackerDate->toDateString(), 
					'toDate' => $edate
				],
				$trId,
				'open'
			);

			if(isset($openServerData['data']) && sizeof($openServerData['data'][1])>0){

				$pdoDayOpenDataTemp = $openServerData['data'][1];
				$tempp = [];
				foreach($pdoDayOpenDataTemp as $row){ 
				    if(in_array($row['symbol'], $pdoDayOpenSymbols))
				    	$tempp[$row['symbol']][$row['date']] = $row;
				}
				$pdoDayOpenDataTemp = $tempp;
				// return $pdoDayOpenDataTemp;

				foreach ($pdoDayOpenSymbols as $symbolIndex => $symbol) {
					$pdoDayOpenDataTempGroup = [];
					try{
						$pdoDayOpenDataTempGroup = $pdoDayOpenDataTemp[$symbol];

					}catch(Exception $e){
						return [$e, $symbol, $trackerDate->toDateString(), $edate, $pdoDayOpenDataTemp];
					}

					krsort($pdoDayOpenDataTempGroup);
					// return $pdoDayOpenDataTempGroup;

					$symbolCompleteFlag = false;
					$dateBeforeZero ='';
					foreach ($pdoDayOpenDataTempGroup as $dateKey => $row) {
						// $flag = true;
						try{
							// if($row['open_qty']!=0){
							// 	// $flag = false;
							// 	$dateBeforeZero = $dateKey;
							// 	// break;
							// }
							if($row['open_qty']==0){
								// $flag = false;
								// $dateBeforeZero = $dateKey;
								$symbolCompleteFlag = true;
								break;
							}else
								$dateBeforeZero = $dateKey;

						}catch(Exception $e){
							return [$e, $symbol, $dateKey, $row];
						}
					}

					if($symbolCompleteFlag || ($trackerDate->diffInDays(Carbon::parse($dateBeforeZero)))>3){
						$symbolWisePdoDates[$symbol] = [$dateBeforeZero, $date];
						unset($pdoDayOpenSymbols[$symbolIndex]);
						
					}
				}
				$pdoDayOpenSymbols = array_values($pdoDayOpenSymbols);
			}
		}

		$feedBackArray = [];
		$accId = $accountid;
		$accountid = TradeAccount::find($accountid)['accountid'];

		$generatedStartDate = '';
		foreach ($symbolWisePdoDates as $key => $value) {
			if($generatedStartDate=='')
				$generatedStartDate = $value[0];
			else{
				if(Carbon::parse($value[0])<Carbon::parse($generatedStartDate))
					$generatedStartDate = $value[0];
			}
		}

		$pdoDayOpenSymbols = array_keys($symbolWisePdoDates);

		$completeFlag = false;
		$result = [];
		while(!$completeFlag){
			$sdate = $generatedStartDate;
			if(Carbon::parse($sdate)->diffInDays(Carbon::parse($date))>30)
				$edate = Carbon::parse($sdate)->addDays(30)->toDateString();
			else
				$edate = $date;


			$trId = Str::uuid()->toString();
			$serverData = $this->fetchFromServerByAccount_Date(
				$accId, 
				[
					'fromDate' => $sdate, 
					'toDate' => $edate
				],
				$trId
			);
			
			if($serverData['message']=='success'){
				// return [Carbon::parse($generatedStartDate)->diffInDays(Carbon::parse($date)), $serverData];
				// return [$generatedStartDate, $serverData['data'][1]];
				$detailed = $serverData['data'][0];
				$open = $serverData['data'][1];
				
				$dF = [];
				foreach ($detailed as $key => $value) {
					if(in_array($value['symbol'], $pdoDayOpenSymbols))
						if(isset($symbolWisePdoDates[$value['symbol']]))
							if(Carbon::parse($value['date'])>=Carbon::parse($symbolWisePdoDates[$value['symbol']][0]))
								$dF[] = $value;
				}
				$oF = [];
				foreach ($open as $key => $value) {
					if(in_array($value['symbol'], $pdoDayOpenSymbols))
						if(isset($symbolWisePdoDates[$value['symbol']]))
							if(Carbon::parse($value['date'])>=Carbon::parse($symbolWisePdoDates[$value['symbol']][0]))
								$oF[] = $value;
								
				}
				// return [$dF, $oF];

				if (sizeof($detailed)>0) {           

		            DetailedBase::whereIn('symbol', $pdoDayOpenSymbols)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
		            Detailed::whereIn('symbol', $pdoDayOpenSymbols)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
		            Open::whereIn('symbol', $pdoDayOpenSymbols)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
		            Adjustment::whereIn('symbol', $pdoDayOpenSymbols)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
		            PreviousDayOpen::whereIn('symbol', $pdoDayOpenSymbols)->where('generatedOn', '>=', $sdate)->where('generatedOn', '<=', $edate)->where('accountid', $accountid)->delete();
		            Areport::whereIn('symbol', $pdoDayOpenSymbols)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
		            Dreport::whereIn('symbol', $pdoDayOpenSymbols)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
		            Oreport::whereIn('symbol', $pdoDayOpenSymbols)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
		            Closedata::whereIn('symbol', $pdoDayOpenSymbols)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', strval($accountid))->delete();
		            
				CashData::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('mappedAccount', strval($accountid))->delete();
				CashData::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', strval($accountid))->delete();
					Locatedata::whereIn('symbol', $pdoDayOpenSymbols)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
		            Locreport::whereIn('symbol', $pdoDayOpenSymbols)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
		    
		            // $ulData = Userlocate::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->get();
		            // foreach ($ulData as $key => $value) {
		            //     Userlocate::where('id', $value->id)->update(['status' => $value->prevstatus]);
		            // }

		            // DB::statement("update userlocates SET `status` = `prevstatus` WHERE `date` >= $sdate AND `date` <= $edate AND `accountid` = '$accountid'");

		            foreach (array_chunk($dF,1000) as $t) {
			            DetailedBase::insert($t);
			        }

			        foreach (array_chunk($oF,1000) as $t) {
			            Open::insert($t);
			        }
			        // foreach (array_chunk($adjustment,1000) as $t) {
			        //     Adjustment::insert($t);
			        // }
			        $dataMapper = new DataMapperClass();
			        $feedBack = $dataMapper->mapDataToUsers($sdate, $edate, $trId);
			        
			        $generator = new ReportGeneratorClass();
					$feedBack = $generator->processReport($sdate, $edate, $trId);
			        // $feedBackArray[$symbol] = $feedBack;
			        // if($feedBack=='success'){
			        // 	return PreviousDayOpen::where('generatedOn', '=', $date)->where('accountid', $accountid)->get()->toArray();
			        // }else
			        	// $result[$generatedStartDate] = $feedBack;
			        	// return [$generatedStartDate, $date, $feedBack];
		        }

		        if($edate==$date){
		        	$completeFlag = true;
		        }else
		        	$generatedStartDate = Carbon::parse($generatedStartDate)->addDay()->toDateString();

			}


		}
		return $result;

		


		// foreach ($symbolWisePdoDates as $symbol => $dates) {
		// 	$trId = Str::uuid()->toString();
		// 	$serverData = $this->fetchFromServerByAccount_Date(
		// 		$accId, 
		// 		[
		// 			'fromDate' => $dates[0], 
		// 			'toDate' => $dates[1]
		// 		],
		// 		$trId
		// 	);
			
		// 	if($serverData['message']=='success'){
		// 		$detailed = $serverData['data'][0];
		// 		$open = $serverData['data'][1];
				
		// 		$dF = [];
		// 		foreach ($detailed as $key => $value) {
		// 			if($value['symbol']==$symbol)
		// 				$dF[] = $value;
		// 		}
		// 		$oF = [];
		// 		foreach ($open as $key => $value) {
		// 			if($value['symbol']==$symbol)
		// 				$oF[] = $value;
		// 		}
		// 	// return [$dF, $oF];

		// 		if (sizeof($detailed)>0) {
		            
		//             $sdate = $dates[0];
		//             $edate = $dates[1];
		            

		//             DetailedBase::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
		//             Detailed::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
		//             Open::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
		//             Adjustment::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
		//             PreviousDayOpen::where('symbol', $symbol)->where('generatedOn', '>=', $sdate)->where('generatedOn', '<=', $edate)->where('accountid', $accountid)->delete();
		//             Areport::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
		//             Dreport::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
		//             Oreport::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
		//             Closedata::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', strval($accountid))->delete();
		//             Locatedata::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
		//             Locreport::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
		    
		//             // $ulData = Userlocate::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->get();
		//             // foreach ($ulData as $key => $value) {
		//             //     Userlocate::where('id', $value->id)->update(['status' => $value->prevstatus]);
		//             // }

		//             // DB::statement("update userlocates SET `status` = `prevstatus` WHERE `date` >= $sdate AND `date` <= $edate AND `accountid` = '$accountid'");

		//             foreach (array_chunk($dF,1000) as $t) {
		// 	            DetailedBase::insert($t);
		// 	        }

		// 	        foreach (array_chunk($oF,1000) as $t) {
		// 	            Open::insert($t);
		// 	        }

		// 	        // foreach (array_chunk($adjustment,1000) as $t) {
		// 	        //     Adjustment::insert($t);
		// 	        // }
		// 	        $dataMapper = new DataMapperClass();
		// 	        $feedBack = $dataMapper->mapDataToUsers($sdate, $edate, $trId);
			        
		// 	        $generator = new ReportGeneratorClass();
		// 			$feedBack = $generator->processReport($dates[0], $dates[1], $trId);
		// 	        $feedBackArray[$symbol] = $feedBack;
		// 	        // if($feedBack=='success'){
		// 	        // 	return PreviousDayOpen::where('generatedOn', '=', $date)->where('accountid', $accountid)->get()->toArray();
		// 	        // }else
		// 	        // 	return $feedBack;
		//         }

		// 	}
		// }
		return $feedBackArray;
	}

	public function getSymbolWisePdoDates(Request $request)
	{
		$date = Carbon::parse($request['data']['date'])->toDateString();
		$accountid = $request['data']['account'];
		$accId = TradeAccount::where('_id', $accountid)->first()['accountid'];

		if(PreviousDayOpen::where('accountid', $accId)->where('generatedOn', $date)->count()>0){
			return response()->json(['message' => 'PDO Exists for the selected date', 'data' => []], 200);
		}
		if(DetailedBase::where('accountid', $accId)->count()>0){
			return response()->json(['message' => 'Fetching PDO is prohibited for the accounts which already have Detailed Data in the System', 'data' => []], 200);
		}

		$trId = Str::uuid()->toString();
		
		$serverData = $this->fetchFromServerByAccount_Date(
			$accountid, 
			[
				'fromDate' => $date, 
				'toDate' => $date
			],
			$trId,
			'open'
		);
		// return $serverData;
		if(isset($serverData) && $serverData['message']=='success'){
			if(sizeof($serverData['data'][1])>0){

				$pdoDayOpenData = $serverData['data'][1];

				$pdoDayOpenSymbols = [];
				foreach ($pdoDayOpenData as $key => $row) {
					if($row['open_qty']!=0)
						$pdoDayOpenSymbols[] = $row['symbol'];
				}
				// return $pdoDayOpenSymbols;

				// $pdoDayOpenSymbols = ['AAMC', 'AE','NBY'];
				if(sizeof($pdoDayOpenSymbols)>0){

					$symbolWisePdoDates = [];
	
					$trackerDate = Carbon::parse($date);
					$pdoFirstDate = null;
					while(sizeof($pdoDayOpenSymbols)!=0){
						
						$edate = $trackerDate->toDateString();
						$trackerDate = $trackerDate->subMonths(12);
						if($trackerDate->isWeekend())
							$trackerDate = $trackerDate->subDays(3);
	
						$trId = Str::uuid()->toString();
						$openServerData = $this->fetchFromServerByAccount_Date(
							$accountid, 
							[
								'fromDate' => $trackerDate->toDateString(), 
								'toDate' => $edate
							],
							$trId,
							'open'
						);
	
						if(isset($openServerData['data']) && sizeof($openServerData['data'][1])>0){
	
							$pdoDayOpenDataTemp = $openServerData['data'][1];
							$tempp = [];
							foreach($pdoDayOpenDataTemp as $row){ 
								if(in_array($row['symbol'], $pdoDayOpenSymbols))
									$tempp[$row['symbol']][$row['date']] = $row;
							}
							$pdoDayOpenDataTemp = $tempp;
							// return $pdoDayOpenDataTemp;
	
							foreach ($pdoDayOpenSymbols as $symbolIndex => $symbol) {
								$pdoDayOpenDataTempGroup = [];
								try{
									$pdoDayOpenDataTempGroup = $pdoDayOpenDataTemp[$symbol];
	
								}catch(Exception $e){
									return [$e, $symbol, $trackerDate->toDateString(), $edate, $pdoDayOpenDataTemp];
								}
	
								krsort($pdoDayOpenDataTempGroup);
								// return $pdoDayOpenDataTempGroup;
	
								$symbolCompleteFlag = false;
								$dateBeforeZero ='';
								foreach ($pdoDayOpenDataTempGroup as $dateKey => $row) {
									// $flag = true;
									try{
										// if($row['open_qty']!=0){
										// 	// $flag = false;
										// 	$dateBeforeZero = $dateKey;
										// 	// break;
										// }
										if($row['open_qty']==0){
											// $flag = false;
											// $dateBeforeZero = $dateKey;
											$symbolCompleteFlag = true;
											break;
										}else
											$dateBeforeZero = $dateKey;
	
									}catch(Exception $e){
										return [$e, $symbol, $dateKey, $row];
									}
								}
	
								if($symbolCompleteFlag || ($trackerDate->diffInDays(Carbon::parse($dateBeforeZero)))>3){
									$symbolWisePdoDates[$symbol] = [$dateBeforeZero, $date];
									unset($pdoDayOpenSymbols[$symbolIndex]);
									
									if($pdoFirstDate==null || Carbon::parse($dateBeforeZero)->lt(Carbon::parse($pdoFirstDate))){
										$pdoFirstDate = $dateBeforeZero;
									}
								}
							}
							$pdoDayOpenSymbols = array_values($pdoDayOpenSymbols);
						}
					}
					if($this->getAccountMaster($accId, $pdoFirstDate)==null){
						return response()->json(['message' => 'Please Assing a Master From '.$pdoFirstDate.' and Try again'], 200);
					}else{
						$masterUser = $this->getAccountMaster($accId, $pdoFirstDate);
						if(Acmap::where('startdate', '>', $pdoFirstDate)->
						where('startdate', '<=', $date)->
						where('role', 'master')->
						where('user', '!=', $masterUser['_id'])->
						where('mappedTo', $accId)->
						count()>0){
							return response()->json(['message' => 'Mater cannot be changed within '.$pdoFirstDate.' to '.$date.'. Ony One Master should be there during PDO Generation Time Frame'], 200);
						}
					}
					return response()->json(['message' => 'success', 'data' => $symbolWisePdoDates], 200);
				}else{
					return response()->json(['message' => 'success', 'data' => []], 200);				
				}
			}else
				return response()->json(['message' => 'success', 'data' => []], 200);				
		}else
			return response()->json(['message' => 'Bad Request', 'response' => $serverData], 200);
	}



	public function getSymbolWisePdo(Request $request)
	{
		$sdate = $request['data']['fromDate'];
		$edate = $request['data']['toDate'];
		
		$accId = $request['data']['accountid'];
		$accountid = TradeAccount::find($request['data']['accountid'])['accountid'];
		// $accountid = $request['data']['accountid'];
		$symbol = $request['data']['symbol'];

		
		$trId = Str::uuid()->toString();
		$serverData = $this->fetchFromServerByAccount_Date(
			$accId, 
			[
				'fromDate' => $sdate, 
				'toDate' => $edate
			],
			$trId
		);

		// return $serverData;
		if($serverData['message']=='success'){
			// return [Carbon::parse($generatedStartDate)->diffInDays(Carbon::parse($date)), $serverData];
			// return [$generatedStartDate, $serverData['data'][1]];
			$detailed = $serverData['data'][0];
			$open = $serverData['data'][1];
			
			$dF = [];
			foreach ($detailed as $key => $value) {
				if($value['symbol']==$symbol)
					$dF[] = $value;
			}

			$detailedDates = [];

			foreach ($dF as $key => $value) {
				if(!in_array($value['date'], $detailedDates))
					$detailedDates[] = $value['date'];
			}

			$oF = [];
			foreach ($open as $key => $value) {
				if($value['symbol']==$symbol)
					if(in_array($value['date'], $detailedDates))
						$oF[] = $value;
			}



			if (sizeof($detailed)>0) {         

			    DetailedBase::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
			    Detailed::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
			    Open::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
			    Adjustment::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
			    PreviousDayOpen::where('symbol', $symbol)->where('generatedOn', '>=', $sdate)->where('generatedOn', '<=', $edate)->where('accountid', $accountid)->delete();
			    Areport::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
			    Dreport::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
			    Oreport::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
			    Closedata::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', strval($accountid))->delete();
			    CashData::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('mappedAccount', strval($accountid))->delete();
				CashData::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', strval($accountid))->delete();
				Locatedata::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
			    Locreport::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();

			    // $ulData = Userlocate::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->get();
			    // foreach ($ulData as $key => $value) {
			    //     Userlocate::where('id', $value->id)->update(['status' => $value->prevstatus]);
			    // }

			    // DB::statement("update userlocates SET `status` = `prevstatus` WHERE `date` >= $sdate AND `date` <= $edate AND `accountid` = '$accountid'");

			    foreach (array_chunk($dF,1000) as $t) {
			        DetailedBase::insert($t);
			    }

			    foreach (array_chunk($oF,1000) as $t) {
			        Open::insert($t);
			    }
			    // foreach (array_chunk($adjustment,1000) as $t) {
			    //     Adjustment::insert($t);
			    // }
			    $dataMapper = new DataMapperClass();
			    $feedBack = $dataMapper->mapDataToUsers($sdate, $edate, $trId);
			    
			    $generator = new ReportGeneratorClass();
				$feedBack = $generator->processReport($sdate, $edate, $trId);
			    // return $feedBack->getData();
			    // $feedBackArray[$symbol] = $feedBack;
			    $feedBack = (array)$feedBack->getData();
			    if($feedBack['message']=='success'){
			    	$lastPdoDate = PreviousDayOpen::where('accountid', $accountid)->where('symbol', $symbol)->orderBy('generatedOn', 'desc')->first()['generatedOn'];
			    	$data = PreviousDayOpen::where('generatedOn', $lastPdoDate)->where('accountid', $accountid)->where('symbol', $symbol)->get()->toArray();


			    	$opData = Oreport::where('symbol', $symbol)->where('date', $lastPdoDate)->where('accountid', $accountid)->get();

			    	$modifiedData = [];

			    	foreach ($data as $pdoRow) {
			    		$pdoRow['marketvalue'] = $opData->where('symbol', $pdoRow['symbol'])->first()['marketvalue'];
			    		$pdoRow['und'] = $opData->where('symbol', $pdoRow['symbol'])->first()['und'];
			    		$modifiedData[] = $pdoRow;
			    	}
				    


			    	DetailedBase::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
				    Detailed::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
				    Open::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
				    Adjustment::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
				    PreviousDayOpen::where('symbol', $symbol)->where('generatedOn', '>=', $sdate)->where('generatedOn', '<=', $edate)->where('accountid', $accountid)->delete();
				    Areport::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
				    Dreport::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
				    Oreport::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
				    Closedata::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', strval($accountid))->delete();
				    CashData::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('mappedAccount', strval($accountid))->delete();
					CashData::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', strval($accountid))->delete();
					Locatedata::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
				    Locreport::where('symbol', $symbol)->where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();

					return response()->json(['message' => 'success', 'data' => $modifiedData], 200);
			    }else
					return response()->json(['message' => $feedBack['message']], 200);
			    	// $result[$generatedStartDate] = $feedBack;
			    	// return [$generatedStartDate, $date, $feedBack];
			}else
				return response()->json(['message' => 'error'], 200);
		}else{
			return response()->json(['message' => $serverData['message']], 200);

		}
	}


	public function saveFetchedPDO(Request $request)
	{
		if($request->Has('data') && $request->Has('date')){
			$generatedOnDate = Carbon::parse($request->date)->toDateString();
			$trId = Str::uuid()->toString();
			$accountid = $request->accountid;
			$accId = $request->accId;
			$openServerData = $this->fetchFromServerByAccount_Date(
				$accId, 
				[
					'fromDate' => $generatedOnDate, 
					'toDate' => $generatedOnDate
				],
				$trId,
				'open'
			);
			if(isset($openServerData) && $openServerData['message']=='success'){
				if(sizeof($openServerData['data'][1])>0){
					$pdoDayOpenDataTemp = $openServerData['data'][1];
					$tempp = [];
					foreach($pdoDayOpenDataTemp as $row){ 
					    $tempp[$row['symbol']] = $row;
					}
					$pdoDayOpenDataTemp = $tempp;
					$pdo = $request->data;

					$slicedDataForOreport = [];

					foreach ($pdo as $symbol => $dataRows) {
						$temp = [];
						foreach ($dataRows as $value) {
							$value['generatedOn'] = $generatedOnDate;
							// $accountid = $value['accountid'];
							
							unset($value['_id']);

							// $slicedDataForOreport[$value['symbol']]['und'] = $value['und'];
							$slicedDataForOreport[$value['symbol']]['marketvalue'] = $value['marketvalue'];

							unset($value['marketvalue']);
							unset($value['und']);
							// unset($value['created_at']);
							// unset($value['updated_at']);
							$temp[] = $value;
						}

						PreviousDayOpen::insert($temp);
					}
					$pdoArray = PreviousDayOpen::where('generatedOn', $generatedOnDate)->where('accountid', $accountid)->get();

					$pdoArray = $pdoArray->groupBy(function($item) {
						return $item->symbol;
					});
					
					foreach ($pdoArray as $key => $value) {
						$pdoArray[$key] = $value->groupBy(function($item) {
							return $item->userId;
						});
					}
					$oReport = [];
					if(sizeof($pdoArray)>0){
						foreach ($pdoArray as $symbol => $dataSet1) {
							foreach ($dataSet1 as $userId => $dataSet2) {
								$oReport[$symbol][$userId] = [
									'userId' => $userId,
									'date' => $generatedOnDate,
									'accountid' => $dataSet2[0]['accountid'],
									'symbol' => $symbol,
									'qty' => 0,
									'avgprice' => 0,
									'closeprice' => $pdoDayOpenDataTemp[$symbol]['closeprice'],
									'ccy' => '',
									'spot' => '',
									'cost' => 0,
									'marketvalue' => $slicedDataForOreport[$symbol]['marketvalue'],
									'und' => $pdoDayOpenDataTemp[$symbol]['unrealized_delta'],
									'un' => $pdoDayOpenDataTemp[$symbol]['unrealized'],
								];
								foreach ($dataSet2 as $key => $data) {
									// foreach ($dataSet as $key => $data) {
										$oReport[$symbol][$userId]['qty'] = $oReport[$symbol][$userId]['qty'] + $data['qty'];
										$oReport[$symbol][$userId]['avgprice'] = $oReport[$symbol][$userId]['avgprice'] + $data['qty']*$data['price'];
										

									// }
									
								}
								$oReport[$symbol][$userId]['avgprice'] = abs($oReport[$symbol][$userId]['avgprice']/$oReport[$symbol][$userId]['qty']);
								$oReport[$symbol][$userId]['cost'] = $oReport[$symbol][$userId]['avgprice']*$oReport[$symbol][$userId]['qty'];
								$oReport[$symbol][$userId]['marketvalue'] = $oReport[$symbol][$userId]['closeprice']*$oReport[$symbol][$userId]['qty'];
								$oReport[$symbol][$userId]['un'] = $oReport[$symbol][$userId]['marketvalue']-$oReport[$symbol][$userId]['cost'];
							}
						}
					}
					foreach ($oReport as $symbol => $rows) {
						foreach ($rows as $userId => $row) {
							$obj = new Oreport();
							foreach ($row as $key => $value) {
								$obj[$key] = $value;
							}
							$obj->save();
						}
					}

					$cashData = $this->fetchReportFromServerByAccount_Date(
						$accId, 
						[
							'fromDate' => $generatedOnDate, 
							'toDate' => $generatedOnDate
						],
						time(),
						'summaryByDate'
					);
					$rep = $cashData;
			
					$cashData = $cashData['data'];
					
					if(sizeof($cashData)>0){
						$cashData = $cashData[0]['cash'];
						$cashD = CashData::where('date', $generatedOnDate)->where('accountid', $accountid)->get();
						if($cashD->count()==1){
							CashData::where('_id', $cashD[0]->_id)->update(['cash' => floatval($cashData)]);
						}else{
							$cashObj = new CashData();
							$cashObj->date = $generatedOnDate;
							$cashObj->accountid = $accountid;
							$cashObj->cash = $cashData;
							$cashObj->save();
						}
					}

					return response()->json(['message' => 'success', 'data' => $rep ], 200);
				}else
					return response()->json(['message' => 'success'], 200);

			}else
				return response()->json(['message' => "Couldn't fetch Current Day Open Data"], 200);
		}else
			return response()->json(['message' => 'Bad Request'], 200);
	}

}