<?php

namespace App\Http\Controllers;
set_time_limit(0);
ini_set('memory_limit', -1);


use Illuminate\Http\Request;
use App\Models\TradeAccount;
use App\Models\Broker;
use Carbon\Carbon;
use Session;


class CornJobManagerController extends Controller
{

	public function generateReport($daterange, $accounts = null)
	{
		if($daterange==null)
    	    $daterange = Carbon::yesterday()->toDateString().' - '.Carbon::yesterday()->toDateString();
    	$daterr = explode(' - ', trim($daterange));
        
        $sdate = Carbon::parse($daterr[0])->toDateString();
        $edate = Carbon::parse($daterr[1])->toDateString();
        if($accounts==null)
            $accounts = TradeAccount::where('autoReport', true)->where('authorised', true)->get();
        else
            $accounts = TradeAccount::whereIn('accountid', $accounts)->get();
        // echo 'Sourav Vai';
        // return 'Sourav Vai';

        $responseArray = [];
        foreach ($accounts as $account) {
        	echo('Working on '.$account['accountid']."\n");
        	
        	$dataForPDO = [
        		'date' => Carbon::parse($sdate)->subDay()->toDateString(),
        		'account' => $account['_id']
        	];
        	$requestForPDO = new Request();
        	$requestForPDO->replace(['data' => $dataForPDO]);

			$pdoGenerator = new \App\Http\Controllers\PDOController();
			$pdoRes = $pdoGenerator->getSymbolWisePdoDates($requestForPDO);
			$pdoRes = $pdoRes->getOriginalContent();
			// dd($pdoRes);
			if($pdoRes['message']!='Bad Request'){
				if(sizeof($pdoRes['data'])==0){
		        	$data = [];
		        	$data['fromDate'] = $sdate;
		        	$data['toDate'] = $edate;
		        	$data['account']['id'] = $account['_id'];
		        	$data['account']['text'] = strval($account['accountid']);

		        	$requestForReport = new Request();
					$requestForReport->replace(['data' => $data]);

					$reportGenerator = new \App\Http\Controllers\ReportManagerController();
					$response = $reportGenerator->importServerData($requestForReport);
					// echo gettype($response);
					// echo ($response instanceof Illuminate\Http\JsonResponse)?'true':'false';
					// echo ($response instanceof JsonResponse)?'true':'false';
					$status = '';
					// if(!($response instanceof Illuminate\Http\JsonResponse))
					// 	$status = $response['message'];
					// else
						$status = $response->getOriginalContent()['message'];
					$responseArray[] = [
						'fromDate' => $sdate,
						'toDate' => $sdate,
						'accountid' => $account['accountid'],
						'status' => $status
					];
				}else{
					$responseArray[] = [
						'fromDate' => $sdate,
						'toDate' => $sdate,
						'accountid' => $account['accountid'],
						'status' => 'PDO Exists. Please Import For the Account First'
					];
				}
			}else{
				$responseArray[] = [
					'fromDate' => $sdate,
					'toDate' => $sdate,
					'accountid' => $account['accountid'],
					'status' => $pdoRes['message']
				];
			}
			echo($responseArray[sizeof($responseArray)-1]['status']."\n");
        }
        dd($responseArray);
        return $responseArray;
	}

}