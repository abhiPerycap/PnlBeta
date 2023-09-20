<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Dreport;
use App\Models\User;
use App\Models\TradeAccount;
use App\Models\Role;
use App\Models\Oreport;
use App\Models\Areport;
use App\Models\ReportGroup;
use App\Models\UserData;
use App\Models\CashData;
use App\Models\Adjustment;
use App\Models\Adjustmentlog;
use App\Models\Masteradjustment;
use App\Models\PreviousDayOpen;
use App\Models\Closedata;
use App\Models\DefaultCharges;
use DB;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

class ReportViewerController extends Controller
{


	public $operatingAccounts = [];
	public $preferenceCols = [];
	public $sumCols = ['qty' => 0, 'price' => 0];
	public $headerCols = [];
	public $tmpHeaderCols = [];
	public $specimenDetailedHeader = [
		'time' => 'Time',
		'order_id' => 'Order Id',
		'fill_id' => 'Fill Id',
		'route' => 'Route',
		'liq' => 'Liq',
		'type' => '',
		'qty' => 'Qty',
		'price' => 'Price',
		'position' => 'Position',
		'gross' => 'Gross'
	];
	public $excludeHeaderCols = ['_id', 'created_at', 'updated_at', 'currency', 'isin/cusip', 'total', 'net', 'propreports_id', 'transactionId', 'grossRealizedDetails', 'status', 'userId', 'commission'];

	public function index(Request $request)
	{
		if ($request->Has('fromDate') && $request->Has('toDate') && $request->Has('report') && $request->Has('account')) {
			$sdate = Carbon::parse($request->fromDate)->toDateString();
			$edate = Carbon::parse($request->toDate)->toDateString();

			$reportBy = '';
			$account = '';
			$reportVisibleFrom = '';

			if (isset($request->account)) {
				$reportBy = (strpos($request->account, 'acc_') !== false) ? 'accountid' : 'userId';
				$account = ($reportBy == 'userId') ? User::find($request->account) : TradeAccount::where('accountid', explode('_', $request->account)[1])->first();
				if (isset($account->reportVisibleFrom) && $account->reportVisibleFrom != '' && $account->reportVisibleFrom != null) {
					$reportVisibleFrom = Carbon::parse($account->reportVisibleFrom);
				}
				$account = ($reportBy == 'userId') ? $account->_id : $account->accountid;

			}
			if ($reportBy == 'accountid') {

				// $pdoDate = PreviousDayOpen::where('accountid', $account)->orderBy('date')->first();
				// if (isset($pdoDate)) {
				// 	if (Carbon::parse($request->fromDate) <= Carbon::parse($pdoDate['generatedOn'])) {
				// 		$sdate = Carbon::parse($pdoDate['generatedOn']);
				// 		$sdate = $sdate->addDay()->toDateString();
				// 	}
				// 	if (Carbon::parse($request->toDate) <= Carbon::parse($pdoDate['generatedOn'])) {
				// 		$edate = Carbon::parse($pdoDate['generatedOn']);
				// 		$edate = $edate->addDay()->toDateString();
				// 	}
				// }
				if ($reportVisibleFrom != '') {
					if (Carbon::parse($sdate) < $reportVisibleFrom) {
						$sdate = $reportVisibleFrom->toDateString();
						// $sdate = $sdate->addDay()->toDateString();
					}
					if (Carbon::parse($edate) < $reportVisibleFrom) {
						$edate = $reportVisibleFrom->toDateString();
						// $sdate = $sdate->addDay()->toDateString();
					}

				}
			}

			if ($reportBy == 'userId' && $reportVisibleFrom != '') {

				if (Carbon::parse($sdate) < $reportVisibleFrom) {
					$sdate = $reportVisibleFrom->toDateString();
					// $sdate = $sdate->addDay()->toDateString();
				}
				if (Carbon::parse($edate) < $reportVisibleFrom) {
					$edate = $reportVisibleFrom->toDateString();
					// $sdate = $sdate->addDay()->toDateString();
				}
			}

			$group = null;

			if ($request->Has('group'))
				$group = $request->group;

			// if($this->checkIfAdjustmentManagedForSelectedAccount($account, $sdate)){
			if (true) {
				switch ($request->report) {
					case 'detailed':
						return $this->getDetailedReport($sdate, $edate, $account, $reportBy);
						break;

					case 'open':
						return $this->getOpenReport($sdate, $edate, $account, $reportBy);
						break;

					case 'adjustments':
						return $this->getAdjustmentReport($sdate, $edate, $account, $reportBy);
						break;
					case 'summaryByDate':
						return $this->getSummaryByDateReport($sdate, $edate, $account, $reportBy);
						break;
					case 'summaryByMonth':
						return $this->getSummaryByMonthReport($sdate, $edate, $account, $reportBy);
						break;

					case 'tbd':
						return $this->getTotalByDateReport($sdate, $edate, $account, $reportBy);
						break;

					case 'tba':
						return $this->getTotalByAccountReport($sdate, $edate, $group);
						break;

					case 'tbu':
						return $this->getTotalByAccountReport($sdate, $edate, $group, 'users');
						break;

					case 'tbs':
						return $this->getTotalBySymbolReport($sdate, $edate, $account, $reportBy);
						break;

					case 'gsbd':
						return $this->getGroupSummaryByDateReport($sdate, $edate, $group);
						break;

					default:
						// code...
						break;
				}

			} else {
				return response()->json(['message' => 'Please Manage Adjustment[s] for the selected Account and Selected Time Period first'], 200);
			}
		} else {
			return response()->json(['message' => 'Please Select all Parameters to Generate Report'], 200);
		}
	}

	public function checkIfAdjustmentManagedForSelectedAccount($accountid, $sdate)
	{
		$masterAdjObj = new \App\Http\Controllers\MasterAdjustmentController;
		$response = $masterAdjObj->getAdjustmentNotification($accountid);
		$response = (array) $response;
		if (isset($response['original']['data']) && sizeof($response['original']['data']) > 0)
			return false;
		else {
			if (Adjustmentlog::where('effectiveFor', $accountid)->where('effectiveFrom', '<=', $sdate)->count() > 0)
				return true;
			else
				return false;
		}
	}

	public function formatBlankValToZero($value)
	{
		if ($value == '')
			return 0;
		else
			return $value;
	}

	public function addPreferencesToData($data, $account, $reportBy = 'accountid')
	{
		$obj = null;
		if ($reportBy == 'accountid') {
			$obj = TradeAccount::where('accountid', $account)->first();
		} else {
			$obj = User::where('_id', $account)->first();
		}


		if ($obj != null) {
			$defaultPrefValues = DefaultCharges::first();
			$dates = array_values(array_unique($data->pluck('date')->toArray()));
			$prefDatas = [];
			foreach ($dates as $date) {
				$prefDatas[$date] = $obj->getPreferenceByDate($date);
			}
			// return $prefDatas;
			foreach ($data as $key => $dataRow) {
				// $preferences = $prefDatas[$dataRow['date']];
				if (isset($prefDatas[$dataRow['date']]) && sizeof($prefDatas[$dataRow['date']]) > 0) {
					foreach ($prefDatas[$dataRow['date']] as $prefKey => $prefValue) {
						if (isset($dataRow[$prefKey]))
							if ($prefKey == 'comm')
								$data[$key][$prefKey] = $data[$key]['qty'] * $prefValue;
							else
								$data[$key][$prefKey] = $data[$key][$prefKey] * $prefValue;
					}

				} else {
					if (isset($defaultPrefValues) && $defaultPrefValues != null && sizeof($defaultPrefValues['preferenceCols']) > 0) {
						foreach ($defaultPrefValues['preferenceCols'] as $prefKey => $prefValue) {
							if (isset($dataRow[$prefKey]))
								if ($prefKey == 'comm')
									$data[$key][$prefKey] = $data[$key]['qty'] * $prefValue;
								else
									$data[$key][$prefKey] = $data[$key][$prefKey] * $prefValue;
						}
					}
				}
			}
			return $data;
		} else {
			return $data;
		}

	}


	public function getDetailedReport($sdate, $edate, $account, $reportBy = 'accountid', $isFunctionCall = false, $addPreference = true, $excelExport = false)
	{

		$data = Dreport::where('date', '>=', $sdate)->where('date', '<=', $edate)->where($reportBy, $account)->get();

		# THIS SECTION BAKES USERDATA AND FORMATS AS DREPORT DATA AND ADDS WITH $data VARIABLE
		$userTradeData = [];
		$userTradeDataAccounts = [];
		if ($reportBy != 'accountid') {
			$userTradeData = UserData::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('user_id', $account)->where('status', 'Validated')->get();
			$temp = [];
			$userTradeDataDateWiseAccounts = [];
			if ($userTradeData->count() > 0) {
				foreach ($userTradeData as $key => $value) {
					if (!in_array($value['accountid'], $userTradeDataAccounts)) {
						$userTradeDataAccounts[] = $value['accountid'];
					}
					$userTradeDataDateWiseAccounts[$value['date']] = $value['accountid'];
					$temp[$value['date']][$value['symbol']][] = $value;

				}


				$userTradeData = [];
				$accountDreport = [];
				$dReportHeaderCols = [];
				$dReportPrefCols = [];
				foreach ($userTradeDataDateWiseAccounts as $dateKey => $acc) {
					$rvObj = new ReportViewerController();
					$reportTemp = $rvObj->getDetailedReport($dateKey, $dateKey, $acc, 'accountid', true, false);
					$accountDreport[$dateKey] = $reportTemp['sumRows'][$dateKey];
					$dReportHeaderCols[$acc] = array_fill_keys(array_keys($reportTemp['headingRow']), 0);
					$dReportPrefCols[$acc] = array_keys($reportTemp['prefCols'][$acc]);
				}

				foreach ($temp as $dateKey => $dataSet1) {
					foreach ($dataSet1 as $symbolKey => $dataSet2) {
						foreach ($dataSet2 as $key => $dataSet) {
							// $cols = [];
							// $dataSet = $dataSet2[0];
							$cols = $dReportHeaderCols[$dataSet['accountid']];
							$cols['accountid'] = $dataSet['accountid'];
							$cols['date'] = $dataSet['date'];
							$cols['symbol'] = $dataSet['symbol'];
							$cols['gross'] = $dataSet['grossPnl'];
							// $cols['net'] = $dataSet['grossPnl'];
							$cols['qty'] = $dataSet['quantity'];
							$cols['position'] = 0;
							$cols['_id'] = $dataSet['_id'];
							$cols['userId'] = $account;
							$cols['propreports_id'] = '';
							$cols['created_at'] = $dataSet['created_at'];
							$cols['updated_at'] = $dataSet['updated_at'];
							$cols['grossRealizedDetails'] = [];
							$cols['commission'] = 0;
							$cols['currency'] = '';
							$cols['isin/cusip'] = '';
							$cols['type'] = ($dataSet['grossPnl'] < 0 ? 'S' : 'B');
							$cols['transactionId'] = '';
							$cols['status'] = '';

							foreach ($dReportPrefCols[$dataSet['accountid']] as $prefCol) {
								$cols[$prefCol] = $accountDreport[$dateKey][$symbolKey]['total'][$prefCol] * ($cols['qty'] / $accountDreport[$dateKey][$symbolKey]['total']['qty']);
								$cols['net'] -= $cols[$prefCol];
							}
							$drObj = new Dreport();
							foreach ($cols as $key => $value) {
								$drObj[$key] = $value;
							}
							$userTradeData[] = $drObj;

						}
					}
				}
				$data = $data->merge($userTradeData);
				// if(sizeof($data)>0){
				// }else{
				// 	$data = $userTradeData;
				// }
			}
			// return [
			// 	'userData' => $userTradeData,
			// 	'dReportHeaderCols' => $dReportHeaderCols,
			// 	'dReportPrefCols' => $dReportPrefCols,
			// 	'accountSumRows' => $accountDreport,
			// 	'dateWiseAccounts' => $userTradeDataDateWiseAccounts
			// ];
		}

		#END

		// return [$sdate, $edate, $account, $reportBy, $data];
		$oReportData = [];
		if (sizeof($data) > 0) {

			if ($addPreference)
				$data = $this->addPreferencesToData($data, $account, $reportBy);

			# Getting all the available tradeAccounts and their Charges Columns for the Selected Time Period

			$operatingAccounts = Dreport::distinct()->where('date', '>=', $sdate)->where('date', '<=', $edate)->where($reportBy, $account)->get(['accountid'])->toArray();
			if (sizeof($operatingAccounts) > 0) {
				$tmp = [];
				foreach ($operatingAccounts as $value) {
					$tmp[] = $value[0];
				}
				$operatingAccounts = array_unique($tmp);
			}
			$operatingAccounts = array_unique(array_merge(($operatingAccounts), $userTradeDataAccounts));
			array_map(function ($arr) {
				if (!in_array($arr, $this->operatingAccounts)) {

					# Adding The TradeAccount for this Session
					$this->operatingAccounts[] = strval($arr);

					# Getting the Charges Columns from the corresponding broker
					$charges = TradeAccount::where('accountid', strval($arr))->first();
					if ($charges->isDuplicateAccount) {
						$charges = $charges->getParentAccount()->broker->toArray()['charges'];
					} else
						$charges = $charges->broker->toArray()['charges'];

					#adding the Charges to the SumColumn List and HeaderColumn List
					foreach ($charges as $cKey => $cValue) {

						if (!array_key_exists($cKey, $this->sumCols))
							$this->sumCols[$cKey] = 0;

						if (!array_key_exists($cKey, $this->tmpHeaderCols))
							$this->tmpHeaderCols[$cKey] = $cValue;

					}

					# Preserving account wise Charges List for Future reference
					$this->preferenceCols[strval($arr)] = $charges;
				}
			}, $operatingAccounts);

			# End

			# Adding Remaining Columns to the HeaderColumn List from Detailed Report

			foreach ($data[0]->toArray() as $key => $value) {
				if (!array_key_exists($key, $this->tmpHeaderCols)) {
					if (!in_array($key, $this->excludeHeaderCols)) {
						$colName = trim(str_replace('_', ' ', $key));
						$colName = ucwords($colName);
						$this->headerCols[$key] = $colName;

					}
				}
			}
			$this->tmpHeaderCols['net'] = 'Net';
			$this->sumCols['net'] = 0;
			$this->headerCols = array_merge($this->specimenDetailedHeader, $this->tmpHeaderCols);





			$dReportSum = [];

			$data = $data->groupBy(function ($item) {
				return Carbon::createFromFormat('Y-m-d', $item->date)->format('Y-m-d');
			});

			foreach ($data as $key => $value) {
				$data[$key] = $value->groupBy(function ($item) {
					return $item->symbol;
				});
			}

			$this->sumCols = array_merge(['orders' => 0, 'fills' => 0, 'qty' => 0, 'price' => 0, 'position' => 0, 'gross' => 0], $this->sumCols);

			foreach ($data as $dateKey => $subSet1) {
				$dailyPL = 0;
				foreach ($subSet1 as $symbol => $subSet2) {
					$dReportSum[$dateKey][$symbol]['bought'] = $this->sumCols;
					$dReportSum[$dateKey][$symbol]['sold'] = $this->sumCols;
					$dReportSum[$dateKey][$symbol]['total'] = $this->sumCols;

					$bFills = 0;
					$sFills = 0;
					$bOrderArr = [];
					$sOrderArr = [];
					$remainingPosition = 0;
					foreach ($subSet2 as $datSetIndex => $dataSet) {

						$rowNet = 0;

						$type = ($dataSet['type'] != 'B') ? 'sold' : 'bought';

						if ($dataSet['type'] != 'B') {
							$sFills++;
							if (!in_array($dataSet['order_id'], $sOrderArr))
								$sOrderArr[] = $dataSet['order_id'];
						} else {
							$bFills++;
							if (!in_array($dataSet['order_id'], $bOrderArr))
								$bOrderArr[] = $dataSet['order_id'];
						}

						$remainingPosition = $dataSet['position'];
						foreach ($dataSet->toArray() as $colName => $colValue) {
							if (array_key_exists($colName, $dReportSum[$dateKey][$symbol][$type])) {
								if ($colName == 'price') {
									$dReportSum[$dateKey][$symbol][$type][$colName] = $dReportSum[$dateKey][$symbol][$type][$colName] + ($colValue * $dataSet['qty']);
								} else {
									$dReportSum[$dateKey][$symbol][$type][$colName] += $colValue;
								}
							}
							if (array_key_exists($colName, ($this->tmpHeaderCols))) {
								$rowNet += $colValue;
							}
						}
						$data[$dateKey][$symbol][$datSetIndex]['net'] = $dataSet->gross - $rowNet;
						$dReportSum[$dateKey][$symbol][$type]['net'] = $dReportSum[$dateKey][$symbol][$type]['net'] + $data[$dateKey][$symbol][$datSetIndex]['net'];

					}

					// if($dReportSum[$dateKey][$symbol]['bought']['qty']==0)
					// return $dReportSum[$dateKey][$symbol];

					$dReportSum[$dateKey][$symbol]['bought']['fills'] = $bFills;
					$dReportSum[$dateKey][$symbol]['sold']['fills'] = $sFills;
					$dReportSum[$dateKey][$symbol]['bought']['orders'] = sizeof($bOrderArr);
					$dReportSum[$dateKey][$symbol]['sold']['orders'] = sizeof($sOrderArr);

					if ($dReportSum[$dateKey][$symbol]['bought']['qty'] != 0)
						$dReportSum[$dateKey][$symbol]['bought']['price'] = $dReportSum[$dateKey][$symbol]['bought']['price'] / $dReportSum[$dateKey][$symbol]['bought']['qty'];
					else
						$dReportSum[$dateKey][$symbol]['bought']['price'] = 0;

					if ($dReportSum[$dateKey][$symbol]['sold']['qty'] != 0)
						$dReportSum[$dateKey][$symbol]['sold']['price'] = $dReportSum[$dateKey][$symbol]['sold']['price'] / $dReportSum[$dateKey][$symbol]['sold']['qty'];
					else
						$dReportSum[$dateKey][$symbol]['sold']['price'] = 0;

					foreach ($dReportSum[$dateKey][$symbol]['total'] as $key => $value) {
						// if(!in_array($key, ['orders', 'fills', 'position', 'gross']))
						$dReportSum[$dateKey][$symbol]['total'][$key] = $dReportSum[$dateKey][$symbol]['bought'][$key] + $dReportSum[$dateKey][$symbol]['sold'][$key];
					}

					$dReportSum[$dateKey][$symbol]['bought']['gross'] = -1 * ($dReportSum[$dateKey][$symbol]['bought']['qty'] * $dReportSum[$dateKey][$symbol]['bought']['price']);
					$dReportSum[$dateKey][$symbol]['sold']['gross'] =
						$dReportSum[$dateKey][$symbol]['sold']['qty'] * $dReportSum[$dateKey][$symbol]['sold']['price'];
					$dReportSum[$dateKey][$symbol]['total']['gross'] =
						$dReportSum[$dateKey][$symbol]['sold']['gross'] + $dReportSum[$dateKey][$symbol]['bought']['gross'];

					# IDENTIFYING USERDATA OR DREPORT DATA & ACCORDINGLY CALCULATING THE GROSS

					if ($data[$dateKey][$symbol][0]['gross'] != 0 && sizeof($data[$dateKey][$symbol][0]['grossRealizedDetails']) == 0) {
						foreach ($data[$dateKey][$symbol] as $dv) {
							if ($dv['type'] == 'B')
								$dReportSum[$dateKey][$symbol]['bought']['gross'] += $dv['gross'];
							else
								$dReportSum[$dateKey][$symbol]['sold']['gross'] += $dv['gross'];

							$dReportSum[$dateKey][$symbol]['total']['gross'] += $dv['gross'];
						}

					}

					# END

					$dReportSum[$dateKey][$symbol]['bought']['position'] = '';
					$dReportSum[$dateKey][$symbol]['sold']['position'] = '';
					$dReportSum[$dateKey][$symbol]['total']['position'] = ($remainingPosition != 0) ? $remainingPosition : '';

					unset($this->tmpHeaderCols['net']);


					$dReportSum[$dateKey][$symbol]['bought']['net'] = $dReportSum[$dateKey][$symbol]['bought']['gross'];
					$dReportSum[$dateKey][$symbol]['sold']['net'] = $dReportSum[$dateKey][$symbol]['sold']['gross'];
					$dReportSum[$dateKey][$symbol]['total']['net'] = $dReportSum[$dateKey][$symbol]['total']['gross'];

					foreach ($this->tmpHeaderCols as $key => $value) {
						$dReportSum[$dateKey][$symbol]['bought']['net'] = $dReportSum[$dateKey][$symbol]['bought']['net'] - $dReportSum[$dateKey][$symbol]['bought'][$key];
						$dReportSum[$dateKey][$symbol]['sold']['net'] = $dReportSum[$dateKey][$symbol]['sold']['net'] - $dReportSum[$dateKey][$symbol]['sold'][$key];
						$dReportSum[$dateKey][$symbol]['total']['net'] = $dReportSum[$dateKey][$symbol]['total']['net'] - $dReportSum[$dateKey][$symbol]['total'][$key];

					}
					// return $oReportData;
					$oReportData = Oreport::where('date', '<', $dateKey)->where($reportBy, $account)->where('symbol', $symbol)->orderBy('date', 'desc')->get();
					$previousDayOverNight = 0;

					if (sizeof($oReportData) > 0) {
						$oReportData = $oReportData->groupBy(function ($item) {
							return Carbon::createFromFormat('Y-m-d', $item->date)->format('Y-m-d');
						});

						$oReportData = $oReportData->first();

						$isCloseDataExists = Closedata::where('date', '>', $oReportData[0]->date)->where('date', '<', $dateKey)->where($reportBy, $account)->where('symbol', $symbol)->get()->count();

						if ($isCloseDataExists == 0) {
							foreach ($oReportData as $orKey => $orValue) {
								$previousDayOverNight += $orValue['cost'];
							}
						}

					}

					$oReportCurrentData = Oreport::where('date', $dateKey)->where($reportBy, $account)->where('symbol', $symbol)->orderBy('date', 'desc')->get();
					$currentDayOverNight = 0;

					if (sizeof($oReportCurrentData)) {
						foreach ($oReportCurrentData as $cdonvalue) {
							$currentDayOverNight += $cdonvalue['cost'];
						}
					}

					$totalOvernight = $currentDayOverNight - $previousDayOverNight;

					if ($totalOvernight != 0) {
						$dReportSum[$dateKey][$symbol]['overnight'] = $totalOvernight;

						$dReportSum[$dateKey][$symbol]['total']['gross'] = $dReportSum[$dateKey][$symbol]['total']['gross'] + $dReportSum[$dateKey][$symbol]['overnight'];
						$dReportSum[$dateKey][$symbol]['total']['net'] = $dReportSum[$dateKey][$symbol]['total']['net'] + $dReportSum[$dateKey][$symbol]['overnight'];
					}
				}
			}

			$dailyTotal = [];
			$allTotal = [];

			foreach ($dReportSum as $date => $set1) {
				foreach ($set1 as $symbol => $dataSet) {

					foreach ($dataSet['total'] as $key => $value) {
						if (!isset($dailyTotal[$date][$key]))
							$dailyTotal[$date][$key] = $this->formatBlankValToZero($value);
						else
							$dailyTotal[$date][$key] = $dailyTotal[$date][$key] + $this->formatBlankValToZero($value);
					}
				}
				foreach ($dailyTotal[$date] as $key => $value) {
					if (!isset($allTotal[$key]))
						$allTotal[$key] = $value;
					else
						$allTotal[$key] = $allTotal[$key] + $value;
				}
			}

			// return [
			// 	'header' => $this->headerCols,
			// 	// 'sumCols' => $this->sumCols,
			// 	'data' => $data,
			// 	'sum' => $dReportSum,
			// 	'prefCols' => $this->preferenceCols,
			// 	// 'Accounts' => $this->operatingAccounts,
			// ];

			if (sizeof($dReportSum) > 0) {
				$revisedDailyTotal = array_fill_keys(array_keys($allTotal), 0);
				$revisedAllTotal = array_fill_keys(array_keys($allTotal), 0);

				foreach ($dReportSum as $dateKey => $dataSet1) {

					$accountid = '';
					$mappingType = '';
					if ($reportBy != 'accountid') {
						$accountid = User::find($account);
						$mappingType = $accountid->getMapData($dateKey)['role'];
						$accountid = $accountid->getAccountId($dateKey);
					}
					foreach ($dataSet1 as $symbolKey => $dataSet2) {
						$userData = [];
						if ($mappingType == 'master')
							$userData = UserData::where('date', $dateKey)->where('symbol', $symbolKey)->where('accountid', $accountid)->where('status', 'Validated')->get();
						if (isset($userData) && sizeof($userData) > 0) {
							$grossTotal = $dataSet2['total'];
							$dReportSum[$dateKey][$symbolKey]['grossTotal'] = $grossTotal;
							$dReportSum[$dateKey][$symbolKey]['total'] = array_fill_keys(array_keys($grossTotal), 0);
							$totalSubQty = 0;
							$totalSubGross = 0;
							foreach ($userData as $key => $value) {
								$totalSubQty += $value['quantity'];
								$totalSubGross += $value['grossPnl'];
							}

							$dReportSum[$dateKey][$symbolKey]['sub'] = $dReportSum[$dateKey][$symbolKey]['total'];

							$dReportSum[$dateKey][$symbolKey]['sub']['qty'] = $totalSubQty;
							$dReportSum[$dateKey][$symbolKey]['total']['qty'] = $dReportSum[$dateKey][$symbolKey]['grossTotal']['qty'] - $dReportSum[$dateKey][$symbolKey]['sub']['qty'];
							$dReportSum[$dateKey][$symbolKey]['sub']['gross'] = $totalSubGross;
							$dReportSum[$dateKey][$symbolKey]['sub']['net'] = $totalSubGross;
							$dReportSum[$dateKey][$symbolKey]['total']['gross'] = $dReportSum[$dateKey][$symbolKey]['grossTotal']['gross'] - $dReportSum[$dateKey][$symbolKey]['sub']['gross'];
							$dReportSum[$dateKey][$symbolKey]['total']['net'] = $dReportSum[$dateKey][$symbolKey]['total']['gross'];

							foreach ($dReportSum[$dateKey][$symbolKey]['grossTotal'] as $key => $value) {
								if (!in_array($key, ['position', 'fills', 'orders', 'position', 'price'])) {
									if (in_array($key, ['qty', 'gross', 'net'])) {
										// $dReportSum[$dateKey][$symbolKey]['total'][$key] = $dReportSum[$dateKey][$symbolKey]['grossTotal'][$key] - $dReportSum[$dateKey][$symbolKey]['sub'][$key];
									} else {
										$dReportSum[$dateKey][$symbolKey]['sub'][$key] = $dReportSum[$dateKey][$symbolKey]['grossTotal'][$key] * ($dReportSum[$dateKey][$symbolKey]['sub']['qty'] / $dReportSum[$dateKey][$symbolKey]['grossTotal']['qty']);
										$dReportSum[$dateKey][$symbolKey]['total'][$key] = $dReportSum[$dateKey][$symbolKey]['grossTotal'][$key] - $dReportSum[$dateKey][$symbolKey]['sub'][$key];
										$dReportSum[$dateKey][$symbolKey]['sub']['net'] -= $dReportSum[$dateKey][$symbolKey]['sub'][$key];
										$dReportSum[$dateKey][$symbolKey]['total']['net'] -= $dReportSum[$dateKey][$symbolKey]['total'][$key];
									}
								} else
									$dReportSum[$dateKey][$symbolKey]['total'][$key] = $dReportSum[$dateKey][$symbolKey]['grossTotal'][$key];
							}

						}
						foreach ($dReportSum[$dateKey][$symbolKey]['total'] as $key => $value) {
							if (is_numeric($value) && is_numeric($revisedDailyTotal[$key])) {
								$value = $value + 0;
								// if(is_numeric($revisedDailyTotal[$key])){
								$revisedDailyTotal[$key] = $revisedDailyTotal[$key] + 0;
								$revisedDailyTotal[$key] += ($value);
								// }
							} else
								$revisedDailyTotal[$key] = $value;
						}
						// $revisedDailyTotal = $dReportSum[$dateKey][$symbolKey]['total'];
					}
					$dailyTotal[$dateKey] = $revisedDailyTotal;
					foreach ($revisedDailyTotal as $key => $value) {
						if (is_numeric($value) && is_numeric($revisedAllTotal[$key])) {
							$value = $value + 0;
							$revisedAllTotal[$key] = $revisedAllTotal[$key] + 0;
							$revisedAllTotal[$key] += $value;
						} else
							$revisedAllTotal[$key] = $value;
					}
					$revisedDailyTotal = array_fill_keys(array_keys($allTotal), 0);
				}
				$allTotal = $revisedAllTotal;
			}

			if ($isFunctionCall) {
				return [
					'headingRow' => ($this->headerCols),
					// 'headingRow' => array_values($this->headerCols),
					// 'sumCols' => $this->sumCols,
					'rows' => $data,
					'sumRows' => $dReportSum,
					'prefCols' => $this->preferenceCols,
					'dailyTotal' => $dailyTotal,
					'allTotal' => $allTotal,
					// 'Accounts' => $this->operatingAccounts,
				];
			} else {
				return response()->json([
					'message' => 'success',
					'data' => [
						'headingRow' => ($this->headerCols),
						// 'headingRow' => array_values($this->headerCols),
						// 'sumCols' => $this->sumCols,
						'rows' => $data,
						'sumRows' => $dReportSum,
						'prefCols' => $this->preferenceCols,
						'dailyTotal' => $dailyTotal,
						'allTotal' => $allTotal,
						// 'Accounts' => $this->operatingAccounts,
					]
				], 200);
			}


			// return $data;

		} else {

			if ($isFunctionCall)
				return [];
			else
				return response()->json(['message' => 'No Data Found for the Selected Parameters'], 200);
		}
	}

	public function getOpenReport($sdate, $edate, $account, $reportBy = 'accountid', $isFunctionCall = false, $excelExport = false)
	{
		$data = Oreport::where('date', '>=', $sdate)->where('date', '<=', $edate)->where($reportBy, $account)->get();
		$compiledData = [];
		$compiledSumData = [];
		$headerColFormat = [];
		$sumColFormat = ['cost' => 0, 'marketvalue' => 0, 'und' => 0, 'un' => 0];
		if (isset($data) && sizeof($data) > 0) {
			foreach ($data[0]->toArray() as $key => $value) {
				if (!in_array($key, ['_id', 'accountid', 'userId', 'created_at', 'updated_at', 'date'])) {
					$headerColFormat[$key] = '';
				}
			}

			$data = $data->groupBy(function ($item) {
				return $item->date;
			});

			foreach ($data as $key => $value) {
				$data[$key] = $value->groupBy(function ($item) {
					return $item->symbol;
				});
			}
			$data = $data->toArray();
			// return $data;
			$existingDates = [];

			foreach ($data as $date => $dataSet1) {
				$existingDates[] = $date;
				$tempSum['long'] = $sumColFormat;
				$tempSum['short'] = $sumColFormat;
				$tempSum['closed'] = 0;
				$tempSum['total'] = ['und' => 0, 'un' => 0];
				foreach ($dataSet1 as $symbol => $dataSet2) {
					$temp = $headerColFormat;

					$temp['symbol'] = $symbol;
					$temp['ccy'] = $dataSet2[0]['ccy'];
					$temp['spot'] = $dataSet2[0]['spot'];
					foreach ($dataSet2 as $index => $dataRow) {
						foreach ($dataRow as $colName => $value) {
							// code...
							if (!in_array($colName, ['_id', 'ccy', 'spot', 'symbol', 'accountid', 'date', 'userId', 'created_at', 'updated_at'])) {
								if ($temp[$colName] == '')
									$temp[$colName] = $value;
								else
									$temp[$colName] += $value;
							}

						}





					}
					$tempSum[($temp['qty'] > 0) ? 'long' : 'short']['cost'] += $temp['cost'];
					$tempSum[($temp['qty'] > 0) ? 'long' : 'short']['marketvalue'] += $temp['marketvalue'];
					$tempSum[($temp['qty'] > 0) ? 'long' : 'short']['und'] += $temp['und'];
					$tempSum[($temp['qty'] > 0) ? 'long' : 'short']['un'] += $temp['un'];
					$compiledData[$date][] = $temp;
				}

				usort($compiledData[$date], function ($a, $b) {
					return strcmp($a['symbol'], $b['symbol']);
				});

				$closeData = Closedata::raw(function ($collection) use ($date, $reportBy, $account) {
					return $collection->aggregate([
						[
							'$match' => [
								'date' => $date,
								// [
								// 	'$gte' => '2022-01-03',
								// 	'$lte' => '2022-01-03'
								// ],
								$reportBy => $account
							]
						],
						[
							'$group' => [
								'_id' => '$date',
								'total' => [
									'$sum' => '$close'
								]
							]
						],
						[
							'$project' => [
								'closed' => '$total',
								'_id' => '$$REMOVE'
							]
						]
					]);
				});
				// return $closeData[0]['closed'];
				$tempSum['total']['und'] = $tempSum['long']['und'] + $tempSum['short']['und'];
				if (sizeof($closeData) == 1) {
					// $tempSum['closed'] = abs($closeData[0]['closed']);
					$tempSum['closed'] = -1 * ($closeData[0]['closed']);
				}
				$tempSum['total']['und'] = $tempSum['total']['und'] + $tempSum['closed'];
				$tempSum['total']['un'] = $tempSum['long']['un'] + $tempSum['short']['un'];
				$compiledSumData[$date] = $tempSum;
			}

			// Below Code is for Generating Closed Entries for the day when Open Position is Zero

			$remainingCloseData = Closedata::where('date', '>=', $sdate)->where('date', '<=', $edate)->whereNotIn('date', $existingDates)->where($reportBy, $account)->get();
			$remainingCloseData = $remainingCloseData->groupBy(function ($item) {
				return $item->date;
			});
			$rcd = [];
			foreach ($remainingCloseData as $dateKey => $dayCloseSet) {
				$rcd[$dateKey] = 0;
				foreach ($dayCloseSet as $value) {
					$rcd[$dateKey] += ($value['close']);
				}
			}
			foreach ($rcd as $dateKey => $closeValue) {
				$compiledData[$dateKey][] = [];

				// [
				// 	'avgprice' => '',
				// 	'ccy' => '',
				// 	'closeprice' => '',
				// 	'cost' => '',
				// 	'marketvalue' => '',
				// 	'qty' => '',
				// 	'spot' => '',
				// 	'symbol' => '',
				// 	'un' => '',
				// 	'und' => ''
				// ];
				$compiledSumData[$dateKey] = [
					"long" => [
						"cost" => 0,
						"marketvalue" => 0,
						"und" => 0,
						"un" => 0
					],
					"short" => [
						"cost" => 0,
						"marketvalue" => 0,
						"und" => 0,
						"un" => 0
					],
					"closed" => -1 * $closeValue,
					"total" => [
						"und" => -1 * $closeValue,
						"un" => 0
					]
				];
			}
			// return $compiledSumData;

			ksort($compiledData);
			ksort($compiledSumData);

			// End

			if ($isFunctionCall) {
				return [
					'header' => $headerColFormat,
					'rows' => $compiledData,
					'sum' => $compiledSumData,
				];
			} else {
				return response()->json([
					'message' => 'success',
					'data' => [
						'header' => $headerColFormat,
						'rows' => $compiledData,
						'sum' => $compiledSumData,
					]
				], 200);

			}

		} else {
			if ($isFunctionCall)
				return [];
			else
				return response()->json(['message' => 'No Data Found for the Selected Parameters'], 200);
		}

	}

	public function getDailyInterestEntriesByDate($openData, $account, $reportBy = 'accountid')
	{
		$obj = null;
		if ($reportBy == 'accountid') {
			$obj = TradeAccount::where('accountid', $account)->first();
		} else {
			$obj = User::where('_id', $account)->first();
		}

		$diCols = [];
		$noOfDaysInYearForDi = DefaultCharges::all()->isNotEmpty() ? DefaultCharges::all()->first()->toArray()['diYear'] : 365;
		if ($obj != null) {
			if (sizeof($openData) > 0) {
				foreach ($openData['sum'] as $dateKey => $sumValue) {
					if ($sumValue['long']['marketvalue'] > 0) {
						$diValue = $obj->getDailyInterestByDate($dateKey);
						array_push($diCols, [
							'date' => $dateKey,
							'category' => 'Fee: Daily Interest',
							'comment' => 'Daily Interest',
							'debit' => ($diValue * $sumValue['long']['marketvalue']) / $noOfDaysInYearForDi,
							'credit' => 0,
						]);

					}
				}
			}
		}
		return $diCols;
	}


	public function getAdjustmentEntriesByDate($sdate, $edate, $account, $reportBy = 'accountid', $isFunctionCall = false)
	{


		// $account = is_numeric($account)?$account+0:$account;

		$period = CarbonPeriod::create($sdate, $edate);
		$data = Areport::where('date', '>=', $sdate)->where('date', '<=', $edate)->where($reportBy, $account)->get()->toArray();
		$adjCols = [];
		foreach ($period as $date) {

			$data = Areport::where('date', $date->toDateString())->where($reportBy, $account)->get()->toArray();
			// return $data;
			// $adjCategories = array_values(array_unique(Areport::where('date', '>=', $sdate)->where('date', '<=', $edate)->where($reportBy, $account)->get()->pluck('category')->toArray()));

			foreach ($data as $key => $adj) {
				$adjLog = null;

				if ($reportBy != 'accountid') {
					// $mapData = User::find($account)->getMapData($date->toDateString());
					// if(isset($mapData)){
					// 	if($mapData->role=='master')
					// 		$adjLog = Adjustmentlog::where('effectiveFor', $mapData->mappedTo)->where('category', $adj['category'])->where('effectiveFrom', '<=', $date->toDateString())->orderBy('effectiveFrom', 'desc')->first();
					// 	else
					// 		$adjLog = Adjustmentlog::where('effectiveFor', $account)->where('category', $adj['category'])->where('effectiveFrom', '<=', $adj['date'])->orderBy('effectiveFrom', 'desc')->first();
					// }else
					$adjLog = Adjustmentlog::where('effectiveFor', $account)->where('category', explode(': ', $adj['category'])[0])->where('toBeShownAs', explode(': ', $adj['category'])[1])->where('effectiveFrom', '<=', $adj['date'])->orderBy('effectiveFrom', 'desc')->first();
				} else {

					$adjLog = Adjustmentlog::where('effectiveFor', $account)->where('category', explode(': ', $adj['category'])[0])->where('toBeShownAs', explode(': ', $adj['category'])[1])->where('effectiveFrom', '<=', $adj['date'])->orderBy('effectiveFrom', 'desc')->first();
				}
				if (isset($adjLog)) {
					if ($adjLog['effectiveTo'] != '' && $adjLog['effectiveTo'] != null) {
						if (Carbon::parse($adj['date']) <= Carbon::parse($adjLog['effectiveTo'])) {
							if ($adj['debit'] != 0) {
								array_push($adjCols, [
									'date' => $adj['date'],
									'fullCategory' => $adjLog['category'] . ': ' . $adjLog['toBeShownAs'],
									'category' => $isFunctionCall ? $adjLog['toBeShownAs'] : $adjLog['category'] . ': ' . $adjLog['toBeShownAs'],
									'comment' => $adjLog['comment'] != null ? $adjLog['comment'] : $adj['comment'],
									'debit' => ($adjLog['valueType'] == 'manual' ? $adjLog['value'] : $adj['debit']),
									'credit' => 0,
								]);

							} else {
								array_push($adjCols, [
									'date' => $adj['date'],
									'fullCategory' => $adjLog['category'] . ': ' . $adjLog['toBeShownAs'],
									'category' => $isFunctionCall ? $adjLog['toBeShownAs'] : $adjLog['category'] . ': ' . $adjLog['toBeShownAs'],
									'comment' => $adjLog['comment'] != null ? $adjLog['comment'] : $adj['comment'],
									'debit' => 0,
									'credit' => ($adjLog['valueType'] == 'manual' ? $adjLog['value'] : $adj['credit']),
								]);
							}
						}
					} else {
						if ($adj['debit'] != 0) {
							array_push($adjCols, [
								'date' => $adj['date'],
								'fullCategory' => $adjLog['category'] . ': ' . $adjLog['toBeShownAs'],
								'category' => $isFunctionCall ? $adjLog['toBeShownAs'] : $adjLog['category'] . ': ' . $adjLog['toBeShownAs'],
								'comment' => $adjLog['comment'] != null ? $adjLog['comment'] : $adj['comment'],
								'debit' => ($adjLog['valueType'] == 'manual' ? $adjLog['value'] : $adj['debit']),
								'credit' => 0,
							]);

						} else {
							array_push($adjCols, [
								'date' => $adj['date'],
								'fullCategory' => $adjLog['category'] . ': ' . $adjLog['toBeShownAs'],
								'category' => $isFunctionCall ? $adjLog['toBeShownAs'] : $adjLog['category'] . ': ' . $adjLog['toBeShownAs'],
								'comment' => $adjLog['comment'] != null ? $adjLog['comment'] : $adj['comment'],
								'debit' => 0,
								'credit' => ($adjLog['valueType'] == 'manual' ? $adjLog['value'] : $adj['credit']),
							]);
						}

					}


				}
			}
		}


		$data = MasterAdjustment::where('effectiveFor', $account)->where('source', 'manual')->pluck('fullCategory')->toArray();
		$data = array_unique($data);
		foreach ($data as $key => $value) {
			// $category = $value['category'];
			$period = CarbonPeriod::create($sdate, $edate);

			// Iterate over the period
			foreach ($period as $date) {
				$adjLogs = Adjustmentlog::where('effectiveFor', $account)->where('fullCategory', $value)->where('effectiveFrom', '<=', $date->toDateString())->where('source', 'manual')->orderBy('effectiveFrom', 'desc')->get();
				foreach ($adjLogs as $adjLog) {

					if (isset($adjLog)) {
						if ($adjLog['effectiveTo'] != '' && $adjLog['effectiveTo'] != null) {
							if ($date <= Carbon::parse($adjLog['effectiveTo'])) {
								if ($adjLog['frequency'] == 'monthly') {
									if ($adjLog['effectiveDay'] == $date->day && $adjLog['value'] != 0) {
										array_push($adjCols, [
											'date' => $date->toDateString(),
											'fullCategory' => $adjLog['category'] . ': ' . $adjLog['toBeShownAs'],
											'category' => $isFunctionCall ? $adjLog['toBeShownAs'] : $adjLog['category'] . ': ' . $adjLog['toBeShownAs'],
											'comment' => $adjLog['comment'] != null ? $adjLog['comment'] : $adjLog['category'] . ': ' . $adjLog['toBeShownAs'],
											'debit' => ($adjLog['value'] < 0 ? abs($adjLog['value']) : 0),
											'credit' => ($adjLog['value'] > 0 ? abs($adjLog['value']) : 0),
										]);

									}
								}

								if ($adjLog['frequency'] == 'oneTime') {
									if ($adjLog['effectiveFrom'] == $date->toDateString() && $adjLog['value'] != 0) {
										array_push($adjCols, [
											'date' => $date->toDateString(),
											'fullCategory' => $adjLog['category'] . ': ' . $adjLog['toBeShownAs'],
											'category' => $isFunctionCall ? $adjLog['toBeShownAs'] : $adjLog['category'] . ': ' . $adjLog['toBeShownAs'],
											'comment' => $adjLog['comment'] != null ? $adjLog['comment'] : $adjLog['category'] . ': ' . $adjLog['toBeShownAs'],
											'debit' => ($adjLog['value'] < 0 ? abs($adjLog['value']) : 0),
											'credit' => ($adjLog['value'] > 0 ? abs($adjLog['value']) : 0),
										]);

									}
								}
							}
						} else {
							if ($adjLog['frequency'] == 'monthly') {
								if ($adjLog['effectiveDay'] == $date->day && $adjLog['value'] != 0) {
									array_push($adjCols, [
										'date' => $date->toDateString(),
										'fullCategory' => $adjLog['category'] . ': ' . $adjLog['toBeShownAs'],
										'category' => $isFunctionCall ? $adjLog['toBeShownAs'] : $adjLog['category'] . ': ' . $adjLog['toBeShownAs'],
										'comment' => $adjLog['comment'] != null ? $adjLog['comment'] : $adjLog['category'] . ': ' . $adjLog['toBeShownAs'],
										'debit' => ($adjLog['value'] < 0 ? abs($adjLog['value']) : 0),
										'credit' => ($adjLog['value'] > 0 ? abs($adjLog['value']) : 0),
									]);

								}
							}

							if ($adjLog['frequency'] == 'oneTime') {
								if ($adjLog['effectiveFrom'] == $date->toDateString() && $adjLog['value'] != 0) {
									array_push($adjCols, [
										'date' => $date->toDateString(),
										'fullCategory' => $adjLog['category'] . ': ' . $adjLog['toBeShownAs'],
										'category' => $isFunctionCall ? $adjLog['toBeShownAs'] : $adjLog['category'] . ': ' . $adjLog['toBeShownAs'],
										'comment' => $adjLog['comment'] != null ? $adjLog['comment'] : $adjLog['category'] . ': ' . $adjLog['toBeShownAs'],
										'debit' => ($adjLog['value'] < 0 ? abs($adjLog['value']) : 0),
										'credit' => ($adjLog['value'] > 0 ? abs($adjLog['value']) : 0),
									]);

								}
							}

						}
					}
				}
			}
		}

		return $adjCols;

	}


	public function getCompiledOpenReportForAdjustment($sdate, $edate, $account, $reportBy = 'accountid')
	{
		$openReportData = $this->getOpenReport($sdate, $edate, $account, $reportBy, true);


		// return Carbon::parse('2020-11-07')->diffInDays(Carbon::parse('2020-11-08'));
		if (isset($openReportData['sum'])) {
			$openDates = array_keys($openReportData['sum']);
			$prevOpenDate = null;
			foreach ($openDates as $oDate) {
				if ($prevOpenDate != null) {
					if ($prevOpenDate->diffInDays(Carbon::parse($oDate)) > 1) {
						$cd = CloseData::where($reportBy, $account)->where('date', '>', $prevOpenDate->toDateString())->where('date', '<', $oDate)->get();
						if ($cd->count() == 0) {
							$gapPeriod = CarbonPeriod::create($prevOpenDate->copy()->addDay()->toDateString(), Carbon::parse($oDate)->subDay()->toDateString());
							// return $gapPeriod;
							foreach ($gapPeriod as $gapDate) {
								$openReportData['sum'][$gapDate->toDateString()] = $openReportData['sum'][$prevOpenDate->toDateString()];
								$openReportData['sum'][$gapDate->toDateString()]['total']['und'] = 0;
							}
						} else {
							$closeDataDate = $cd->first();
							$gapPeriod = CarbonPeriod::create($prevOpenDate->copy()->addDay()->toDateString(), Carbon::parse($closeDataDate['date'])->subDay()->toDateString());
							// return $gapPeriod;
							foreach ($gapPeriod as $gapDate) {
								$openReportData['sum'][$gapDate->toDateString()] = $openReportData['sum'][$prevOpenDate->toDateString()];
								$openReportData['sum'][$gapDate->toDateString()]['total']['und'] = 0;
							}
						}
					}
				}
				$prevOpenDate = Carbon::parse($oDate);
			}

		}

		$period = CarbonPeriod::create($sdate, $edate);
		foreach ($period as $date) {
			if (!isset($openReportData['sum'][$date->toDateString()])) {
				$openDate = Oreport::where('date', '<', $date->toDateString())->where($reportBy, $account)->orderBy('date', 'DESC')->first();
				if (isset($openDate)) {
					$cd = CloseData::where($reportBy, $account)->where('date', '>', $openDate['date'])->where('date', '<=', $date->toDateString())->get();
					if ($cd->count() == 0) {
						$prevOpenReportData = $this->getOpenReport(
							$openDate['date'],
							$openDate['date'],
							$account,
							$reportBy,
							true
						);
						// foreach ($gapPeriod as $gapDate) {
						$openReportData['sum'][$date->toDateString()] = $prevOpenReportData['sum'][$openDate['date']];
						$openReportData['sum'][$date->toDateString()]['total']['und'] = 0;
						// }
					}

				}

			}
		}
		return $openReportData;
	}

	public function getAdjustmentReport($sdate, $edate, $account, $reportBy = 'accountid', $isFunctionCall = false, $excelExport = false)
	{
		$openReportData = $this->getCompiledOpenReportForAdjustment($sdate, $edate, $account, $reportBy);
		// $openReportData =  $this->getOpenReport($sdate, $edate, $account, $reportBy, true);


		// // return Carbon::parse('2020-11-07')->diffInDays(Carbon::parse('2020-11-08'));
		// if(isset($openReportData['sum'])){
		// 	$openDates = array_keys($openReportData['sum']);
		// 	$prevOpenDate = null;
		// 	foreach ($openDates as $oDate) {
		// 		if($prevOpenDate!=null){
		// 			if($prevOpenDate->diffInDays(Carbon::parse($oDate))>1){
		// 				$cd = CloseData::where($reportBy, $account)->where('date', '>', $prevOpenDate->toDateString())->where('date', '<', $oDate)->get();
		// 				if($cd->count()==0){
		// 					$gapPeriod = CarbonPeriod::create($prevOpenDate->copy()->addDay()->toDateString(), Carbon::parse($oDate)->subDay()->toDateString());
		// 					// return $gapPeriod;
		// 					foreach ($gapPeriod as $gapDate) {
		// 						$openReportData['sum'][$gapDate->toDateString()] = $openReportData['sum'][$prevOpenDate->toDateString()];
		// 					}
		// 				}else{
		// 					$closeDataDate = $cd->first();
		// 					$gapPeriod = CarbonPeriod::create($prevOpenDate->copy()->addDay()->toDateString(), Carbon::parse($closeDataDate['date'])->subDay()->toDateString());
		// 					// return $gapPeriod;
		// 					foreach ($gapPeriod as $gapDate) {
		// 						$openReportData['sum'][$gapDate->toDateString()] = $openReportData['sum'][$prevOpenDate->toDateString()];
		// 					}
		// 				}
		// 			}
		// 		}
		// 		$prevOpenDate = Carbon::parse($oDate);
		// 	}

		// }

		// $period = CarbonPeriod::create($sdate, $edate);
		// foreach ($period as $date) {
		// 	if(!isset($openReportData['sum'][$date->toDateString()])){
		// 		$openDate = Oreport::where('date', '<', $date->toDateString())->where($reportBy, $account)->orderBy('date', 'DESC')->first();
		// 		$cd = CloseData::where($reportBy, $account)->where('date', '>', $openDate['date'])->where('date', '<=', $date->toDateString())->get();
		// 		if($cd->count()==0){
		// 			$prevOpenReportData =  $this->getOpenReport(
		//     			$openDate['date'],
		//     			$openDate['date'],
		//     			$account,
		//     			$reportBy,
		//     			true
		//     		);
		// 			// foreach ($gapPeriod as $gapDate) {
		// 				$openReportData['sum'][$date->toDateString()] = $prevOpenReportData['sum'][$openDate['date']];
		// 			// }
		// 		}

		// 	}
		// }

		// return $openReportData['sum'];
		/*
					   $period = CarbonPeriod::create($sdate, $edate);

					   // Iterate over the period to generate OpenData for WeekEnd Days
					   $fridayOpenReportData = null;
					   foreach ($period as $date) {
						   if($date->dayName=="Saturday"){
							   $lastOpenDate = Carbon::parse($date->toDateString());
							   $lastOpenDate->subDay();
							   $fridayOpenReportData =  $this->getOpenReport(
								   $lastOpenDate->toDateString(),
								   $lastOpenDate->toDateString(),
								   $account,
								   $reportBy,
								   true
							   );
							   if(sizeof($fridayOpenReportData)>0){
								   $openReportData['sum'][$date->toDateString()] = $fridayOpenReportData['sum'][$lastOpenDate->toDateString()];
							   }
						   }
						   if($date->dayName=="Sunday"){
							   $lastOpenDate = Carbon::parse($date->toDateString());
							   $lastOpenDate->subDays(2);
							   if($fridayOpenReportData==null)
								   $fridayOpenReportData =  $this->getOpenReport(
									   $lastOpenDate->toDateString(),
									   $lastOpenDate->toDateString(),
									   $account,
									   $reportBy,
									   true
								   );
							   if(sizeof($fridayOpenReportData)>0){
								   $openReportData['sum'][$date->toDateString()] = $fridayOpenReportData['sum'][$lastOpenDate->toDateString()];
							   }
						   }
						   if($date->dayName=="Monday"){
							   $fridayOpenReportData = null;
						   }
					   }
					   */
		$diEntries = $this->getDailyInterestEntriesByDate($openReportData, $account, $reportBy);
		$adjEntries = $this->getAdjustmentEntriesByDate($sdate, $edate, $account, $reportBy, $isFunctionCall);
		// return $adjEntries;
		$data = [];
		if (sizeof($adjEntries) > 0) {
			if (sizeof($diEntries) > 0) {
				$data = array_merge($adjEntries, $diEntries);
			} else {
				$data = $adjEntries;
			}
		} else {
			if (sizeof($diEntries) > 0) {
				$data = $diEntries;
			}
		}
		usort($data, function ($obj1, $obj2) {
			return $obj1['date'] > $obj2['date'];
		});
		// $account = is_numeric($account)?$account+0:$account;
		// $data = Areport::where('date', '>=', $sdate)->where('date', '<=', $edate)->where($reportBy, $account)->get()->toArray();

		$rows = [];
		$sumRows = [];
		$sumTransferRows = [];
		$sumRowsFormat = [
			'date' => 0,
			'category' => 0,
			'comment' => 0,
			'debit' => 0,
			'credit' => 0,
			'net' => 0,
		];

		$totalDebitNet = 0;
		$totalCreditNet = 0;

		$totalTransferDebitNet = 0;
		$totalTransferCreditNet = 0;

		if (sizeof($data) > 0) {
			$cols = array_keys($sumRowsFormat);
			unset($sumRowsFormat['date']);
			unset($sumRowsFormat['comment']);
			$dataRow = [];
			$transferDataRow = [];


			foreach ($data as $key => $value) {
				/*
												 if(!$isFunctionCall){
													 foreach ($value as $colName => $value1) {
														 if(in_array($colName, $cols)){
															 $dataRow[$key][$colName] = $value1;
															 if($colName=='debit')
																 $totalDebitNet += $value1;
															 if($colName=='credit')
																 $totalCreditNet += $value1;
														 }

													 }
													 $categoryAlreadyExistInSumRow = false;
													 foreach ($sumRows as $key2 => $value2) {
														 if(trim(strtoupper($value2['category']))==trim(strtoupper($value['category']))){
															 $sumRows[$key2]['debit'] += ($value['debit']);
															 $sumRows[$key2]['credit'] += ($value['credit']);
															 $sumRows[$key2]['net'] += ($value['debit'] - $value['credit']);
															 $categoryAlreadyExistInSumRow = true;
															 break;
														 }
													 }
													 if(!$categoryAlreadyExistInSumRow){
														 $temp = $sumRowsFormat;
														 $temp['category'] = trim($value['category']);
														 $temp['debit'] = ($value['debit']);
														 $temp['credit'] = ($value['credit']);
														 $temp['net'] = ($value['debit'] - $value['credit']);
														 $sumRows[] = $temp;
													 }

												 }else{
													 if($isFunctionCall && strpos(strtolower($value['category']), 'transfer') !== false){
														 foreach ($value as $colName => $value1) {
															 if(in_array($colName, $cols)){
																 $transferDataRow[$key][$colName] = $value1;
																 if($colName=='debit')
																	 $totalTransferDebitNet += $value1;
																 if($colName=='credit')
																	 $totalTransferCreditNet += $value1;
															 }
														 }
														 $categoryAlreadyExistInTransferSumRow = false;
														 foreach ($sumTransferRows as $key2 => $value2) {
															 if(trim(strtoupper($value2['category']))==trim(strtoupper($value['category']))){
																 $sumTransferRows[$key2]['debit'] += ($value['debit']);
																 $sumTransferRows[$key2]['credit'] += ($value['credit']);
																 $sumTransferRows[$key2]['net'] += ($value['debit'] - $value['credit']);
																 $categoryAlreadyExistInTransferSumRow = true;
																 break;
															 }
														 }
														 if(!$categoryAlreadyExistInTransferSumRow){
															 $temp = $sumRowsFormat;
															 $temp['category'] = trim($value['category']);
															 $temp['debit'] = ($value['debit']);
															 $temp['credit'] = ($value['credit']);
															 $temp['net'] = ($value['debit'] - $value['credit']);
															 $sumTransferRows[] = $temp;
														 }
													 }else{
														 foreach ($value as $colName => $value1) {
															 if(in_array($colName, $cols)){
																 $dataRow[$key][$colName] = $value1;
																 if($colName=='debit')
																	 $totalDebitNet += $value1;
																 if($colName=='credit')
																	 $totalCreditNet += $value1;
															 }
														 }

														 $categoryAlreadyExistInSumRow = false;
														 foreach ($sumRows as $key2 => $value2) {
															 if(trim(strtoupper($value2['category']))==trim(strtoupper($value['category']))){
																 $sumRows[$key2]['debit'] += ($value['debit']);
																 $sumRows[$key2]['credit'] += ($value['credit']);
																 $sumRows[$key2]['net'] += ($value['debit'] - $value['credit']);
																 $categoryAlreadyExistInSumRow = true;
																 break;
															 }
														 }
														 if(!$categoryAlreadyExistInSumRow){
															 $temp = $sumRowsFormat;
															 $temp['category'] = trim($value['category']);
															 $temp['debit'] = ($value['debit']);
															 $temp['credit'] = ($value['credit']);
															 $temp['net'] = ($value['debit'] - $value['credit']);
															 $sumRows[] = $temp;
														 }
													 }
												 }*/
				if ($isFunctionCall && isset($value['fullCategory']) && strpos(strtolower($value['fullCategory']), 'transfer') !== false) {
					foreach ($value as $colName => $value1) {
						if (in_array($colName, $cols)) {
							$transferDataRow[$key][$colName] = $value1;
							if ($colName == 'debit')
								$totalTransferDebitNet += $value1;
							if ($colName == 'credit')
								$totalTransferCreditNet += $value1;
						}
					}
					$categoryAlreadyExistInTransferSumRow = false;
					foreach ($sumTransferRows as $key2 => $value2) {
						if (trim(strtoupper($value2['category'])) == trim(strtoupper($value['category']))) {
							$sumTransferRows[$key2]['debit'] += ($value['debit']);
							$sumTransferRows[$key2]['credit'] += ($value['credit']);
							$sumTransferRows[$key2]['net'] += ($value['debit'] - $value['credit']);
							$categoryAlreadyExistInTransferSumRow = true;
							break;
						}
					}
					if (!$categoryAlreadyExistInTransferSumRow) {
						$temp = $sumRowsFormat;
						$temp['category'] = trim($value['category']);
						$temp['debit'] = ($value['debit']);
						$temp['credit'] = ($value['credit']);
						$temp['net'] = ($value['debit'] - $value['credit']);
						$sumTransferRows[] = $temp;
					}
				} else {
					foreach ($value as $colName => $value1) {
						if (in_array($colName, $cols)) {
							$dataRow[$key][$colName] = $value1;
							if ($colName == 'debit')
								$totalDebitNet += $value1;
							if ($colName == 'credit')
								$totalCreditNet += $value1;
						}
					}

					$categoryAlreadyExistInSumRow = false;
					foreach ($sumRows as $key2 => $value2) {
						if (trim(strtoupper($value2['category'])) == trim(strtoupper($value['category']))) {
							$sumRows[$key2]['debit'] += ($value['debit']);
							$sumRows[$key2]['credit'] += ($value['credit']);
							$sumRows[$key2]['net'] += ($value['debit'] - $value['credit']);
							$categoryAlreadyExistInSumRow = true;
							break;
						}
					}
					if (!$categoryAlreadyExistInSumRow) {
						$temp = $sumRowsFormat;
						$temp['category'] = trim($value['category']);
						$temp['debit'] = ($value['debit']);
						$temp['credit'] = ($value['credit']);
						$temp['net'] = ($value['debit'] - $value['credit']);
						$sumRows[] = $temp;
					}
				}
			}
			if ($isFunctionCall) {
				return [
					'rows' => $dataRow,
					'sum' => [
						'debit' => $totalDebitNet,
						'credit' => $totalCreditNet,
					],
					'net' => -($totalDebitNet - $totalCreditNet),
					'categorySum' => $sumRows,
					'openReport' => $openReportData,
					'transfer' => [
						'rows' => $transferDataRow,
						'sum' => [
							'debit' => $totalTransferDebitNet,
							'credit' => $totalTransferCreditNet,
						],
						'net' => -($totalTransferDebitNet - $totalTransferCreditNet),
						'categorySum' => $sumTransferRows
					]
				];
			} else {
				return response()->json([
					'message' => 'success',
					'data' => [
						'rows' => $dataRow,
						'sum' => [
							'debit' => $totalDebitNet,
							'credit' => $totalCreditNet,
						],
						'net' => -($totalDebitNet - $totalCreditNet),
						'categorySum' => $sumRows
					]
				], 200);

			}
		} else {
			if ($isFunctionCall)
				return [];
			else
				return response()->json(['message' => 'No Data Found for the Selected Parameters', 'data' => []], 200);
		}

	}

	public function updateCashData($accountType, $account, $rows)
	{
		$updateRow = [];
		
		foreach ($rows as $key => $value) {
			if ($accountType == 'accountid')
				DB::collection('cashData')->where('date', $value['date'])->where($accountType, $account)->update([
					'date' => $value['date'],
					$accountType => $account,
					'cash' => $value['cash'],
				], ['upsert' => true]);
			else{
				$mappedTo = User::find($account)->getAccountId($value['date']);
				DB::collection('cashData')->where('date', $value['date'])->where($accountType, $account)->update([
					'date' => $value['date'],
					$accountType => $account,
					'mappedAccount' => $mappedTo,
					'cash' => $value['cash'],
				], ['upsert' => true]);
			}
		}
		// CashData::upsert($updateRow, ['date', $accountType], ['cash']);
	}

	public function getSummaryByDateReport($sdate, $edate, $account, $reportBy = 'accountid', $isFunctionCall = false, $excelExport = false)
	{
		$period = CarbonPeriod::create($sdate, $edate);
		$detailed = $this->getDetailedReport($sdate, $edate, $account, $reportBy, true);
		// if(sizeof($detailed)==0){
		// 	return response()->json(['message' => 'No Data Found for the Selected Parameters', 'data' => []], 200);
		// }
		// $openReportData =  $this->getOpenReport($sdate, $edate, $account, $reportBy, true);
		$openReportData = $this->getCompiledOpenReportForAdjustment($sdate, $edate, $account, $reportBy);
		
		$adjustment = [];
		$adjCols = [];

		$transfer = [];
		$transferCols = [];
		foreach ($period as $date) {
			$temp = $this->getAdjustmentReport($date->toDateString(), $date->toDateString(), $account, $reportBy, true);
			$adjustment[$date->toDateString()] = $temp;
			if (sizeof($temp) > 0) {
				foreach ($temp['categorySum'] as $value) {
					if (!in_array($value['category'], array_keys($adjCols)))
						$adjCols[$value['category']] = $value['category'];
					// array_push($adjCols, $value['category']);
				}

				foreach ($temp['transfer']['categorySum'] as $value) {
					if (!in_array($value['category'], array_keys($transferCols)))
						$transferCols[$value['category']] = $value['category'];
				}

			}
		}
		ksort($adjCols);
		ksort($transferCols);
		// return $adjustment;
		$sbdHeaderCols = ['date' => 'Date', 'type' => ' ', 'orders' => 'Orders', 'fills' => 'Fills', 'qty' => 'Qty', 'gross' => 'Gross'];

		$allTradeFeesCols = [];
		if (sizeof($detailed) > 0) {
			foreach ($detailed['prefCols'] as $value) {
				foreach ($value as $key => $value1) {
					if (!array_key_exists($key, $allTradeFeesCols))
						$allTradeFeesCols[$key] = $value1;
				}
			}

		}
		$sbdHeaderCols = ($sbdHeaderCols + $allTradeFeesCols);
		$sbdHeaderCols['totalTradeFees'] = 'All Trade Fees+';
		$sbdHeaderCols['net'] = 'Net';

		$sbdHeaderCols = ($sbdHeaderCols + $adjCols);
		$sbdHeaderCols['totalAdjFees'] = 'Adj Fees+';
		$sbdHeaderCols['adjNet'] = 'Adj Net';

		$sbdHeaderCols['und'] = 'Unrealized Δ';
		$sbdHeaderCols['totald'] = 'Total Δ';
		$sbdHeaderCols = ($sbdHeaderCols + $transferCols);
		$sbdHeaderCols['transfers'] = 'Transfers';
		$sbdHeaderCols['cash'] = 'Cash';
		$sbdHeaderCols['un'] = 'Unrealized';
		$sbdHeaderCols['endBalance'] = 'End Balance+';
		// return array_fill_keys(array_keys($sbdHeaderCols), 0);
		// $sbdDataCols = array_keys($sbdHeaderCols);

		$rows = [];
		$prevDayCash = 0;
		$prevDayCashP = 0;
		$prevdayDate = Carbon::parse($sdate)->subDay()->toDateString();
		$cashData = CashData::where('date', $prevdayDate)->where($reportBy, $account)->first();
		if ($reportBy != 'userId') {
			if (isset($cashData)) {
				$prevDayCash = $cashData['cash'];
				$prevDayCashP = $cashData['cash'];
			}
		} else {
			if (isset($cashData)) {
				$prevDayCash = $cashData['cash'];
				$prevDayCashP = $cashData['cash'];
			} else {
				// $userAccountId = User::find($account)->getAccountId($prevdayDate);
				// if($userAccountId!=null){
				// 	$cashData = CashData::where('date', $prevdayDate)->where('accountid', $userAccountId)->first();
				// 	if (isset($cashData)) {
				// 		$prevDayCash = $cashData['cash'];
				// 		$prevDayCashP = $cashData['cash'];
				// 	}

				// }
			}

		}
		foreach ($period as $date) {
			$tempRow = array_fill_keys(array_keys($sbdHeaderCols), 0);
			$tempRow['date'] = $date->toDateString();
			$tempRow['type'] = 'Eq';
			$totalTradeFees = 0;
			if (isset($detailed['dailyTotal'][$date->toDateString()])) {
				foreach ($detailed['dailyTotal'][$date->toDateString()] as $key => $value) {
					if (array_key_exists($key, $tempRow)) {
						$tempRow[$key] = $value;
					}
					if (array_key_exists($key, $allTradeFeesCols))
						$totalTradeFees += $value;
				}
			}
			$tempRow['totalTradeFees'] = $totalTradeFees;

			$totalAdjFees = 0;
			$totalTransferFees = 0;
			if (isset($adjustment[$date->toDateString()]) && sizeof($adjustment[$date->toDateString()]) > 0) {
				foreach ($adjustment[$date->toDateString()]['categorySum'] as $value) {
					if (array_key_exists($value['category'], $tempRow)) {
						$tempRow[$value['category']] = -1 * $value['net'];
						$totalAdjFees += (-1 * $value['net']);
					}
				}

				if (sizeof($adjustment[$date->toDateString()]['transfer']) > 0) {
					foreach ($adjustment[$date->toDateString()]['transfer']['categorySum'] as $value) {
						if (array_key_exists($value['category'], $tempRow)) {
							$tempRow[$value['category']] = -1 * $value['net'];
							$totalTransferFees += (-1 * $value['net']);
						}
					}
				}
			}
			$tempRow['totalAdjFees'] = $totalAdjFees;
			$tempRow['transfers'] = $totalTransferFees;

			$tempRow['adjNet'] = $tempRow['net'] + $tempRow['totalAdjFees'];

			if (isset($openReportData['sum'][$date->toDateString()])) {
				$tempRow['und'] = $openReportData['sum'][$date->toDateString()]['total']['und'];
				$tempRow['un'] = $openReportData['sum'][$date->toDateString()]['total']['un'];
			} else {
				$tempRow['und'] = 0;
				$tempRow['un'] = 0;
			}
			$tempRow['totald'] = $tempRow['adjNet'] + $tempRow['und'];


			$tempRow['cash'] = ($tempRow['totald'] - $tempRow['und']) + $prevDayCash + $tempRow['transfers'];
			$tempRow['endBalance'] = $tempRow['cash'] + $tempRow['un'];

			array_push($rows, $tempRow);
			$prevDayCash = $tempRow['cash'];
		}

		$sumRow = array_fill_keys(array_keys($sbdHeaderCols), 0);
		$sumRow['date'] = 'Equities';
		$sumRow['type'] = '';
		$sumRow['transfers'] = 0;

		foreach ($rows as $row) {
			foreach ($row as $key => $value) {
				if (!in_array($key, ['date', 'type'])) {
					// echo ' '.$key.'@'.$sumRow[$key].' ';
					if (in_array($key, ['cash', 'un', 'endBalance']))
						$sumRow[$key] = $value;
					else
						$sumRow[$key] = $sumRow[$key] + $value;
				}
			}
		}

		foreach ($rows as $key1 => $value1) {
			$removeFlag = true;
			foreach ($value1 as $key => $value) {
				if ($key != 'date' && $key != 'type') {
					if ($value != 0) {
						// return 'Not Se';
						$removeFlag = false;
						break;
					}

				}
			}
			if ($removeFlag)
				unset($rows[$key1]);
			// return $rows[$key1];
		}
		$rows = array_values($rows);
		// return $rows;
		$this->updateCashData($reportBy, $account, $rows);
		if ($isFunctionCall) {
			return [
				'header' => $sbdHeaderCols,
				'rows' => $rows,
				'sum' => $sumRow,
				'previousDayCash' => $prevDayCashP,
				'cashData' => $cashData,
			];
		} else {
			return response()->json([
				'message' => 'success',
				'data' => [
					'header' => $sbdHeaderCols,
					'rows' => $rows,
					'sum' => $sumRow,
					'previousDayCash' => $prevDayCashP,
					'cashData' => $cashData,
					'prevdayDate' => $prevdayDate,
					'reportBy' => $reportBy,
					'account' => $account,
				],
				// 'debug' => [
				// 	'detailed' => $detailed,
				// 	'open' => $openReportData,
				// 	'firstCashAccount' => $userAccountId,
				// ]
			], 200);
		}

		// return $rows;


		// return [$detailed['dailyTotal'], $adjustment];
		// return json_encode($sbdDataCols);
	}


	public function getSummaryByMonthReport($sdate, $edate, $account, $reportBy = 'accountid', $isFunctionCall = false, $excelExport = false)
	{
		// $sdateObj = Carbon::parse($sdate)->startOfMonth();
		// $edateObj = Carbon::parse($edate)->endOfMonth();

		// $sdateDay = $sdateObj->day;
		// $sdateMonth = $sdateObj->month;

		// $sdateObj->day = 1;
		// $edateObj->day = $sdateObj->day;

		// $sdateMonthName = $sdateObj->format('F');

		$array = [];

		$result = CarbonPeriod::create(Carbon::parse($sdate)->startOfMonth()->toDateString(), '1 month', Carbon::parse($edate)->startOfMonth()->toDateString());
		foreach ($result as $dt) {
			$array[] = [
				$dt->copy()->toDateString(),
				$dt->copy()->endOfMonth()->toDateString()
			];
		}
		$sbmArray = [];
		$headerCols = [];
		$sum = [];

		$temp = $this->getSummaryByDateReport($array[0][0], $array[sizeof($array) - 1][1], $account, $reportBy, true);
		if (isset($temp['header']) && isset($temp['sum'])) {
			$headerCols = $temp['header'];
			$sum = $temp['sum'];

		}


		foreach ($array as $dateSet) {
			$temp = $this->getSummaryByDateReport($dateSet[0], $dateSet[1], $account, $reportBy, true)['sum'];
			// $currentMonth = Carbon::parse($dateSet[0]);
			$temp['date'] = $dateSet[0];
			// $temp['date'] = $currentMonth->get('month').', '.$currentMonth->get('year');
			$sbmArray[] = $temp;
		}

		// $sum = [];
		// if(sizeof($sbmArray)>0){
		// 	foreach ($sbmArray as $key => $value) {
		// 		if(sizeof($sum)==0){
		// 			$sum = $value;
		// 			$sum['date'] = 'Equities';
		// 		}else{
		// 			foreach ($value as $key1 => $value1) {
		// 				if(isset($sum[$key1])){
		// 					if(is_numeric($value1)){
		// 						$sum[$key1] += $value1;
		// 					}
		// 				}
		// 			}
		// 		}
		// 	}

		// }

		if ($isFunctionCall) {
			return [
				'header' => $headerCols,
				'rows' => $sbmArray,
				'sum' => $sum,
			];
		} else {
			return response()->json([
				'message' => 'success',
				'data' => [
					'header' => $headerCols,
					'rows' => $sbmArray,
					'sum' => $sum,
				]
			], 200);
		}

	}

	public function getTotalByDateReport($sdate, $edate, $account, $reportBy = 'accountid', $isFunctionCall = false, $excelExport = false)
	{
		$detailed = $this->getDetailedReport($sdate, $edate, $account, $reportBy, true);
		$sbd = $this->getSummaryByDateReport($sdate, $edate, $account, $reportBy, true);
		$tbsHeaderCol = ['symbol' => 'Symbol'];
		$tbsHeaderRestCol = ['und' => 'Unrealized Δ', 'totald' => 'Total Δ', 'un' => 'Unrealized'];
		$sbdArray = [];
		foreach ($sbd['rows'] as $row) {
			$sbdArray[$row['date']] = [
				'cash' => $row['cash'],
				'un' => $row['un'],
			];
		}
		// $symbolTotal = [];
		$dataRows = [];
		$sumRows = [];
		foreach ($detailed['sumRows'] as $dateKey => $dateValues) {
			foreach ($dateValues as $symbolKey => $SymbolData) {
				foreach ($SymbolData['total'] as $colName => $colValue) {
					if (!in_array($colName, array_keys($tbsHeaderCol)))
						$tbsHeaderCol[$colName] = str_replace("_", " ", ucwords($colName));
				}
			}
		}

		$tbsHeaderCol = array_merge($tbsHeaderCol, $tbsHeaderRestCol);
		unset($tbsHeaderCol['position']);
		unset($tbsHeaderCol['price']);
		$headerCols = array_values($tbsHeaderCol);
		$dataKeys = array_keys($tbsHeaderCol);

		foreach ($detailed['sumRows'] as $dateKey => $dateValues) {
			$symbolTotal = [];
			foreach ($dateValues as $symbolKey => $SymbolData) {

				if (array_key_exists($symbolKey, $symbolTotal)) {
					foreach ($SymbolData['total'] as $colName => $colValue) {
						if (array_key_exists($colName, $symbolTotal[$symbolKey])) {
							if (is_numeric($colValue))
								$symbolTotal[$symbolKey][$colName] += $colValue;
							if ($colName == 'net') {
								$symbolTotal[$symbolKey]['totald'] += $colValue;
							}
						}
					}

				} else {
					$symbolTotal[$symbolKey] = array_fill_keys($dataKeys, 0);
					$symbolTotal[$symbolKey]['symbol'] = $symbolKey;
					foreach ($SymbolData['total'] as $colName => $colValue) {
						if (array_key_exists($colName, $symbolTotal[$symbolKey]))
							$symbolTotal[$symbolKey][$colName] = $colValue;
					}
					$symbolTotal[$symbolKey]['totald'] = $symbolTotal[$symbolKey]['net'];
				}
			}

			$rows = array_values($symbolTotal);
			$sumRow = array_fill_keys($dataKeys, 0);
			$sumRow['symbol'] = 'Equities';
			foreach ($rows as $row) {
				foreach ($row as $colName => $colValue) {
					if ($colName != 'symbol') {
						$sumRow[$colName] += $colValue;
					}
				}
			}

			$dataRows[$dateKey] = $rows;
			$sumRows[$dateKey] = $sumRow;
		}
		$allTotal = [];
		if (sizeof($sumRows) > 0) {
			$allTotal = array_fill_keys($dataKeys, 0);
			$allTotal['symbol'] = 'Equities';
			foreach ($sumRows as $dateKey => $row) {
				foreach ($row as $colName => $colValue) {
					if ($colName != 'symbol') {
						$allTotal[$colName] += $colValue;
					}
				}
			}
		}

		if ($isFunctionCall) {
			return [
				'header' => $headerCols,
				'rows' => $dataRows,
				'sum' => $sumRows,
				'total' => $allTotal,
				'cashData' => $sbdArray,
				'totalCashData' => [
					'cash' => $sbd['sum']['cash'],
					'un' => $sbd['sum']['un'],
				]
			];
		} else {
			return response()->json([
				'message' => 'success',
				'data' => [
					'header' => $headerCols,
					'rows' => $dataRows,
					'sum' => $sumRows,
					'total' => $allTotal,
					'cashData' => $sbdArray,
					'totalCashData' => [
						'cash' => $sbd['sum']['cash'],
						'un' => $sbd['sum']['un'],
					]
				]
			], 200);
		}

	}

	public function getTotalBySymbolReport($sdate, $edate, $account, $reportBy = 'accountid', $isFunctionCall = false, $excelExport = false)
	{
		$detailed = $this->getDetailedReport($sdate, $edate, $account, $reportBy, true);
		$tbsHeaderCol = ['symbol' => 'Symbol'];
		$tbsHeaderRestCol = ['und' => 'Unrealized Δ', 'totald' => 'Total Δ', 'un' => 'Unrealized'];

		$symbolTotal = [];
		if (isset($detailed['sumRows'])) {
			foreach ($detailed['sumRows'] as $dateKey => $dateValues) {
				foreach ($dateValues as $symbolKey => $SymbolData) {
					foreach ($SymbolData['total'] as $colName => $colValue) {
						if (!in_array($colName, array_keys($tbsHeaderCol)))
							$tbsHeaderCol[$colName] = str_replace("_", " ", ucwords($colName));
					}
				}
			}

			$tbsHeaderCol = array_merge($tbsHeaderCol, $tbsHeaderRestCol);
			unset($tbsHeaderCol['position']);
			unset($tbsHeaderCol['price']);
			$headerCols = array_values($tbsHeaderCol);
			$dataKeys = array_keys($tbsHeaderCol);

			foreach ($detailed['sumRows'] as $dateKey => $dateValues) {
				foreach ($dateValues as $symbolKey => $SymbolData) {

					if (array_key_exists($symbolKey, $symbolTotal)) {
						foreach ($SymbolData['total'] as $colName => $colValue) {
							if (array_key_exists($colName, $symbolTotal[$symbolKey])) {
								if (is_numeric($colValue))
									$symbolTotal[$symbolKey][$colName] += $colValue;
								if ($colName == 'net') {
									$symbolTotal[$symbolKey]['totald'] += $colValue;
								}
							}
						}

					} else {
						$symbolTotal[$symbolKey] = array_fill_keys($dataKeys, 0);
						$symbolTotal[$symbolKey]['symbol'] = $symbolKey;
						foreach ($SymbolData['total'] as $colName => $colValue) {
							if (array_key_exists($colName, $symbolTotal[$symbolKey]))
								$symbolTotal[$symbolKey][$colName] = $colValue;
						}
						$symbolTotal[$symbolKey]['totald'] = $symbolTotal[$symbolKey]['net'];
					}
				}
			}
			$rows = array_values($symbolTotal);
			$sumRow = array_fill_keys($dataKeys, 0);
			$sumRow['symbol'] = 'Equities';
			foreach ($rows as $row) {
				foreach ($row as $colName => $colValue) {
					if ($colName != 'symbol') {
						$sumRow[$colName] += $colValue;
					}
				}
			}
			if ($isFunctionCall) {
				return [
					'header' => $headerCols,
					'rows' => $rows,
					'sum' => $sumRow,
				];
			} else {
				return response()->json([
					'message' => 'success',
					'data' => [
						'header' => $headerCols,
						'rows' => $rows,
						'sum' => $sumRow,
					]
				], 200);
			}
		} else
			if ($isFunctionCall)
				return [];
			else
				return response()->json(['message' => 'No Data Found for the Selected Parameters'], 200);

	}

	public function checkIfBlankSBDReport($sumRow)
	{
		$flag = true;
		if (sizeof($sumRow) > 0) {
			foreach ($sumRow as $key => $value) {
				if (is_numeric($value))
					if ($value != 0) {
						$flag = false;
						break;
					}
			}

		}

		return $flag;
	}

	public function compareSBDHeader($h1, $h2)
	{

		if (sizeof($h1) > 0) {
			$arrP1 = [];
			$tradeFees = [];
			$arrP2 = [];
			$adjFees = [];
			$arrP3 = [];
			$transfers = [];
			$arrP4 = [];

			$totalTradeFeesArr = [];
			$netArr = [];
			$totalAdjFeesArr = [];
			$adjNetArr = [];


			$part1 = true;
			$tradeFeesStart = false;
			$part2 = false;
			$adjFeesStart = false;
			$part3 = false;
			$transfersStart = false;
			$part4 = false;
			foreach ($h1 as $key => $value) {
				if ($part1) {
					$arrP1[$key] = $value;
				}
				if ($key == 'gross') {
					$part1 = false;
					$tradeFeesStart = true;
				}
				if (!in_array($key, ['gross', 'totalTradeFees']) && $tradeFeesStart) {
					$tradeFees[$key] = $value;
				}
				if ($key == 'totalTradeFees') {
					$tradeFeesStart = false;
					$part2 = true;
				}

				if ($part2) {
					$arrP2[$key] = $value;
				}


				if ($key == 'net') {
					$part2 = false;
					$adjFeesStart = true;
				}
				if (!in_array($key, ['net', 'totalAdjFees']) && $adjFeesStart) {
					$adjFees[$key] = $value;
				}

				if ($key == 'totalAdjFees') {
					$adjFeesStart = false;
					$part3 = true;
				}

				if ($part3) {
					$arrP3[$key] = $value;
				}
				if ($key == 'totald') {
					$part3 = false;
					$transfersStart = true;
				}
				if (!in_array($key, ['totald', 'transfers']) && $transfersStart) {
					$transfers[$key] = $value;
				}
				if ($key == 'transfers') {
					$transfersStart = false;
					$part4 = true;
				}
				if ($part4) {
					$arrP4[$key] = $value;
				}
			}



			$tradeFeesStart = false;
			$adjFeesStart = false;
			$transfersStart = false;
			foreach ($h2 as $key => $value) {
				if ($key == 'gross')
					$tradeFeesStart = true;
				else if ($key == 'totalTradeFees')
					$tradeFeesStart = false;
				else if ($key == 'net')
					$adjFeesStart = true;
				else if ($key == 'totalAdjFees')
					$adjFeesStart = false;
				else if ($key == 'totald')
					$transfersStart = true;
				else if ($key == 'transfers')
					$transfersStart = false;
				else {
					if ($tradeFeesStart) {
						if (!in_array($key, array_keys($tradeFees)))
							$tradeFees[$key] = $value;
					}
					if ($adjFeesStart) {
						if (!in_array($key, array_keys($adjFees)))
							$adjFees[$key] = $value;
					}
					if ($transfersStart) {
						if (!in_array($key, array_keys($transfers)))
							$transfers[$key] = $value;
					}
				}
			}

			return array_merge($arrP1, $tradeFees, $arrP2, $adjFees, $arrP3, $transfers, $arrP4);
		} else {
			return $h2;
		}

	}

	public function getGroupSummaryByDateReport($sdate, $edate, $group, $isFunctionCall = false, $excelExport = false)
	{
		$period = CarbonPeriod::create($sdate, $edate);

		$group = ReportGroup::find($group);
		$allAccounts = $group->getAllMembers();

		$sbdMaster = [];

		$header = [];
		$sbdArray = [];
		$sum = [];

		foreach ($period as $date) {
			foreach ($allAccounts['accounts'] as $key => $value) {
				$tmp = $this->getSummaryByDateReport($date->toDateString(), $date->toDateString(), $value, 'accountid', true);
				if (isset($tmp['sum']) && !$this->checkIfBlankSBDReport($tmp['sum'])) {
					$header = $this->compareSBDHeader($header, $tmp['header']);
					$sbdMaster[$date->toDateString()][$value] = $tmp['sum'];
				}
				$tmp = null;
			}

			foreach ($allAccounts['users'] as $key => $value) {
				$tmp = $this->getSummaryByDateReport($date->toDateString(), $date->toDateString(), $key, 'user_id', true);
				if (isset($tmp['sum']) && !$this->checkIfBlankSBDReport($tmp['sum'])) {
					$header = $this->compareSBDHeader($header, $tmp['header']);
					$sbdMaster[$date->toDateString()][$value] = $tmp['sum'];
				}
				$tmp = null;
			}

		}
		if (sizeof($header) > 0)
			$header['type'] = 'Accounts';

		foreach ($sbdMaster as $dateKey => $accounts) {
			$dayTotal = array_fill_keys(array_keys($header), 0);
			$dayTotal['date'] = $dateKey;
			$dayTotal['type'] = '';
			$accountsCount = 0;
			foreach ($accounts as $accKey => $accData) {
				foreach ($accData as $key => $value) {
					if (is_numeric($dayTotal[$key])) {
						$dayTotal[$key] += $value;
					}
				}
				$accountsCount++;
			}
			$dayTotal['type'] = $accountsCount;
			$sbdArray[] = $dayTotal;
		}

		$sum = array_fill_keys(array_keys($header), 0);
		$sum['date'] = 'Equities';
		$sum['type'] = '';
		foreach ($sbdArray as $row) {
			foreach ($row as $key => $value) {
				if (is_numeric($sum[$key]))
					$sum[$key] += $value;
			}
		}
		// return [$header, $sbdArray];

		// foreach ($period as $date) {
		// 	$noOfAccounts = 0;
		// 	foreach ($allAccounts['accounts'] as $key => $value) {
		// 		$tmp = $this->getSummaryByDateReport($date->toDateString(), $date->toDateString(), $value, 'accountid', true);
		// 		if(isset($tmp['sum']) && sizeof($tmp['sum'])>0){
		// 			$noOfAccounts++;

		// 			if(isset($sbdArray[$date->toDateString()])){
		// 				foreach ($tmp['sum'] as $key1 => $value1) {
		// 					if(isset($sbdArray[$date->toDateString()][$key1])){
		// 						if(is_numeric($sbdArray[$date->toDateString()][$key1])){
		// 							$sbdArray[$date->toDateString()][$key1] += $value1;
		// 						}
		// 					}
		// 				}
		// 			}else{
		// 				$sbdArray[$date->toDateString()] = $tmp['sum'];
		// 			}
		// 		}
		// 		$tmp = null;
		// 	}

		// 	foreach ($allAccounts['users'] as $key => $value) {
		// 		$tmp = $this->getSummaryByDateReport($date->toDateString(), $date->toDateString(), $key, 'user_id', true);
		// 		if(isset($tmp['sum']) && sizeof($tmp['sum'])>0){
		// 			$noOfAccounts++;

		// 			if(isset($sbdArray[$date->toDateString()])){
		// 				foreach ($tmp['sum'] as $key1 => $value1) {
		// 					if(isset($sbdArray[$date->toDateString()][$key1])){
		// 						if(is_numeric($sbdArray[$date->toDateString()][$key1])){
		// 							$sbdArray[$date->toDateString()][$key1] += $value1;
		// 						}
		// 					}
		// 				}
		// 			}else{
		// 				$sbdArray[$date->toDateString()] = $tmp['sum'];
		// 			}
		// 		}
		// 		$tmp = null;
		// 	}

		// 	$sbdArray[$date->toDateString()]['type'] = $noOfAccounts;
		// 	$noOfAccounts = 0;

		// }
		// return $sbdArray;
		if ($isFunctionCall) {
			return [
				'header' => $header,
				'rows' => $sbdArray,
				'sum' => $sum,
			];
		} else {
			return response()->json([
				'message' => 'success',
				'data' => [
					'header' => $header,
					'rows' => $sbdArray,
					'sum' => $sum,
				]
			], 200);
		}

	}


	public function compileStartBalance($sbdData)
	{
		if (isset($sbdData['rows']) && sizeof($sbdData['rows']) == 1) {
			return [
				'sCash' => $sbdData['rows'][0]['cash'],
				'sUn' => $sbdData['rows'][0]['un'],
				'sBalance' => $sbdData['rows'][0]['endBalance'],
			];
		} else {
			return [
				'sCash' => 0,
				'sUn' => 0,
				'sBalance' => 0,
			];
		}
	}


	public function getTotalByAccountReport($sdate, $edate, $group, $type = 'accounts', $isFunctionCall = false, $excelExport = false)
	{
		$group = ReportGroup::find($group);
		$allAccounts = $group->getAllMembers();
		$reportBy = ($type == 'users') ? 'userId' : 'accountid';
		$oneDayBack = Carbon::parse($sdate)->toDateString();


		$sec1Header = ['account' => 'Account', 'type' => 'Type', 'orders' => 'Orders', 'fills' => 'Fills', 'qty' => 'Qty'];
		$sec2Header = ['sCash' => 'Start Cash', 'sUn' => 'Start Unrealized', 'sBalance' => 'Start Balance', 'gross' => 'Gross'];
		$tradeFeesCols = [];
		$sec3Header = ['totalTradeFees' => 'Trade Fees', 'net' => 'Net'];
		$adjFeesCols = [];
		$sec4Header = ['totalAdjFees' => 'Adj Fees', 'adjNet' => 'Adj Net', 'und' => 'Unrealized Δ', 'totald' => 'Total Δ'];
		$transfersCols = [];
		$sec5Header = ['transfers' => 'Transfers', 'cash' => 'End Cash', 'un' => 'End Unrealized', 'endBalance' => 'End Balance'];
		$rows = [];
		foreach ($allAccounts[$type] as $key1 => $value1) {
			$reportFor = $reportBy == 'userId' ? $key1 : $value1;
			$startBalance = $this->getSummaryByDateReport($oneDayBack, $oneDayBack, $reportFor, $reportBy, true);
			$startBalance = $this->compileStartBalance($startBalance);
			$tmp = $this->getSummaryByDateReport($sdate, $edate, $reportFor, $reportBy, true);
			$accData = $tmp['sum'];

			if (sizeof($tmp['rows']) > 0) {
				// Extracting Fees Cols
				$tradeFeesFlag = false;
				$adjFeesFlag = false;
				$transfersFlag = false;

				foreach ($tmp['header'] as $key => $value) {
					if ($key == 'gross')
						$tradeFeesFlag = true;
					else if ($key == 'totalTradeFees')
						$tradeFeesFlag = false;
					else if ($key == 'net')
						$adjFeesFlag = true;
					else if ($key == 'totalAdjFees')
						$adjFeesFlag = false;
					else if ($key == 'totald')
						$transfersFlag = true;
					else if ($key == 'transfers')
						$transfersFlag = false;
					else if ($tradeFeesFlag) {
						if (!array_key_exists($key, $tradeFeesCols)) {
							$tradeFeesCols[$key] = $value;
						}
					} else if ($adjFeesFlag) {
						if (!array_key_exists($key, $adjFeesCols)) {
							$adjFeesCols[$key] = $value;
						}
					} else if ($transfersFlag) {
						if (!array_key_exists($key, $transfersCols)) {
							$transfersCols[$key] = $value;
						}
					}
				}

				$allCols = array_merge($sec1Header, $sec2Header, $tradeFeesCols, $sec3Header, $adjFeesCols, $sec4Header, $transfersCols, $sec5Header);
				$row = array_fill_keys(array_keys($allCols), 0);

				$row['account'] = $value1;
				$row['sCash'] = $startBalance['sCash'];
				$row['sUn'] = $startBalance['sUn'];
				$row['sBalance'] = $startBalance['sBalance'];
				foreach ($accData as $colKey => $colValue) {
					if (array_key_exists($colKey, $row)) {
						$row[$colKey] = $colValue;
					}
				}
				$row['type'] = 'Eq';

				$rows[] = $row;

			}
		}

		$header = array_merge($sec1Header, $sec2Header, $tradeFeesCols, $sec3Header, $adjFeesCols, $sec4Header, $transfersCols, $sec5Header);
		$sumRow = array_fill_keys(array_keys($header), 0);

		$sumRow['account'] = 'Equities';
		foreach ($rows as $row) {
			foreach ($row as $colKey => $colValue) {
				if (!in_array($colKey, ['account', 'type'])) {
					$sumRow[$colKey] += $colValue;
				}
			}
		}

		if ($isFunctionCall) {
			return [
				'header' => $header,
				'rows' => $rows,
				'sum' => $sumRow,
			];
		} else {
			return response()->json([
				'message' => 'success',
				'data' => [
					'header' => $header,
					'rows' => $rows,
					'sum' => $sumRow,
				]
			], 200);
		}

	}

	public function getUserAccountArrayForDropDown(Request $request, $prepend = '')
	{
		$users = User::all()->pluck('memberId', '_id')->toArray();
		$accounts = TradeAccount::all()->pluck('accountid')->toArray();
		$arr = [];
		foreach ($users as $key => $value) {
			$arr[] = [
				'id' => $key,
				'text' => $value
			];
		}
		foreach ($accounts as $value) {
			$arr[] = [
				'id' => $prepend . $value,
				'text' => $value
			];
		}
		return $arr;
	}
}