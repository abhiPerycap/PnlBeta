<?php
namespace App\Traits;

use Carbon\Carbon;

use App\Models\TradeAccount;
use App\Models\User;
use App\Models\Acmap;
use App\Models\Settings;
use Log;
use Session;

use MongoDB;
use Carbon\CarbonPeriod;
use App\Utils\PropReportsApiClient;


const DEBUG = false;

$currentTR = ['koi'];
$nodeFlow = false;

trait DataAdapterTrait
{

	public function getTechFeeMatch($comment, $category)
	{
		$keyPhrase = Settings::first()->techfeekeyphrase;
		$keyPhrase = explode(',', $keyPhrase);
		$flag = false;
		// foreach ($keyPhrase as $kpValue) {
		// 	$kpV = explode('&', $kpValue);
		// 	// echo json_encode($comment);
		// 	if(sizeof($kpV)==2){
		// 		if(strpos(strtolower($comment), trim($kpV[0]))!==false && strpos(strtolower($category), trim($kpV[1]))!==false){
		// 			$flag = true;
		// 		}
		// 	}if(sizeof($kpV)==1){
		// 		// if($category=='ee')
		// 		// dd($comment);
		// 		if(strpos(strtolower($comment), trim($kpV[0]))!==false){
		// 			// return true;
		// 			$flag = true;
		// 		}

		// 	}

		// }
		return $flag;
	}


	public function fetchFromServerByAccount_Date($account, $date, $sessionId = null, $apiDataType = 'all')
	{
		$account = TradeAccount::where('_id', $account)->first();
		$apiDetails = $account->getApiDetails();
		// return $account->propReportId;

		$fromDate = Carbon::parse($date['fromDate'])->toDateString();
		$toDate = Carbon::parse($date['toDate'])->toDateString();
		// return $fromDate;
		$propReportsClient = new PropReportsApiClient($apiDetails['url']);
		$propReportsClient->setCredentials($apiDetails['username'], $apiDetails['password']);

		$version = $propReportsClient->version();
		$token = $propReportsClient->getToken();
		if ($propReportsClient->getLastError()) {
			return ['message' => $propReportsClient->getLastError()];
		} else {
			$fills = [];
			$fillCols = [];

			$position = [];
			$positionCols = [];

			$adjustments = [];
			$adjustmentsCols = [];

			if (in_array($apiDataType, ['all', 'detailed']))
				$fills = $propReportsClient->fills(null, $account->propReportId, $fromDate, $toDate);

			if (in_array($apiDataType, ['all', 'open']))
				$position = $propReportsClient->positions(null, $account->propReportId, $fromDate, $toDate);

			if (in_array($apiDataType, ['all', 'adjustment']))
				$adjustments = $propReportsClient->adjustments(null, $account->propReportId, $fromDate, $toDate);
			$propReportsClient->logout();


			if (in_array($apiDataType, ['all', 'detailed'])) {
				$fills = array_map("str_getcsv", explode("\n", $fills));
				$fillCols = array_map('strtolower', $fills[0]);
				unset($fills[0]);
				// if ($fills[sizeof($fills)][0] == null)
				// 	unset($fills[sizeof($fills)]);
			}



			// $fills2 = $fills[0];
			// $fills = array_map("str_getcsv", explode("\n", $fills));
			// dd($fills[0]);
			// $colMapper = new DetailedDataMapper;
			// $colMapper->setColumnPosition($fills[0]);
			// dd(json_encode($colMapper->arr));
			if (in_array($apiDataType, ['all', 'open'])) {
				$position = array_map("str_getcsv", explode("\n", $position));
				$positionCols = array_map('strtolower', $position[0]);
				unset($position[0]);
				// if ($position[sizeof($position)][0] == null)
				// 	unset($position[sizeof($position)]);

			}

			if (in_array($apiDataType, ['all', 'adjustment'])) {
				$adjustments = array_map("str_getcsv", explode("\n", $adjustments));
				$adjustmentsCols = array_map('strtolower', $adjustments[0]);
				unset($adjustments[0]);
				// if ($adjustments[sizeof($adjustments)][0] == null)
				// 	unset($adjustments[sizeof($adjustments)]);
			}

			

			if (in_array($apiDataType, ['all', 'detailed']))
				$fillCols = array_map(function ($str) {
					if (strpos(trim($str), ' ')) {
						return str_replace(' ', '_', strtolower(trim($str)));
					} else
						return trim($str);
				}, $fillCols);


			if (in_array($apiDataType, ['all', 'open']))
				$positionCols = array_map(function ($str) {
					if (strpos(trim($str), ' ')) {
						return str_replace(' ', '_', strtolower(trim($str)));
					} else
						return trim($str);
				}, $positionCols);

			if (in_array($apiDataType, ['all', 'adjustment']))
				$adjustmentsCols = array_map(function ($str) {
					if (strpos(trim($str), ' ')) {
						return str_replace(' ', '_', strtolower(trim($str)));
					} else
						return trim($str);
				}, $adjustmentsCols);





			$fillModified = [];
			if (in_array($apiDataType, ['all', 'detailed']))
				foreach ($fills as $key => $value) {
					if (sizeof($value) == sizeof($fillCols)) {
						$temp = [];
						foreach ($value as $key1 => $value1) {
							if ($fillCols[$key1] == "date/time") {
								$date = explode(' ', $value1)[0];
								$time = explode(' ', $value1)[1];
								$temp['date'] = Carbon::parse($date)->toDateString();
								$temp['time'] = $time;

							} else if ($fillCols[$key1] == "account") {
								if ($account->isDuplicateAccount) {
									$temp['accountid'] = $account->accountid;
								} else {
									$temp['accountid'] = $value1;

								}
							} else if ($fillCols[$key1] == "b/s")
								$temp['type'] = $value1;
							else if ($fillCols[$key1] == "qty")
								$temp['qty'] = (int) $value1;
							else
								$temp[$fillCols[$key1]] = $value1;

						}
						$temp['userId'] = null;
						$temp['created_at'] = new MongoDB\BSON\UTCDateTime(time() * 1000);
						$temp['updated_at'] = new MongoDB\BSON\UTCDateTime(time() * 1000);
						$temp['transactionId'] = $sessionId;
						$fillModified[] = $temp;
					}
				}


			$positionModified = [];
			if (in_array($apiDataType, ['all', 'open']))
				foreach ($position as $key => $value) {
					if (sizeof($value) == sizeof($positionCols)) {
						$temp = [];
						foreach ($value as $key1 => $value1) {
							if ($positionCols[$key1] == 'date')
								$temp[$positionCols[$key1]] = Carbon::parse($value1)->toDateString();
							else if ($positionCols[$key1] == "account") {
								if ($account->isDuplicateAccount) {
									$temp['accountid'] = $account->accountid;
								} else {
									$temp['accountid'] = $value1;

								}
							} else if ($positionCols[$key1] == "close")
								$temp['closeprice'] = $value1;
							else
								$temp[$positionCols[$key1]] = $value1;
						}
						// $temp['userId'] = null;
						$temp['created_at'] = new MongoDB\BSON\UTCDateTime(time() * 1000);
						$temp['updated_at'] = new MongoDB\BSON\UTCDateTime(time() * 1000);
						$temp['transactionId'] = $sessionId;
						$positionModified[] = $temp;

					}
				}


			$adjustmentModified = [];
			if (in_array($apiDataType, ['all', 'adjustment']))
				foreach ($adjustments as $key => $value11) {
					// if(sizeof($value)>=5 && $value['comment']!="Daily Interest"){
					$value = [];
					foreach ($value11 as $key1 => $value1) {
						if ($adjustmentsCols[$key1] == "account") {
							if ($account->isDuplicateAccount) {
								$value['accountid'] = $account->accountid;
							} else {
								$value['accountid'] = $value1;
							}
						} else
							$value[$adjustmentsCols[$key1]] = $value1;
					}
					// 	$adjustmentModified[] = $value;

					// }



					if (sizeof($value) >= 5 && $value['comment'] != "Daily Interest") {
						$debitAdj = 0;
						$creditAdj = 0;
						if ($value['amount'] > 0)
							$creditAdj = $value['amount'];
						else
							$debitAdj = abs($value['amount']);
						// if($value['amount']!=0){
						if ($this->getTechFeeMatch($value['comment'], $value['category'])) {
							$alreadyExists = false;
							foreach ($adjustmentModified as $adkey => $advalue) {
								if ($advalue['date'] == Carbon::parse($value['date']) && $advalue['category'] == 'Tech Fee' && $advalue['comment'] == 'Tech Fee') {
									$adjustmentModified[$adkey]['debit'] += $debitAdj;
									$adjustmentModified[$adkey]['credit'] += $creditAdj;

									$alreadyExists = true;
									break;
								}
							}
							if (!$alreadyExists) {
								$value['created_at'] = new MongoDB\BSON\UTCDateTime(time() * 1000);
								$value['updated_at'] = new MongoDB\BSON\UTCDateTime(time() * 1000);
								$value['transactionId'] = $sessionId;
								$value['date'] = Carbon::parse($value['date'])->toDateString();
								$value['category'] = 'Tech Fee';
								$value['comment'] = 'Tech Fee';
								$value['debit'] = $debitAdj;
								$value['credit'] = $creditAdj;
								$value['verified'] = 'Verified';

								$adjustmentModified[sizeof($adjustmentModified)] = $value; /*[
'accountid' => $value[1],
// 'date' => Carbon::parse($value[0])->toDateString(),
'date' => Carbon::parse($value[0]),
'category' =>  'Tech Fee',
'comment' =>  'Tech Fee',
'debit' => $debitAdj,
'credit' => $creditAdj,
'verified' => 'Verified',
'created_at' => new MongoDB\BSON\UTCDateTime(time()*1000),
'updated_at' => new MongoDB\BSON\UTCDateTime(time()*1000),
'transactionId' => $sessionId,
];*/
							}
						} else {

							$value['created_at'] = new MongoDB\BSON\UTCDateTime(time() * 1000);
							$value['updated_at'] = new MongoDB\BSON\UTCDateTime(time() * 1000);
							$value['transactionId'] = $sessionId;
							$value['date'] = Carbon::parse($value['date'])->toDateString();
							$value['debit'] = $debitAdj;
							$value['credit'] = $creditAdj;
							$value['verified'] = 'Verified';

							$adjustmentModified[sizeof($adjustmentModified)] = $value; /*[
'accountid' => $value[1],
// 'date' => Carbon::parse($value[0])->toDateString(),
'date' => Carbon::parse($value[0]),
'category' =>  $value[2],
'comment' =>  $value[4],
'debit' => $debitAdj,
'credit' => $creditAdj,
'verified' => 'Verified',
'created_at' => new MongoDB\BSON\UTCDateTime(time()*1000),
'updated_at' => new MongoDB\BSON\UTCDateTime(time()*1000),
'transactionId' => $sessionId,
];*/
						}
						// }
					}
				}
			if (in_array($apiDataType, ['all', 'adjustment']))
				usort($adjustmentModified, array($this, 'compareByDate'));
			// if (in_array($apiDataType, ['all']))
			// 	return [
			// 		'message' => 'successTest',
			// 		'data' => [
			// 			$fillModified,
			// 			$positionModified,
			// 			$adjustmentModified
			// 		],
			// 		'header' => [
			// 			$fillCols,
			// 			$positionCols,
			// 			$adjustmentsCols,
			// 		],
			// 		'clientDetails' => [
			// 			$version,
			// 			$token
			// 		]
			// 	];
			return [
				'message' => 'success',
				'data' => [
					$fillModified,
					$positionModified,
					$adjustmentModified
				]
			];
		}
	}


	public function fetchReportFromServerByAccount_Date($account, $date, $sessionId = null, $apiDataType = 'all')
	{
		$account = TradeAccount::where('_id', $account)->first();
		$apiDetails = $account->getApiDetails();
		// return $account->propReportId;

		$fromDate = Carbon::parse($date['fromDate'])->toDateString();
		$toDate = Carbon::parse($date['toDate'])->toDateString();
		// return $fromDate;
		$propReportsClient = new PropReportsApiClient($apiDetails['url']);
		$propReportsClient->setCredentials($apiDetails['username'], $apiDetails['password']);

		$version = $propReportsClient->version();
		$token = $propReportsClient->getToken();
		if ($propReportsClient->getLastError()) {
			return ['message' => $propReportsClient->getLastError()];
		} else {
			$cashRows = [];
			$cashCols = [];

			if (in_array($apiDataType, ['all', 'summaryByDate']))
				$cashRows = $propReportsClient->reports(null, $account->propReportId, $fromDate, $toDate);

			$propReportsClient->logout();

			// return [
			// 	'message' => 'successTest',
			// 	'data' => [
			// 		$fills,
			// 		$position,
			// 		$adjustments
			// 	],
			// 	'clientDetails' => [
			// 		$version,
			// 		$token
			// 	]
			// ];

			if (in_array($apiDataType, ['all', 'summaryByDate'])) {
				$cashRows = array_map("str_getcsv", explode("\n", $cashRows));
				$cashCols = array_map('strtolower', $cashRows[0]);
				unset($cashRows[0]);
			}

			if (in_array($apiDataType, ['all', 'summaryByDate']))
				$cashCols = array_map(function ($str) {
					if (strpos(trim($str), ' ')) {
						return str_replace(' ', '_', strtolower(trim($str)));
					} else
						return trim($str);
				}, $cashCols);


			$cashModified = [];
			if (in_array($apiDataType, ['all', 'summaryByDate']))
				foreach ($cashRows as $key => $value) {
					if (sizeof($value) > 20) {
						$temp = [];
						foreach ($value as $key1 => $value1) {
							if ($cashCols[$key1] == "date/time") {
								$date = explode(' ', $value1)[0];
								$time = explode(' ', $value1)[1];
								$temp['date'] = Carbon::parse($date)->toDateString();
								$temp['time'] = $time;

							} else if ($cashCols[$key1] == "account") {
								if ($account->isDuplicateAccount) {
									$temp['accountid'] = $account->accountid;
								} else {
									$temp['accountid'] = $value1;

								}
							} else if ($cashCols[$key1] == "b/s")
								$temp['type'] = $value1;
							else if ($cashCols[$key1] == "qty")
								$temp['qty'] = (int) $value1;
							else
								$temp[$cashCols[$key1]] = $value1;

						}
						$temp['userId'] = null;
						$temp['created_at'] = new MongoDB\BSON\UTCDateTime(time() * 1000);
						$temp['updated_at'] = new MongoDB\BSON\UTCDateTime(time() * 1000);
						$temp['transactionId'] = $sessionId;
						$cashModified[] = $temp;
					}
				}


			return [
				'message' => 'success',
				'data' => $cashModified
			];
		}
	}


	public function compareByDate($a, $b)
	{
		return strnatcmp($a['date'], $b['date']);
	}
}