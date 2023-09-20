<?php 
namespace App\Utils;

use Carbon\Carbon;

use App\Models\TradeAccount;
use App\Models\User;
use App\Models\Acmap;
use App\Models\Settings;
use App\Models\UserData as Userdata;
use App\Models\DetailedBase;
use App\Models\Detailed;
use Log;
use Session;
use Illuminate\Support\Facades\Schema;
use DB;
use Carbon\CarbonPeriod;
use App\Utils\PropReportsApiClient;
use App\Traits\DataMapperTrait;


class DataMapperClass
{
	use DataMapperTrait;

	static $arr1 = [];
	static $arr1Master = [];
	
	static $accountsArray = [];

	static $dataMismatchFlag = false;
	static $userIdCol = 'userId';

	public function mapDataToUsers($sdate, $edate, $trId)
	{
		
		if (Schema::hasColumn('detailedBases', 'user_id')){
	        $userIdCol = 'user_id';
    	}

		self::$accountsArray = DetailedBase::selectRaw("sum(`qty`) as `totalQty`, `date`, `accountid`, `symbol`, `price`, `type`")->where('transactionId', '=', $trId)->groupBy('accountid')->pluck('accountid')->toArray();
		
		$baseDataD = DetailedBase::raw(function($collection) use ($trId){
			return $collection->aggregate([
				[
					'$match' => [
						'transactionId' => $trId
					]
				],[
					'$group' => [
						'_id' => [
							'date' => '$date',
							'accountid' => '$accountid',
							'symbol' => '$symbol',
							'price' => '$price',
							'type' => '$type',
						],
						'totalQty' => [
							'$sum' => '$qty'
						]
					]
				],[
					'$sort' => [
						'_id' => 1
					]
				],[
				    '$set' => [
				    	'date' => '$_id.date',
						'accountid' => '$_id.accountid',
						'symbol' => '$_id.symbol',
						'price' => '$_id.price',
						'type' => '$_id.type',
				      	'totalQty' => '$totalQty',
				      	'_id' => '$$REMOVE',
				    ]
				]
			]);
		});
		
		$userDataT = Userdata::raw(function($collection) use ($trId, $sdate, $edate){
			return $collection->aggregate([
				[
					'$match' => [
						// 'transactionId' => $trId,
						'date' => [
							'$gte' => $sdate,
							'$lte' => $edate,
						]
					]
				],[
					'$group' => [
						'_id' => [
							'date' => '$date',
							'accountid' => '$accountid',
							'symbol' => '$symbol',
							'price' => '$price',
							'type' => '$type',
						],
						'totalQty' => [
							'$sum' => '$qty'
						]
					]
				],[
					'$sort' => [
						'_id' => 1
					]
				],[
				    '$set' => [
				    	'date' => '$_id.date',
						'accountid' => '$_id.accountid',
						'symbol' => '$_id.symbol',
						'price' => '$_id.price',
						'type' => '$_id.type',
				      	'totalQty' => '$totalQty',
				      	'_id' => '$$REMOVE',
				    ]
				]
			]);
		});

		// Userdata::selectRaw("sum(`qty`) as `totalQty`, `date`, `accountid`, `symbol`, `price`, `type`")->where('date', '>=', $sdate)->where('date', '<=', $edate)->groupBy('date', 'accountid', 'symbol', 'price', 'type')->get();

		self::$arr1 = DetailedBase::where('transactionId', $trId)->get()->toArray();


		if(count($baseDataD)!=0){
			$feedBack = $this->handleReamining($sdate, $edate, $trId);
			if($feedBack){
				if(sizeof(self::$arr1)>0){
					foreach (self::$arr1 as $key => $value) {
						unset(self::$arr1[$key]['_id']);
					}
					Log::info('Removing Duplicate Id from Modified RawData');
				}

				if(sizeof(self::$arr1)>0){
					// return 'User Got Verified';
					foreach (array_chunk(self::$arr1,990) as $t) {
						// Detailed::insert(self::$arr1);
						DB::table('detaileds')->insert($t);
					}
					// Detailed::insert(self::$arr1);
					// return true;
					// return $this->generateReport();
					Log::info('Modified RawData Saved Successfully');
				}

				$dbData = Detailed::where('transactionId', $trId)->get();
				// dd($dbData);
		    	$dbData = $dbData->groupBy(function($item) {
					return Carbon::createFromFormat('Y-m-d', $item->date)->format('Y-m-d');
				});

				foreach ($dbData as $key => $value) {
					$dbData[$key] = $value->groupBy(function($item) {
						return $item->accountid;
					});
				}
				foreach ($dbData as $key => $value1) {
					foreach ($value1 as $acc => $value) {
						$dbData[$key][$acc] = $value->groupBy(function($item) {
							return $item->symbol;
						});
					}
				}
				$dbDataFiltered = [];

				foreach ($dbData as $dateKK => $value11) {
					foreach ($value11 as $accKK => $value21) {
						foreach ($value21 as $symbolKK => $value2) {
							foreach ($value2 as $data) {
								if(isset($dbDataFiltered[$dateKK][$accKK][$symbolKK])){
									$dbDataFiltered[$dateKK][$accKK][$symbolKK] += $data->qty;
								}else{
									$dbDataFiltered[$dateKK][$accKK][$symbolKK] = $data->qty;
								}
							}
						}
					}
				}
				// return $dbDataFiltered;
				// $tmp = '';
				foreach ($dbDataFiltered as $dateKK => $value1) {
					foreach ($value1 as $accKK => $value2) {
						foreach ($value2 as $symbolKK => $data) {
							$uData = Userdata::where('accountid', strval($accKK))->where('symbol', $symbolKK)->where('status', 'verified')->get();
							if($uData->count()>0){
								// $tmp = $uData;
								$totalQty = 0;
								foreach ($uData as $value) {
									$totalQty += $value->qty;
								}
								if($totalQty>$data){
									// dd($totalQty);exit;
									Userdata::where('date', $dateKK)->where('accountid', strval($accKK))->where('symbol', $symbolKK)->where('status', 'verified')->update(['status'=>'Mismatched']);
								}else{
									Userdata::where('date', $dateKK)->where('accountid', strval($accKK))->where('symbol', $symbolKK)->where('status', 'verified')->update(['status'=>'Validated']);
								}
							}
						}
						if(Userdata::where('date', $dateKK)->
						where('accountid', strval($accKK))->
						where('status', 'Mismatched')->count()>0){
							// Session::flash('error', 'User Data Mismatch found');
							$uidsForNotification = Userdata::where('date', $dateKK)->
								where('accountid', strval($accKK))->
								where('status', 'Mismatched')->pluck('user_id')->toArray();
							$uidsForNotification = array_unique($uidsForNotification);
							// $this->createMultiNotification('You have Mismatched TRADE DATA For The Date'.$dateKK, $uidsForNotification);
							
							return ['message' => 'User Data Mismatch found', 'userIds' => $uidsForNotification];
						}
					}
				}
				// return [$tmp];
				return ['message' => 'success'];
			}else{
				return ['message' => $feedBack];
				// return $feedBack;
			}

		}else
			return ['message' => 'detailed data is empty'];


		// return self::$arr1;
		
	}


	public function handleReamining($sdate, $edate, $trId)
	{
		$reaminingData = DetailedBase::where('transactionId', $trId)->get();
		
		
		foreach ($reaminingData as $key => $value) {
			if($value->{self::$userIdCol}==0 || $value->{self::$userIdCol}==NULL){
                // try{
                    $value->{self::$userIdCol} = $this->getAccountMaster($value->accountid, $value->date)->id;
				
                // }catch(Exception ex){
                //     dd($value);
                // }
				$value->update();
			}
		}

		if(sizeof(self::$arr1)>0){
			foreach (self::$arr1 as $key => $value) {
				if($value[self::$userIdCol] == null || $value[self::$userIdCol] == 0){

					$adm = $this->getAccountMaster($value['accountid'], $value['date']);
					
					if($adm==null){
						return ['message' => "Master not yet assigned : ".$value['accountid']." on ".$value['date']];
						// Log::error("Master not yet assigned : ".$value['accountid']." on ".$value['date']);
						// dd($arr1);exit;
					}
					self::$arr1[$key][self::$userIdCol] = $adm->id;
				}
			}
		}
		return true;
	}
}

	