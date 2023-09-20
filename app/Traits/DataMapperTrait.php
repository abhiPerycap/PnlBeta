<?php 
namespace App\Traits;

use Carbon\Carbon;

use App\Models\TradeAccount;
use App\Models\User;
use App\Models\Acmap;
use App\Models\Settings;
use App\Models\UserData as Userdata;
use App\Models\DetailedBase;
use Log;
use Session;
use Illuminate\Support\Facades\Schema;
use DB;
use Carbon\CarbonPeriod;
use App\Utils\PropReportsApiClient;


trait DataMapperTrait
{

	// public function mapDataToUsers($sdate, $edate, $trId)
	// {
	// 	$arr1 = [];
	// 	$arr1Master = [];
		
	// 	$accountsArray = [];

	// 	$dataMismatchFlag = false;

	// 	$accountsArray = DetailedBase::selectRaw("sum(`qty`) as `totalQty`, `date`, `accountid`, `symbol`, `price`, `type`")->where('transactionId', '=', $trId)->groupBy('accountid')->pluck('accountid')->toArray();
		
	// 	$baseDataD = DetailedBase::raw(function($collection) use ($trId){
	// 		return $collection->aggregate([
	// 			[
	// 				'$match' => [
	// 					'transactionId' => $trId
	// 				]
	// 			],[
	// 				'$group' => [
	// 					'_id' => [
	// 						'date' => '$date',
	// 						'accountid' => '$accountid',
	// 						'symbol' => '$symbol',
	// 						'price' => '$price',
	// 						'type' => '$type',
	// 					],
	// 					'totalQty' => [
	// 						'$sum' => '$qty'
	// 					]
	// 				]
	// 			],[
	// 				'$sort' => [
	// 					'_id' => 1
	// 				]
	// 			],[
	// 			    '$set' => [
	// 			    	'date' => '$_id.date',
	// 					'accountid' => '$_id.accountid',
	// 					'symbol' => '$_id.symbol',
	// 					'price' => '$_id.price',
	// 					'type' => '$_id.type',
	// 			      	'totalQty' => '$totalQty',
	// 			      	'_id' => '$$REMOVE',
	// 			    ]
	// 			]
	// 		]);
	// 	});
		
	// 	$userDataT = Userdata::raw(function($collection) use ($trId, $sdate, $edate){
	// 		return $collection->aggregate([
	// 			[
	// 				'$match' => [
	// 					'transactionId' => $trId,
	// 					'date' => [
	// 						'$gte' => $sdate,
	// 						'$lte' => $edate,
	// 					]
	// 				]
	// 			],[
	// 				'$group' => [
	// 					'_id' => [
	// 						'date' => '$date',
	// 						'accountid' => '$accountid',
	// 						'symbol' => '$symbol',
	// 						'price' => '$price',
	// 						'type' => '$type',
	// 					],
	// 					'totalQty' => [
	// 						'$sum' => '$qty'
	// 					]
	// 				]
	// 			],[
	// 				'$sort' => [
	// 					'_id' => 1
	// 				]
	// 			],[
	// 			    '$set' => [
	// 			    	'date' => '$_id.date',
	// 					'accountid' => '$_id.accountid',
	// 					'symbol' => '$_id.symbol',
	// 					'price' => '$_id.price',
	// 					'type' => '$_id.type',
	// 			      	'totalQty' => '$totalQty',
	// 			      	'_id' => '$$REMOVE',
	// 			    ]
	// 			]
	// 		]);
	// 	});

	// 	// Userdata::selectRaw("sum(`qty`) as `totalQty`, `date`, `accountid`, `symbol`, `price`, `type`")->where('date', '>=', $sdate)->where('date', '<=', $edate)->groupBy('date', 'accountid', 'symbol', 'price', 'type')->get();

	// 	$arr1 = DetailedBase::where('transactionId', $trId)->get()->toArray();


	// 	if(count($baseDataD)!=0){
			
	// 	}


	// 	return $baseDataD;
		
	// }


	// public function handleReamining($sdate, $edate, $trId)
	// {
	// 	$reaminingData = DetailedBase::where('transactionId', $trId)->get();
		
	// 	$userIdCol = 'userId';
		
	// 	if (Schema::hasColumn('detailedBases', 'user_id')){
	//         $userIdCol = 'user_id';
 //    	}

	// 	foreach ($reaminingData as $key => $value) {
	// 		if($value->{$userIdCol}==0 || $value->{$userIdCol}==NULL){
 //                // try{
 //                    $value->{$userIdCol} = $this->getAccountMaster($value->accountid, $value->date)->id;
				
 //                // }catch(Exception ex){
 //                //     dd($value);
 //                // }
	// 			$value->update();
	// 		}
	// 	}

	// 	if(sizeof(self::$arr1)>0){
	// 		foreach (self::$arr1 as $key => $value) {
	// 			if($value[$userIdCol] == null || $value[$userIdCol] == 0){
	// 				// $share = Share::where('accountid', $value['accountid'])->first();
	// 				$adm = $this->getAccountMaster($value['accountid'], $value['date']);
	// 				// dd($share->admin->id);
	// 				// echo json_encode($share);
	// 				if($adm==null){
	// 					Log::error("Master not yet assigned : ".$value['accountid']." on ".$value['date']);
	// 					dd($arr1);exit;
	// 				}
	// 				self::$arr1[$key][$userIdCol] = $adm->id;
	// 			} 
	// 		}
	// 	}
	// }

	public function getAccountMaster($accountid, $date=null)
    {
        $accountid = strtoupper($accountid);
        if($date!=null){
            $val = Acmap::where('role', 'master')->
                            where('mappedTo', $accountid)->
                            where('startdate', '<=', $date)->
                            orderBy('startdate', 'desc')->
                            first();
            if(isset($val)){
                $user = User::find($val->user);
                if(isset($user))
                    return $user;
                else
                    return null;
            }else
                return null;
        }else{
            $val = Acmap::where('role', 'master')->
                            where('mappedTo', $accountid)->
                            orderBy('startdate', 'desc')->
                            first();
            if(isset($val)){
                $user = User::find($val->user);
                if(isset($user))
                    return $user;
                else
                    return null;
            }else
                return null;
        }
    }
}

	