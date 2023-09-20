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
use App\Models\Adjustment;
use App\Models\Locatedata;
use App\Models\Closedata;
use App\Models\CashData;
use App\Models\Open;
use App\Models\Oreport;
use App\Models\Dreport;
use App\Models\Areport;
use App\Models\Userlocate;
use App\Models\Locreport;
use App\Models\PreviousDayOpen as Prevdaydata;
use App\Models\AdjustmentMaster as Adjustmentmaster;
use Log;
use Session;
use Illuminate\Support\Facades\Schema;
use DB;
use MongoDB;
use Carbon\CarbonPeriod;
use App\Utils\PropReportsApiClient;
use App\Traits\DataMapperTrait;
use App\Http\Controllers\ReportViewerController;

class ReportGeneratorClass
{
	use DataMapperTrait;

	static $userIdCol = 'userId';


	#Calculating the first Position
	public $currentPosition = 0;
	#End of Fist Position		
	public $previousPosition = 0;
	public $prevPosData = null;


	public $gross = 0;
	public $shareQty = 0;
	public $openData = array();

	public $dReportDataArray = [];
	public $grossCalculationArr = [];
	public $currentGrossTrDetailsArray = [];

	public $operatingAccounts = [];
	public $prevDayOpenDatesForOperatingAccounts = [];

	public function processReport($sdate, $edate, $trId)
	{
		try {
			if (Schema::hasColumn('detaileds', 'user_id')) {
				$userIdCol = 'user_id';
			}

			$operatingAccounts = Detailed::distinct()->where('transactionId', $trId)->get(['accountid'])->toArray(); //pluck('accountid')->toArray();
			if (sizeof($operatingAccounts) == 0) {
				$operatingAccounts = Open::distinct()->where('transactionId', $trId)->get(['accountid'])->toArray();
				if (sizeof($operatingAccounts) == 0) {
					$operatingAccounts = Adjustment::distinct()->where('transactionId', $trId)->get(['accountid'])->toArray();
				}
			}
			// return $operatingAccounts;
			array_map(function ($arr) {
				if (!in_array($arr[0], $this->operatingAccounts))
					$this->operatingAccounts[] = $arr[0];
			}, $operatingAccounts);

			$this->generateLocates($sdate, $edate, $trId, true);
			Log::notice('Generating Locatedata : Complete');
			#Locate Generation Done
			$this->generateDetailed($sdate, $edate, $trId, true);
			Log::notice('Generating Detailed : Complete');
			$feedBack = $this->generatePreviousDayData($sdate, $edate, $trId, true);
			// return $feedBack;
			if ($feedBack['message'] == 'success') {

				Log::notice('Generating For Next Day Open : Complete');
				$this->generateOpenData($sdate, $edate, true);
				Log::notice('Generating Open Data : Complete');
				$this->generateCloseForOpen($sdate, $edate, true);
				Log::notice('Generating CloseData For the Day : Complete');
				$this->generateAdjustmentDataNew($sdate, $edate, $trId, true);
				Log::notice('Generating Adjustment : Complete');

				$this->generateLocateData($sdate, $edate, true);
				Log::notice('Generating Locate Data : Complete');

				$period = CarbonPeriod::create($sdate, $edate);

				foreach ($period as $date) {
					foreach ($operatingAccounts as $accountid) {

						$reportObj = new ReportViewerController();
						$sbdReport = $reportObj->getSummaryByDateReport($date->toDateString(), $date->toDateString(), $accountid[0], 'accountid', true);
						if (CashData::where('date', $date->toDateString())->where('accountid', $accountid[0])->get()->count() == 1) {
							CashData::where('date', $date->toDateString())->where('accountid', $accountid[0])->update(['cash' => $sbdReport['sum']['cash']]);
						} else {
							$cashObj = new CashData();

							$cashObj['date'] = $date->toDateString();
							$cashObj['accountid'] = $accountid[0];
							$cashObj['cash'] = $sbdReport['sum']['cash'];
							$cashObj->save();

						}

						$mappedUsers = $this->getAllMappedUsers($accountid, $date->toDateString());
						if (sizeof($mappedUsers) > 0) {
							foreach ($mappedUsers as $userId) {
								$reportObj = new ReportViewerController();
								$sbdReport = $reportObj->getSummaryByDateReport($date->toDateString(), $date->toDateString(), $userId, 'userId', true);
								if (CashData::where('date', $date->toDateString())->where('userId', $userId)->get()->count() == 1) {
									CashData::where('date', $date->toDateString())->where('userId', $userId)->update(['mappedAccount' => $accountid, 'cash' => $sbdReport['sum']['cash']]);
								} else {
									$cashObj = new CashData();

									$cashObj['date'] = $date->toDateString();
									$cashObj['userId'] = $userId;
									$cashObj['mappedAccount'] = $accountid;
									$cashObj['cash'] = $sbdReport['sum']['cash'];
									$cashObj->save();

								}

							}
						}
					}
				}


				return response()->json(['message' => 'success'], 200);
			} else {
				return response()->json(['message' => "45"], 200);
			}

		} catch (\Exception $e) {
			return $e; //response()->json(['message' => ($e)], 200);
			// return $e->getMessage();
		}

	}


	public function getAllMappedUsers($account, $date = null)
	{
		if ($date == null)
			$date = Carbon::now()->toDateString();
		else
			$date = Carbon::parse($date)->toDateString();
		// $account = $this->accountid;

		$master = Acmap::where('startdate', '<=', $date)->where('role', 'master')->where('mappedTo', $account)->orderBy('startdate', 'desc')->first();
		$subs = Acmap::where('startdate', '<=', $date)->where('role', 'sub')->where('mappedTo', $account)->orderBy('startdate', 'desc')->first();
		$subIds = [];
		if (isset($subs))
			foreach ($subs as $sub) {
				$temp = Acmap::where('startdate', '<=', $date)->where('role', 'sub')->where('user', $sub['user'])->orderBy('startdate', 'desc')->first();
				if (isset($temp) && $temp['mappedTo'] == $account)
					$subIds[] = $temp['user'];
			}
		if (isset($master)) {
			return array_merge([$master['user']], $subIds);
		} else
			return $subIds;

	}

	public function processReportForPDO($sdate, $edate, $trId)
	{
		if (Schema::hasColumn('detaileds', 'user_id')) {
			$userIdCol = 'user_id';
		}

		$operatingAccounts = Detailed::distinct()->where('transactionId', $trId)->get(['accountid'])->toArray(); //pluck('accountid')->toArray();
		array_map(function ($arr) {
			if (!in_array($arr[0], $this->operatingAccounts))
				$this->operatingAccounts[] = $arr[0];
		}, $operatingAccounts);

		// $this->generateLocates($sdate, $edate, $trId, true);
		// Log::notice('Generating Locatedata : Complete');
		#Locate Generation Done
		$this->generateDetailed($sdate, $edate, $trId, true);
		Log::notice('Generating Detailed : Complete');
		$feedBack = $this->generatePreviousDayData($sdate, $edate, $trId, true);
		if ($feedBack['message'] == 'success') {
			return $feedBack['message'];

		} else {
			return $feedBack['message'];
		}
	}


	public function generateLocates($sdate, $edate, $trId, $alInsertFlag = false)
	{
		$adjustmentData = Adjustment::where('transactionId', $trId)->get();
		$locateDataArray = [];
		if (!empty($adjustmentData) && $adjustmentData->count() > 0) {
			foreach ($adjustmentData as $key => $value) {
				if (strpos($value->comment, 'Locate') !== false) {
					$temArray = explode(' ', trim($value->comment));
					if ($temArray[0] == 'Locate') {
						$qty = '';
						$symbol = '';
						// $refId  = '';
						foreach ($temArray as $pcs) {
							if ($symbol == '' && $pcs == strtoupper($pcs) && !is_numeric(str_replace(',', '', $pcs))) {
								$symbol = $pcs;
								// break;
							}

							if ($qty == '' && is_numeric(str_replace(',', '', $pcs))) {
								$qty = str_replace(',', '', $pcs);
								// break;
							}
						}
						// $refId = $temArray[4];

						$locateDataArray[] = [
							'date' => $value->date,
							'userId' => null,
							'accountid' => strval($value->accountid),
							'category' => $value->category,
							'comment' => $value->comment,
							'symbol' => $symbol,
							'qty' => (int) $qty,
							'bep' => $value->debit / $qty,
							'created_at' => new MongoDB\BSON\UTCDateTime(time() * 1000),
							'updated_at' => new MongoDB\BSON\UTCDateTime(time() * 1000),
							'status' => null,
						];
					}
				}
			}
		}

		if (isset($locateDataArray) && !empty($locateDataArray) && $alInsertFlag) {
			Locatedata::insert($locateDataArray);
		}
	}


	public function generateDetailed($sdate, $edate, $trId, $derInsertFlag = false)
	{
		# Get Detailed Data & Group By Date
		$dbData = Detailed::where('transactionId', $trId)->get();

		$dbData = $dbData->groupBy(function ($item) {
			return Carbon::createFromFormat('Y-m-d', $item->date)->format('Y-m-d');
		});
		# Complete

		# Get PreviousDay Stock From the DataBase
		$this->loadGcaFromDB($sdate, $edate);
		# Complete

		$this->calculatePositionFromStock();
		// return $this->prevPosData;

		foreach ($dbData as $trDate => $dbData1) {

			foreach ($dbData1 as $data) {

				#Calculating the Position
				$this->calculateCurrentPosition($data);
				#End of Position Calculation
				$grossRealizedDetails = [];
				if ($this->checkIfGrossCalculationRequired($data)) {

					#Calculating the Gross
					if ($this->ifDataExistsOnGrossCalcutationArray($data)) {
						$this->currentGrossTrDetailsArray = [];

						#This is for Current Day---------------------
						$this->calculateGrossForCurrentDay($data);
						#--------------------------------------------

						#This is for Previous Day--------------------
						$this->calculateGrossForPreviousDay($data);
						#--------------------------------------------
					}
					#End Gross Calculation
				}
				$this->createGCARowFromRemainingShare($data);


				#Creating new Row for Detailed Data
				$this->createDataRow($data);
				#end of Detailed Row Creation
			}

			$this->generateOpenDataFromDetailed($trDate);

		}

		# Insert Dreport Data
		if (
			isset($this->dReportDataArray) &&
			sizeof($this->dReportDataArray) > 0
			&& $derInsertFlag
		) {
			foreach (array_chunk($this->dReportDataArray, 900) as $t) {
				DB::table('dreports')->insert($t);
			}
		}
	}

	public function loadGcaFromDB($sdate, $edate)
	{

		$pdoIds = [];
		foreach ($this->operatingAccounts as $acc) {
			$date = Prevdaydata::
				// distinct('date')->
				where('accountid', $acc)->
				where('generatedOn', '<', $sdate)->
				orderBy('generatedOn', 'desc')->
				first();
			if (isset($date)) {
				$this->prevDayOpenDatesForOperatingAccounts[$acc] = $date->generatedOn;
				$aIds = Prevdaydata::
					where('accountid', $acc)->
					where('generatedOn', $date->generatedOn)->
					get()->pluck('_id')->toArray();
				// return $aIds;
				$pdoIds = array_merge($pdoIds, $aIds);

			}
		}
		// $this->grossCalculationArr = Prevdaydata::where('generatedOn', '<', $sdate)->
		// 						orderBy('date', 'asc')->whereIn('accountid', $this->operatingAccounts)->get();
		$this->grossCalculationArr = Prevdaydata::
			whereIn('_id', $pdoIds)->
			orderBy('date', 'asc')->
			get();

		if (isset($this->grossCalculationArr) && sizeof($this->grossCalculationArr) > 0) {

			foreach ($this->grossCalculationArr as $key => $value) {
				$closeData = Closedata::where('date', '>=', $value->date)->where('date', '<=', $edate)->where('symbol', $value->symbol)->where('accountid', strval($value->accountid))->where('userId', $value->userId)->count();
				if ($closeData > 0) {
					unset($this->grossCalculationArr[$key]);
					// break;
				}

			}
		}

		if (sizeof($this->grossCalculationArr) > 0) {
			$this->grossCalculationArr = $this->grossCalculationArr->toArray();
		} else
			$this->grossCalculationArr = [];
	}

	public function calculatePositionFromStock()
	{
		if (isset($this->grossCalculationArr))
			foreach ($this->grossCalculationArr as $key => $value) {
				$fl = false;
				$ind = 0;
				if (isset($this->prevPosData))
					foreach ($this->prevPosData as $key1 => $value1) {
						if (
							$value1['accountid'] == $value['accountid'] && $value1['userId'] == $value['userId'] &&
							$value1['symbol'] == $value['symbol']
						) {
							$fl = true;
							$ind = $key1;
							break;
						}
					}
				if ($fl) {
					//$this->prevPosData[$ind]['position'] = $value['qty']; this has been changed to the next line on 26th September 2021 for Not showing correcct position on Capsofia.traderspnl.com calculating from Previous day Open Upload by Excel
					$this->prevPosData[$ind]['position'] += $value['qty'];
				} else {
					$this->prevPosData[] = [
						'accountid' => strval($value['accountid']),
						'symbol' => $value['symbol'],
						'position' => (int) $value['qty'],
						'userId' => $value['userId'],
					];
				}

			}
	}

	public function calculateCurrentPosition($data)
	{

		if (!isset($this->prevPosData)) {
			$this->currentPosition = 0;
			if (strcasecmp('B', $data->type) == 0) {
				$this->currentPosition += $data->qty;
			} else if (strcasecmp('T', $data->type) == 0) {
				$this->currentPosition += (-1 * $data->qty);
			} else if (strcasecmp('S', $data->type) == 0) {
				$this->currentPosition -= $data->qty;
			}
			$this->prevPosData[] = [
				'accountid' => $data->accountid,
				'symbol' => $data->symbol,
				'position' => $this->currentPosition,
				'userId' => $data->userId,
			];
			$this->previousPosition = 0;
			// if($data->symbol=='EVI')
			// 	echo 'EVI FOUND';
		} else {
			$DP = 0; #DATA POSITION
			$DEPPDA = false; # DataExistsOnPreviousPositionDataArray

			for ($i = (count($this->prevPosData) - 1); $i >= 0; $i--) {
				if (($this->prevPosData[$i]['symbol'] == $data->symbol) && ($this->prevPosData[$i]['accountid'] == $data->accountid) && ($this->prevPosData[$i]['userId'] == $data->userId)) {
					$DP = $i;
					$DEPPDA = true;
					break;
				}
			}

			if (!$DEPPDA) {
				$this->currentPosition = 0;
				if (strcasecmp('B', $data->type) == 0) {
					$this->currentPosition += $data->qty;
				} else if (strcasecmp('T', $data->type) == 0) {
					$this->currentPosition += (-1 * $data->qty);
				} else if (strcasecmp('S', $data->type) == 0) {
					$this->currentPosition -= $data->qty;
				}
				$this->prevPosData[] = [
					'accountid' => $data->accountid,
					'symbol' => $data->symbol,
					'position' => $this->currentPosition,
					'userId' => $data->userId,
				];
				$this->previousPosition = 0;
			}

			if ($DEPPDA) {
				$temp = $this->prevPosData[$DP];
				if (strcasecmp('B', $data->type) == 0) {
					$this->currentPosition = $temp['position'] + $data->qty;
				} else if (strcasecmp('T', $data->type) == 0) {
					$this->currentPosition = $temp['position'] + (-1 * $data->qty);
				} else if (strcasecmp('S', $data->type) == 0) {
					$this->currentPosition = $temp['position'] - $data->qty;
				}
				$this->previousPosition = $this->prevPosData[$DP]['position'];
				$this->prevPosData[$DP]['position'] = $this->currentPosition;
			}

			// if($data->symbol=='EVI')
			// 	echo ($DEPPDA?'true':'false');
		}


		// if($this->currentPosition==0){
		// 	foreach ($this->grossCalculationArr as $key => $value) {
		// 		if($value['symbol']==$data->symbol && Carbon::parse($value['date'])<=Carbon::parse($data->date) && $value['userId']==$data->userId)
		// 			unset($this->grossCalculationArr[$key]);
		// 	}
		// }


		// if($data->type=='T' && $data->symbol=='JOUT' && $data->price==87.80)
		// dd($this->currentPosition);	
		// if($data->symbol=='GCBC')
		// 	echo('Calculated Current Position for :'.$data->userId.': '.$this->currentPosition.'<br>');	
	}

	public function checkIfGrossCalculationRequired($data)
	{
		$cgFlag = false; //Calculate gross Flag
		$this->gross = 0.00;

		if (strcasecmp('B', $data->type) == 0) {
			if ($this->previousPosition < 0) {
				#calculate Gross
				$cgFlag = true;
			}
		} else {
			if ($this->previousPosition > 0) {
				#calculate Gross
				$cgFlag = true;
			}
		}
		$this->shareQty = $data->qty;
		return $cgFlag;
	}

	public function ifDataExistsOnGrossCalcutationArray($data)
	{
		$tmp = false;
		foreach ($this->grossCalculationArr as $key => $gca) {
			if (
				$gca['accountid'] == $data->accountid &&
				$gca['userId'] == $data->userId &&
				$gca['symbol'] == $data->symbol
			) {
				$tmp = true;
				break;
			}
		}
		return $tmp;
	}

	public function calculateGrossForCurrentDay($data)
	{
		foreach ($this->grossCalculationArr as $gcaKey => $gca) {
			if ($this->shareQty == 0)
				break;
			if (
				$gca['accountid'] == $data->accountid &&
				$gca['userId'] == $data->userId &&
				$gca['symbol'] == $data->symbol &&
				$gca['date'] == $data->date
			) {

				// if($this->shareQty==0)
				// break;

				#Merging Stock from the Current Day Stock First

				if ($this->shareQty >= abs($gca['qty'])) {

					$tmp = [
						'cb_date' => $gca['date'],
						'cb_qty' => $gca['qty'],
						'cb_price' => abs($gca['price']),
						'tr_qty' => ($data->type != 'B' ? '-' : '') . ($gca['qty']),
						'tr_price' => $data->price,
						'ncb_qty' => '',
						'ncb_price' => '',
						'gross_exp' => '',
						'gross_amount' => '',
					];
					$this->shareQty -= abs($gca['qty']);
					if ((strcasecmp('B', $data->type) == 0) && $this->previousPosition < 0) {
						$this->gross += ($gca['price'] - $data->price) * abs($gca['qty']);

						$tmp['gross_exp'] = ($gca['price'] - $data->price) . ' * ' . abs($gca['qty']);
					} else {
						$this->gross += ($data->price - $gca['price']) * abs($gca['qty']);

						$tmp['gross_exp'] = ($data->price - $gca['price']) . ' * ' . abs($gca['qty']);
					}
					$tmp['gross_amount'] = $this->gross;
					$this->currentGrossTrDetailsArray[] = $tmp;

					unset($this->grossCalculationArr[$gcaKey]);
				} else {
					$tmp = [
						'cb_date' => $gca['date'],
						'cb_qty' => $gca['qty'],
						'cb_price' => abs($gca['price']),
						'tr_qty' => ($data->type != 'B' ? '-' : '') . ($this->shareQty),
						'tr_price' => $data->price,
						'ncb_qty' => '',
						'ncb_price' => abs($gca['price']),
						'gross_exp' => '',
						'gross_amount' => '',
					];
					if ((strcasecmp('B', $data->type) == 0) && $this->previousPosition < 0) {
						$this->gross += ($gca['price'] - $data->price) * abs($this->shareQty);
						$this->grossCalculationArr[$gcaKey]['qty'] += $this->shareQty;

						$tmp['ncb_qty'] = $this->grossCalculationArr[$gcaKey]['qty'];
						$tmp['gross_exp'] = ($gca['price'] - $data->price) . ' * ' . abs($this->shareQty);
					} else {
						$this->gross += ($data->price - $gca['price']) * abs($this->shareQty);
						$this->grossCalculationArr[$gcaKey]['qty'] -= $this->shareQty;


						$tmp['ncb_qty'] = $this->grossCalculationArr[$gcaKey]['qty'];
						$tmp['gross_exp'] = ($data->price - $gca['price']) . ' * ' . abs($this->shareQty);
					}
					$tmp['gross_amount'] = $this->gross;
					$this->currentGrossTrDetailsArray[] = $tmp;

					$this->shareQty = 0;
				}
			}
		}
	}


	public function calculateGrossForPreviousDay($data)
	{
		if ($this->shareQty != 0) {
			foreach ($this->grossCalculationArr as $gcaKey => $gca) {
				if (
					$gca['accountid'] == $data->accountid &&
					$gca['userId'] == $data->userId &&
					$gca['symbol'] == $data->symbol
				) {

					if ($this->shareQty == 0)
						break;

					#Merging Stock from the Current Day Stock First

					if ($this->shareQty >= abs($gca['qty'])) {
						$tmp = [
							'cb_date' => $gca['date'],
							'cb_qty' => $gca['qty'],
							'cb_price' => abs($gca['price']),
							'tr_qty' => ($data->type != 'B' ? '-' : '') . ($gca['qty']),
							'tr_price' => $data->price,
							'ncb_qty' => '',
							'ncb_price' => '',
							'gross_exp' => '',
							'gross_amount' => '',
						];
						$this->shareQty -= abs($gca['qty']);
						if ((strcasecmp('B', $data->type) == 0) && $this->previousPosition < 0) {
							$this->gross += ($gca['price'] - $data->price) * abs($gca['qty']);
							// $this->shareQty +=$gca['qty'];
							$tmp['gross_exp'] = ($gca['price'] - $data->price) . ' * ' . abs($gca['qty']);
						} else {
							$this->gross += ($data->price - $gca['price']) * abs($gca['qty']);
							// $this->shareQty -=$gca['qty'];
							$tmp['gross_exp'] = ($data->price - $gca['price']) . ' * ' . abs($gca['qty']);
						}
						$tmp['gross_amount'] = $this->gross;
						$this->currentGrossTrDetailsArray[] = $tmp;

						unset($this->grossCalculationArr[$gcaKey]);
					} else {
						$tmp = [
							'cb_date' => $gca['date'],
							'cb_qty' => $gca['qty'],
							'cb_price' => abs($gca['price']),
							'tr_qty' => ($data->type != 'B' ? '-' : '') . ($this->shareQty),
							'tr_price' => $data->price,
							'ncb_qty' => '',
							'ncb_price' => abs($gca['price']),
							'gross_exp' => '',
							'gross_amount' => '',
						];
						if ((strcasecmp('B', $data->type) == 0) && $this->previousPosition < 0) {
							$this->gross += ($gca['price'] - $data->price) * abs($this->shareQty);
							$this->grossCalculationArr[$gcaKey]['qty'] += $this->shareQty;


							$tmp['ncb_qty'] = $this->grossCalculationArr[$gcaKey]['qty'];
							$tmp['gross_exp'] = ($gca['price'] - $data->price) . ' * ' . abs($this->shareQty);
						} else {
							$this->gross += ($data->price - $gca['price']) * abs($this->shareQty);
							$this->grossCalculationArr[$gcaKey]['qty'] -= $this->shareQty;

							$tmp['ncb_qty'] = $this->grossCalculationArr[$gcaKey]['qty'];
							$tmp['gross_exp'] = ($data->price - $gca['price']) . ' * ' . abs($this->shareQty);
						}
						$tmp['gross_amount'] = $this->gross;
						$this->currentGrossTrDetailsArray[] = $tmp;

						$this->shareQty = 0;
					}
				}
			}
		}
	}

	public function createGCARowFromRemainingShare($data)
	{
		if ($this->shareQty != 0) {
			$DK = 0;
			$dataExists = false;
			//section 1 code ta aage enabled chilo, kintu same price hole add kore dicche [Dated 22-01-2022 05:10AM]
			if (strcasecmp('B', $data->type) == 0) {
				$this->grossCalculationArr[] = [
					'accountid' => $data->accountid,
					'userId' => $data->userId,
					'symbol' => $data->symbol,
					'date' => $data->date,
					'qty' => $this->shareQty,
					'price' => $data->price,
					'position' => $this->currentPosition,
				];
			} else {
				$this->grossCalculationArr[] = [
					'accountid' => $data->accountid,
					'userId' => $data->userId,
					'symbol' => $data->symbol,
					'date' => $data->date,
					'qty' => 0 - $this->shareQty,
					'price' => $data->price,
					'position' => $this->currentPosition,
				];
			}
		}
		// modified at 19-10-2020 : Start
		if ($this->currentPosition == 0) {
			foreach ($this->grossCalculationArr as $key => $value) {
				if ($value['symbol'] == $data->symbol && $value['date'] == $data->date && $value['userId'] == $data->userId)
					unset($this->grossCalculationArr[$key]);
			}
		} # modified at 19-10-2020 : End

		/*
			  if($data->symbol=='GCBC'){
				  $str = "<table border='2'><tr>";
				  $str .= "<th>AccID</th><th>UID</th><th>Sym</th><th>Date</th><th>Qty</th><th>Price</th><th>Position</th></tr>";
				  foreach ($this->grossCalculationArr as $key => $value) {
					  $str .= '<tr><td>'.$value['accountid'].'</td><td>'.$value['userId'].'</td><td>'.$value['symbol'].'</td><td>'.$value['date'].'</td><td>'.$value['qty'].'</td><td>'.$value['price'].'</td><td>'.$value['position'].'</td></tr>';

				  }
					  $str .= "</table><br>";
				  echo '<br><b> Iteration For '.$data->id.'_'.$data->symbol.'  User: '.$data->userId.' # <br>Current Position'.$this->currentPosition.'</b><br>';
				  echo $str;//.json_encode($this->grossCalculationArr).'<br>';
			  }*/
	}


	public function createDataRow($data)
	{
		$temp = $data->toArray();
		$temp['position'] = $this->currentPosition;
		$temp['gross'] = $this->gross;
		$temp['commission'] = 0.00;
		$temp['total'] = 0.00;
		$temp['net'] = 0.00;
		$temp['grossRealizedDetails'] = $this->currentGrossTrDetailsArray;

		$this->dReportDataArray[] = $temp;
		// $this->dReportDataArray[] = [
		// 	'userId' => $data->userId,
		// 	'symbol' => $data->symbol,
		// 	'accountid' => $data->accountid,
		// 	'date' => $data->date,
		// 	'time' => $data->time,
		// 	'orderid' => $data->orderid,
		// 	'fillid' => $data->fillid,
		// 	'route' => $data->route,
		// 	'liq' => $data->liq,
		// 	'type' => $data->type,
		// 	'qty' => $data->qty,
		// 	'position' => $this->currentPosition,
		// 	'gross' => $this->gross,
		// 	'price' => $data->price,
		// 	'commission' => 0.00,
		// 	'ecnfee' => $data->ecnfee,
		// 	'sec' => $data->sec,
		// 	'taf' => $data->taf,
		// 	'nscc' => $data->nscc,
		// 	'clr' => $data->clr,
		// 	'orf' => $data->orf,
		// 	'ptfpf' => $data->ptfpf,
		// 	'total' => 0.00,
		// 	'net' => 0.00,
		// ];
		$this->gross = 0.00;
		// $this->currentGrossTrDetailsArray = [];
	}

	public function generateOpenDataFromDetailed($trDate)
	{
		if (sizeof($this->grossCalculationArr) != 0)
			foreach ($this->grossCalculationArr as $key => $value) {
				$this->grossCalculationArr[$key]['generatedOn'] = $trDate;
			}

		$tempArr = array($trDate => $this->grossCalculationArr);
		$this->openData = array_merge($this->openData, $tempArr);
	}


	public function generatePreviousDayData($sdate, $edate, $trId, $pddInsertFlag = false)
	{

		// dd($this->openData);exit;
		if (sizeof($this->openData) != 0) {
			$openDates = [];
			$closePriceData = Open::where('transactionId', $trId)->whereIn('accountid', $this->operatingAccounts)->where('userId', null)->get();
			$closePriceData = $closePriceData->groupBy(function ($item) {
				return Carbon::createFromFormat('Y-m-d', $item->date)->format('Y-m-d');
			});
			// dd($closePriceData);
			$opDataError = false;



			$exArray = array();
			$prevDate = 0;
			foreach ($this->openData as $dateKeyX => $valueX) {
				if ($prevDate == 0) {
					$prevDate = $dateKeyX;
				} else {

					if (Carbon::parse($prevDate)->diffInDays(Carbon::parse($dateKeyX)) > 1) {
						$prevDate1 = Carbon::parse($prevDate)->addDay();
						$endDate = Carbon::parse($dateKeyX);

						while ($prevDate1->lt($endDate)) {

							if ($prevDate1->isSaturday() || $prevDate1->isSunday()) {
							} else if (isset($closePriceData[$prevDate1->toDateString()])) {
								$exArray[$prevDate1->toDateString()] = $this->openData[$prevDate];
								foreach ($exArray[$prevDate1->toDateString()] as $key => $valueXC) {
									$exArray[$prevDate1->toDateString()][$key]['generatedOn'] = $prevDate1->toDateString();

								}
							}
							$prevDate1->addDay();
						}
					}
					$prevDate = $dateKeyX;
				}
			}

			$this->openData = array_merge($this->openData, $exArray);
			ksort($this->openData);


			# Adding Close price from 'Open Excel Data(i.e. closePriceData)' to the 'open data array(i.e. openData)' generated from Detailed

			foreach ($this->openData as $dateKey => $value) {
				foreach ($value as $ind => $pdd) {

					if (isset($closePriceData[$dateKey])) {
						$opDataError = true;
						foreach ($closePriceData[$dateKey] as $dbValue) {
							if (
								$dbValue['date'] == $dateKey &&
								$dbValue['symbol'] == $pdd['symbol'] &&
								$dbValue['accountid'] == $pdd['accountid'] &&
								$dbValue['closeprice'] != null
							) {
								$opDataError = false;
								$this->openData[$dateKey][$ind]['closeprice'] = $dbValue['closeprice'];
								// echo 'MAtch Found<br>';
								break;
							} else {
								#THis Portion Only enable when you need trouble Shooting

								// echo $dbValue['symbol'].': '.$dbValue['date'].' == '. $dateKey.'<br>';
								// echo $dbValue['symbol'].': '.$dbValue['symbol'].' == '. $pdd['symbol'].'<br>';
								// echo $dbValue['symbol'].': '.$dbValue['accountid'].' == '. $pdd['accountid'].'<br>';
								// echo $dbValue['symbol'].': '.$dbValue['closeprice'].'<br>';
								// // dd($this->openData);
								// dd($pdd);	
								// exit;
							}
						}
					}
					if ($opDataError) {


						Log::error("A Previous Day(" . $pdd['date'] . ") Open(" . $pdd['symbol'] . "-" . $pdd['qty'] . ") Exists for the A/C which shouldn't be");
						Log::debug($pdd['accountid'] . ': You Must check for any Userdata Mismatch on the same day or previous day');
						// $this->resetReportForMapChange($sdate.' - '.$edate, $this->operatingAccounts, null, 'Prev-day-data Generator');
						break;
						// return false;





						/*
										  echo $dbValue['symbol'].': '.$dbValue['date'].' == '. $dateKey.'<br>';
										  echo $dbValue['symbol'].': '.$dbValue['symbol'].' == '. $pdd['symbol'].'<br>';
										  echo $dbValue['symbol'].': '.$dbValue['accountid'].' == '. $pdd['accountid'].'<br>';
										  echo $dbValue['symbol'].': '.$dbValue['closeprice'].'<br>';
										  // dd($this->openData);
										  echo '<pre>';
										  print_r($pdd);
										  echo json_encode($closePriceData[$dateKey]);	
										  echo '</pre>';
										  // exit;
										  // break;
										  */
					}
				}
				if ($opDataError)
					break;
			}
			# End
			// dd($this->openData);
			if ($opDataError) {
				// Session::flash('error', 'Open Excel Data Mismatch with Generated Data');
				return ['message' => 'Open Excel Data Mismatch with Generated Data'];
			} else {
				// return $this->openData;
				// return 'Data Validated';
			}

			# ---------------------------------------------

			# Getting Previous Day Data Array For Database from OpenData
			foreach ($this->openData as $key => $value) {
				foreach ($value as $pdd) {
					$pdd['unrealizeddelta'] = 0;
					$pdd['created_at'] = new MongoDB\BSON\UTCDateTime(time() * 1000);
					$pdd['updated_at'] = $edate;
					unset($pdd['_id']);
					$pddData[] = $pdd;
					if (!in_array($pdd['generatedOn'], $openDates)) {
						$openDates[] = $pdd['generatedOn'];
					}
				}
			}

			// Removing 'id' Key element fro the arraylist 
			if (isset($pddData)) {
				foreach ($pddData as $key => $value) {
					if (array_key_exists('id', $value))
						unset($pddData[$key]['id']);
				}
			}

			// 'id' remove complete
			// dd($pddData);
			$errorData = '';
			if (isset($pddData) && sizeof($pddData) > 0 && $pddInsertFlag) { //==================================
				foreach ($pddData as $key => $value) {
					if (!array_key_exists('closeprice', $value)) {
						$errorData = $value;
						break;
					}
				}
			}
			if (is_array($errorData)) {
				// dd($errorData);
				Log::error('Getting<br> Open[' . User::find($errorData['userId'])->name . '| ' . $errorData['accountid'] . '_' . $errorData['symbol'] . '_' . $errorData['qty'] . '| ' . $errorData['generatedOn'] . '| ' . $errorData['price'] . '] from Compiled Data. But No Close Price FOUND for the same from Server');
				Log::debug('You Must check for any Userdata Mismatch on the same day or previous day');
				// $this->resetReportForMapChange($sdate.' - '.$edate, $this->operatingAccounts, null, 'Prev-day-data Generator');
				return ['message' => 'Close Price for Open Data not found'];
				// return false;
			} else {
				if (isset($pddData) && sizeof($pddData) > 0 && $pddInsertFlag) {
					// foreach ($pddData as $key => $value) {
					// 	if(isset($value['_id']))
					// 		unset($pddData[$key]['_id']);
					// }
					// return $pddData;
					Prevdaydata::insert($pddData); //=========================================

					$period = CarbonPeriod::create($sdate, $edate);
					// Iterate over the period
					$remainingOpenDates = [];
					foreach ($period as $date) {
						if (!in_array($date->toDateString(), $openDates))
							if (Detailed::where('transactionId', $trId)->whereIn('accountid', $this->operatingAccounts)->where('date', $date->toDateString())->get()->count() == 0)
								$remainingOpenDates[] = $date->toDateString();
					}
					if (sizeof($remainingOpenDates) > 0) {
						if (Open::where('transactionId', $trId)->whereIn('accountid', $this->operatingAccounts)->whereIn('date', $remainingOpenDates)->get()->count() > 0) {
							$closePriceData = Open::where('transactionId', $trId)->whereIn('date', $remainingOpenDates)->whereIn('accountid', $this->operatingAccounts)->where('userId', null)->get();
							$closePriceData = $closePriceData->groupBy(function ($item) {
								return Carbon::createFromFormat('Y-m-d', $item->date)->format('Y-m-d');
							});
							$finalData = [];
							$finalData2 = [];
							// return $openDates;
							foreach ($remainingOpenDates as $rod) {

								$firstDate = null;
								$secondDate = null;
								$prevPdoData = [];
								// foreach (array_reverse($openDates) as $odDate) {
								// 	if(Carbon::parse($odDate)<Carbon::parse($rod)){
								// 		$firstDate = Carbon::parse($odDate);
								// 		break;
								// 	}
								// }

								$tempPrevPdoData = Prevdaydata::where('generatedOn', '<', $rod)->whereIn('accountid', $this->operatingAccounts)->orderBy('generatedOn', 'DESC')->first();
								if (isset($tempPrevPdoData)) {
									$firstDate = Carbon::parse($tempPrevPdoData['generatedOn']);
								}



								foreach (array_reverse(array_keys($finalData2)) as $odDate) {
									if (Carbon::parse($odDate) < Carbon::parse($rod)) {
										$secondDate = Carbon::parse($odDate);
										break;
									}
								}

								if ($firstDate != null && $secondDate != null) {
									if ($firstDate > $secondDate) {
										$prevPdoData = Prevdaydata::where('generatedOn', $firstDate->toDateString())->whereIn('accountid', $this->operatingAccounts)->get()->toArray();
									} else {
										$prevPdoData = $finalData2[$secondDate->toDateString()];
									}
								} else if ($firstDate == null && $secondDate != null) {
									$prevPdoData = $finalData2[$secondDate->toDateString()];
								} else if ($firstDate != null && $secondDate == null) {
									$prevPdoData = Prevdaydata::where('generatedOn', $firstDate->toDateString())->whereIn('accountid', $this->operatingAccounts)->get()->toArray();
								} else {

								}

								if (isset($closePriceData[($rod)])) {
									$tempPDO1 = $prevPdoData;
									foreach ($tempPDO1 as $tempPDO11) {
										if (isset($tempPDO11['_id']))
											unset($tempPDO11['_id']);
										if (isset($tempPDO11['created_at']))
											unset($tempPDO11['created_at']);
										if (isset($tempPDO11['updated_at']))
											unset($tempPDO11['updated_at']);

										$tempPDO11['generatedOn'] = $rod;
										foreach ($closePriceData[$rod] as $cloP) {
											if ($cloP['symbol'] == $tempPDO11['symbol']) {
												$tempPDO11['closeprice'] = $cloP['closeprice'];
												break;
											}
										}
										// $finalData[$date->toDateString()][$tempPDO11['symbol']][] = $tempPDO11;
										$finalData[] = $tempPDO11;
										$finalData2[$rod][] = $tempPDO11;
									}
								}





								/*
														$prevDate = Carbon::parse($rod)->subDay();
														// return $prevDate;
														if($prevDate->dayName=="Saturday")
															$prevDate = $prevDate->subDay();
														if($prevDate->dayName=="Sunday")
															$prevDate = $prevDate->subDays(2);
														$prevPdoData = Prevdaydata::where('generatedOn', $prevDate->toDateString())->whereIn('accountid', $this->operatingAccounts)->get()->toArray();

														// if(!Carbon::parse($sdate)->isWeekend()){

														// }

														if(!empty($prevPdoData) && sizeof($prevPdoData)>0){
															// $closePriceData = Open::where('transactionId', $trId)->whereIn('accountid', $this->operatingAccounts)->where('userId', null)->get();
															// $closePriceData = $closePriceData->groupBy(function($item) {
															// 	return Carbon::createFromFormat('Y-m-d', $item->date)->format('Y-m-d');
															// });

															// $period = CarbonPeriod::create($sdate, $edate);
															// Iterate over the period
															// foreach ($period as $date) {
																// if(!$date->isWeekend()){
																	if(isset($closePriceData[($rod)])){
																		$tempPDO1 = $prevPdoData;
																		foreach ($tempPDO1 as $tempPDO11) {
																			unset($tempPDO11['_id']);
																			unset($tempPDO11['created_at']);
																			unset($tempPDO11['updated_at']);
																			$tempPDO11['generatedOn'] = $rod;
																			foreach ($closePriceData[$rod] as $cloP) {
																				if($cloP['symbol']==$tempPDO11['symbol']){
																					$tempPDO11['closeprice'] = $cloP['closeprice'];
																					break;
																				}
																			}
																			// $finalData[$date->toDateString()][$tempPDO11['symbol']][] = $tempPDO11;
																			$finalData[] = $tempPDO11;
																			$finalData2[$rod][] = $tempPDO11;
																		}
																	}
																// }    
															// }
														}else{
															if(isset($finalData2[$prevDate->toDateString()])){
																if(isset($closePriceData[($rod)])){
																	$tempPDO1 = $finalData2[$prevDate->toDateString()];
																	foreach ($tempPDO1 as $tempPDO11) {
																		// unset($tempPDO11['_id']);
																		// unset($tempPDO11['created_at']);
																		// unset($tempPDO11['updated_at']);
																		$tempPDO11['generatedOn'] = $rod;
																		foreach ($closePriceData[$rod] as $cloP) {
																			if($cloP['symbol']==$tempPDO11['symbol']){
																				$tempPDO11['closeprice'] = $cloP['closeprice'];
																				break;
																			}
																		}
																		// $finalData[$date->toDateString()][$tempPDO11['symbol']][] = $tempPDO11;
																		$finalData[] = $tempPDO11;
																		$finalData2[$rod][] = $tempPDO11;
																	}
																}
															}
														}*/
							}
							Prevdaydata::insert($finalData);

						}
					}
				}
				return ['message' => 'success'];
				// return true;
			}


			// return 'Previous Inserted';
			# ----------------------------------------------

		} else {
			Log::info("No OpenData For Next Day FOUND");
			$prevDate = Carbon::parse($sdate)->subDay();
			// return $prevDate;
			if ($prevDate->dayName == "Saturday")
				$prevDate = $prevDate->subDay();
			if ($prevDate->dayName == "Sunday")
				$prevDate = $prevDate->subDays(2);
			$prevPdoData = Prevdaydata::where('generatedOn', $prevDate->toDateString())->whereIn('accountid', $this->operatingAccounts)->get()->toArray();

			// if(!Carbon::parse($sdate)->isWeekend()){

			// }

			if (!empty($prevPdoData) && sizeof($prevPdoData) > 0) {
				$closePriceData = Open::where('transactionId', $trId)->whereIn('accountid', $this->operatingAccounts)->where('userId', null)->get();
				$closePriceData = $closePriceData->groupBy(function ($item) {
					return Carbon::createFromFormat('Y-m-d', $item->date)->format('Y-m-d');
				});

				$period = CarbonPeriod::create($sdate, $edate);
				// Iterate over the period
				$finalData = [];
				foreach ($period as $date) {
					// if(!$date->isWeekend()){
					if (isset($closePriceData[$date->toDateString()])) {
						$tempPDO1 = $prevPdoData;
						foreach ($tempPDO1 as $tempPDO11) {
							$tempPDO11['generatedOn'] = $date->toDateString();
							unset($tempPDO11['_id']);
							unset($tempPDO11['created_at']);
							unset($tempPDO11['updated_at']);
							foreach ($closePriceData[$date->toDateString()] as $cloP) {
								if ($cloP['symbol'] == $tempPDO11['symbol']) {
									$tempPDO11['closeprice'] = $cloP['closeprice'];
									break;
								}
							}
							// $finalData[$date->toDateString()][$tempPDO11['symbol']][] = $tempPDO11;
							$finalData[] = $tempPDO11;
						}
					}
					// }    
				}
				// return ['pdo' => $finalData];
				Prevdaydata::insert($finalData);


				return ['message' => 'success'];
			} else {
				return ['message' => 'success'];
			}
		}
	}


	public function generateOpenData($sdate, $edate, $oprInsertFlag = false)
	{
		#Generating Open Report according to the PreviousDayData
		$hehe = [];
		$krishna = [];
		$todayUn = [];
		$currentSessionUnrealized = [];
		$todayUnTest = [];
		$pdu = 0;


		$pddData = Prevdaydata::where('generatedOn', '>=', $sdate)->where('generatedOn', '<=', $edate)->whereIn('accountid', $this->operatingAccounts)->orderBy('_id', 'ASC')->get();

		# Grouping Data(By Date, accountid_userid, symbol)
		if (isset($pddData) && sizeof($pddData) > 0) {
			foreach ($pddData as $key => $value) {
				$pddData[$key]->date = $pddData[$key]->generatedOn;
			}
		}

		$pddData = $pddData->groupBy(function ($item) {
			return Carbon::createFromFormat('Y-m-d', $item->date)->format('Y-m-d');
		});
		// return $pddData;
		foreach ($pddData as $key => $value) {
			$pddData[$key] = $value->groupBy(function ($item) {
				return $item->accountid . '_' . $item->userId;
			});
		}

		foreach ($pddData as $key1 => $value1) {
			foreach ($value1 as $key2 => $value2) {
				$pddData[$key1][$key2] = $value2->groupBy(function ($item) {
					return $item->symbol;
				});
			}
		}

		#Grouping Complete



		$alreadyDone = array();

		$aIdsX = [];

		foreach ($this->prevDayOpenDatesForOperatingAccounts as $accX => $dateX) {
			$tmp = Prevdaydata::where('generatedOn', $dateX)->where('accountid', $accX)->get()->pluck('_id')->toArray();
			$aIdsX = array_merge($aIdsX, $tmp);
		}
		// return $pddData;

		if (isset($pddData) && sizeof($pddData) > 0) {

			// return $pddData;
			$period = CarbonPeriod::create($sdate, $edate);
			// return $period;
			foreach ($period as $loopDate1) {
				$loopDate = $loopDate1->toDateString();
				if (isset($pddData[$loopDate])) {
					$usersData = $pddData[$loopDate];
					foreach ($usersData as $userIdKey => $symbolsData) {
						$loopAC = explode('_', $userIdKey);

						$loopUSER = $loopAC[1];
						$loopAC = $loopAC[0];
						foreach ($symbolsData as $symbolKey => $dataCollection) {
							$avgprice = 0;
							$ccy = '';
							$spot = '';
							$tempQty = 0;

							$notFoundPDu = true;
							$prevDayUnrealized = 0;

							foreach ($dataCollection as $singleData) {
								$avgprice += abs($singleData->qty * $singleData->price);
								$tempQty += ($singleData->qty);
							}

							# Getting Previous Day Unrealized


							if (sizeof($currentSessionUnrealized) > 0) {
								$backDate = Carbon::parse($loopDate)->copy()->subDay();
								$targetDate = Carbon::parse($sdate);
								while ($backDate >= $targetDate) {
									if (isset($currentSessionUnrealized[$backDate->toDateString()][$loopAC][$loopUSER][$symbolKey])) {

										if ($backDate->diffInDays(Carbon::parse($loopDate)) == 1) {
											$prevDayUnrealized = $currentSessionUnrealized[$backDate->toDateString()][$loopAC][$loopUSER][$symbolKey];
											$notFoundPDu = false;
											break;
										} else {

											$ovi = Dreport::where('date', '<', $loopDate)->
												where('date', '>', $backDate->toDateString())->
												where('accountid', $loopAC)->
												where('userId', $loopUSER)->
												where('symbol', $symbolKey)->
												orderBy('date', 'DESC')->
												first();
											if (isset($ovi)) {
												$ovi = Dreport::where('date', $ovi->date)->
													// where('date', '>', $tempTodayUn[$key]['date'])->
													where('accountid', $loopAC)->
													where('userId', $loopUSER)->
													where('symbol', $symbolKey)->
													orderBy('_id', 'DESC')->
													first();
												if ($ovi['position'] == 0)
													$prevDayUnrealized = 0;
												else
													$prevDayUnrealized = $currentSessionUnrealized[$backDate->toDateString()][$loopAC][$loopUSER][$symbolKey];
											} else {
												$prevDayUnrealized = $currentSessionUnrealized[$backDate->toDateString()][$loopAC][$loopUSER][$symbolKey];
											}
											$notFoundPDu = false;
											break;
										}
										// $prevDayUnrealized = $currentSessionUnrealized[$backDate->toDateString()][$loopAC][$loopUSER][$symbolKey];
										// $notFoundPDu = false;
										// break;
									}
									$backDate = $backDate->copy()->subDay();
								}
							}

							if ($notFoundPDu) {
								$prevDayUnrealizedO = Oreport::where('date', '<', $loopDate)->
									where('symbol', $symbolKey)->
									where('accountid', $loopAC)->
									where('userId', $loopUSER)->
									orderBy('date', 'desc')->
									first();

								$prevDayUnrealizedC = Dreport::where('date', '<', $loopDate)->
									where('symbol', $symbolKey)->
									where('accountid', $loopAC)->
									where('userId', $loopUSER)->
									// where('position', 0)->
									orderBy('date', 'desc')->
									first();
								if (!empty($prevDayUnrealizedC)) {
									$prevDayUnrealizedC = Dreport::where('date', $prevDayUnrealizedC['date'])->
										where('symbol', $symbolKey)->
										where('accountid', $loopAC)->
										where('userId', $loopUSER)->
										// where('position', 0)->
										orderBy('_id', 'desc')->
										first();
									if (!empty($prevDayUnrealizedO) && $prevDayUnrealizedC->position == 0 && Carbon::parse($prevDayUnrealizedC->date) > Carbon::parse($prevDayUnrealizedO->date))
										$prevDayUnrealized = 0;
									else if (!empty($prevDayUnrealizedO) && $prevDayUnrealizedC->position == 0 && Carbon::parse($prevDayUnrealizedC->date) < Carbon::parse($prevDayUnrealizedO->date))
										$prevDayUnrealized = $prevDayUnrealizedO;
									else if (!empty($prevDayUnrealizedO) && $prevDayUnrealizedC->position != 0)
										$prevDayUnrealized = $prevDayUnrealizedO;
									else if (empty($prevDayUnrealizedO) && $prevDayUnrealizedC->position == 0)
										$prevDayUnrealized = 0;
									else
										$prevDayUnrealized = 0;

								} else {
									$prevDayUnrealized = $prevDayUnrealizedO;
								}

								if (isset($prevDayUnrealized) && $prevDayUnrealized != null && is_object($prevDayUnrealized)) {
									$prevDayUnrealized = $prevDayUnrealized['un'];
								} else
									$prevDayUnrealized = 0;
							}

							# End of Previous Day Searching

							$avgprice /= $tempQty;
							$cost = $tempQty * abs($avgprice);
							$marketvalue = $singleData->closeprice * $tempQty;
							$un = $marketvalue - $cost;
							$und = $un - $prevDayUnrealized;
							// if($loopDate=="2021-01-25")
							$krishna[$loopDate][] = [
								'userId' => $loopUSER,
								'symbol' => $symbolKey,
								'accountid' => $loopAC,
								'qty' => $tempQty,
								'date' => $loopDate,
								'avgprice' => abs($avgprice),
								'closeprice' => $singleData->closeprice,
								'ccy' => $ccy,
								'spot' => $spot,
								'cost' => $cost,
								'marketvalue' => $marketvalue,
								'und' => $und,
								'un' => $un,
								'created_at' => new MongoDB\BSON\UTCDateTime(time() * 1000),
								'updated_at' => new MongoDB\BSON\UTCDateTime(time() * 1000),
							];

							$currentSessionUnrealized[$loopDate][$loopAC][$loopUSER][$symbolKey] = $un;
							// $todayUn[$loopDate][$loopAC][$loopUSER][$symbolKey] = $un;
						}

					}

				}
			}


		}
		// return $krishna;
		if (isset($krishna) && sizeof($krishna) > 0 && $oprInsertFlag) { //==================================
			foreach ($krishna as $keyKr => $valueKr) {
				// code...
				Oreport::insert($valueKr);
			}
		}

		#-------------------------------------------------------
	}


	public function generateCloseForOpen($sdate, $edate, $cdInsertFlag = false)
	{
		$closeDataForOpen = [];
		$dat = Dreport::where('date', '>=', ($sdate))->where('date', '<=', ($edate))->whereIn('accountid', $this->operatingAccounts)->get();
		$dat = $dat->groupBy(function ($item) {
			return Carbon::createFromFormat('Y-m-d', $item->date)->format('Y-m-d');
		});

		foreach ($dat as $key => $value) {
			$dat[$key] = $value->groupBy(function ($item) {
				return $item->accountid;
			});
		}

		foreach ($dat as $key2 => $value2) {
			foreach ($value2 as $key => $value) {
				$dat[$key2][$key] = $value->groupBy(function ($item) {
					return $item->userId;
				});
			}
		}


		foreach ($dat as $key3 => $value3) {
			foreach ($value3 as $key2 => $value2) {
				foreach ($value2 as $key => $value) {
					$dat[$key3][$key2][$key] = $value->groupBy(function ($item) {
						return $item->symbol;
					});
				}
			}
		}
		// return $dat;
		$iskaPon = [];
		$AE = array();
		foreach ($dat as $key4 => $value4) { #---------Date
			foreach ($value4 as $key3 => $value3) { #---------accountid
				foreach ($value3 as $key2 => $value2) { #---------userId
					foreach ($value2 as $key => $valueD) { #---------symbol
						if ($valueD->last()->position == 0) {

							// echo $key.'is zero';
							$flag = false;
							$tempo = [];
							if (array_key_exists($key3 . '_' . $key2 . '_' . $key, $AE)) {
								$tempo = Oreport::where('date', '<', Carbon::parse($key4)->toDateString())->where('accountid', strval($key3))->where('date', '>', Carbon::parse($AE[$key3 . '_' . $key2 . '_' . $key])->toDateString())->orderBy('date', 'DESC')->get();
								$flag = true;
							} else
								$tempo = Oreport::where('date', '<', Carbon::parse($key4)->toDateString())->where('accountid', strval($key3))->orderBy('date', 'DESC')->get();

							// return Oreport::where('date', '<', Carbon::parse($key4)->toDateString())->get();
							if (sizeof($tempo) > 0) {

								$tempo = $tempo->groupBy(function ($item) {
									return Carbon::createFromFormat('Y-m-d', $item->date)->format('Y-m-d');
								});
								$tempo = $tempo->first();
								if (isset($tempo) && !empty($tempo)) {
									// echo 'Oreport Data Found for '.$key;
									foreach ($tempo as $value) {
										if (strval($value->accountid) == strval($key3) && $value->userId == $key2 && $value->symbol == $key) {
											// $iskaPon[] = $tempo;
											// echo 'Searching Closedata for'.$key;
											$closeDataC = Closedata::where('date', '>', $value->date)->where('date', '<', $key4)->where('accountid', $value->accountid)->where('symbol', $value->symbol)->where('userId', $value->userId)->count();
											if ($closeDataC == 0) {
												// echo 'Yeppii.. Closedata not Found. thats a good sign';
												$un = $value->un;
												if ($flag) {
													// echo 'Merging: '.$key.' on '.$key4;
													$AE[$key3 . '_' . $key2 . '_' . $key] = $key4;
												} else {
													// echo 'Adding: '.$key.' on '.$key4;
													$AE = array_merge($AE, array($key3 . '_' . $key2 . '_' . $key => $key4));
												}
												$closeDataForOpen[] = [
													'date' => $key4,
													'accountid' => strval($key3),
													'userId' => $key2,
													'symbol' => $key,
													'close' => $un,
													// 'created_at' => new MongoDB\BSON\UTCDateTime(time()*1000),
													// 'updated_at' => new MongoDB\BSON\UTCDateTime(time()*1000),
												];
												break;
											}
										} else
											$un = 0;
									}
									// echo $tempo;
									// echo '@'.$key4;
									// exit;
								} else
									$un = 0;
								// if($tempo->where('accountid', $key3)->where('userId', $key2)->where('symbol', $key)->count()>0){
								// $un = $tempo->where('accountid', $key3)->where('userId', $key2)->where('symbol', $key)->first()->un;
								// }

							} else {
								// echo 'Setting Default Closedata';

								// echo 'Oreport Data not Found for '.$key;
								$un = 0;
								// $closeDataForOpen[] = [
								// 	'date' => $key4,
								// 	'accountid' => strval($key3),
								// 	'userId' => $key2,
								// 	'symbol' => $key,
								// 	'close' => 2000,
								// 	// 'created_at' => new MongoDB\BSON\UTCDateTime(time()*1000),
								// 	// 'updated_at' => new MongoDB\BSON\UTCDateTime(time()*1000),
								// ];
							}




						}
					}
				}
			}
		}
		// return $iskaPon;
		// return $closeDataForOpen;
		if (count($closeDataForOpen) > 0 && $cdInsertFlag) {
			Closedata::insert($closeDataForOpen);
			// dd($closeDataForOpen);exit;
		}
	}


	public function generateAdjustmentDataNew($sdate, $edate, $trId, $arInsertFlag = false)
	{
		$adDbData = Adjustment::where('transactionId', $trId)->get();

		$adDbData = $adDbData->groupBy(function ($item) {
			return Carbon::parse($item->date)->toDateString();
		});
		foreach ($adDbData as $key => $value) {
			$adDbData[$key] = $value->groupBy(function ($item) {
				return $item->accountid;
			});
		}
		$period = CarbonPeriod::create($sdate, $edate);
		$selectedRows = [];
		foreach ($period as $date) {
			if (isset($adDbData[$date->toDateString()])) {
				foreach ($adDbData[$date->toDateString()] as $accountKey => $dataSet) {
					$adm = $this->getAccountMaster($accountKey, $date->toDateString());
					if ($adm != null) {
						foreach ($dataSet as $dataRow) {
							// if(strpos($dataRow->category, 'Locate')!==false && strpos($dataRow->comment, 'Locate')!==false){

							// }else{
							$temp = $dataRow->toArray();
							unset($temp['_id']);

							$temp['accountid'] = strval($temp['accountid']);
							$temp['userId'] = $adm->id;
							$temp['created_at'] = new MongoDB\BSON\UTCDateTime(time() * 1000);
							$temp['updated_at'] = new MongoDB\BSON\UTCDateTime(time() * 1000);

							array_push($selectedRows, $temp);

							// }
						}

					}
				}
			}
		}
		// return $selectedRows;
		if (sizeof($selectedRows) > 0 && $arInsertFlag) {
			Areport::insert($selectedRows);
		} else
			return $selectedRows;
	}





	public function generateAdjustmentData($sdate, $edate, $trId, $arInsertFlag = false)
	{
		#-------------Generating Adjustment Report Data---------

		#------Grouping Data............

		// $opData = Oreport::where('date', '>=', $sdate)->where('date', '<=', $edate)->selectRaw('`date`,`accountid`,`userId`,sum(`qty`) as sum')->groupBy('userId','accountid','date')->get();

		$opData = Oreport::raw(function ($collection) use ($sdate, $edate) {
			return $collection->aggregate([
				[
					'$match' => [
						'date' => [
							'$gte' => $sdate,
							'$lte' => $edate,
						]
					]
				],
				[
					'$group' => [
						'_id' => [
							'userId' => '$userId',
							'accountid' => '$accountid',
							'date' => '$date',
						],
						'totalQty' => [
							'$sum' => '$qty'
						]
					]
				],
				[
					'$sort' => [
						'_id' => 1
					]
				],
				[
					'$set' => [
						'date' => '$_id.date',
						'accountid' => '$_id.accountid',
						'userId' => '$_id.userId',
						'sum' => '$totalQty',
						'_id' => '$$REMOVE',
					]
				]
			]);
		});
		// $opAccTotalData = oreport::where('date', '>=', $sdate)->where('date', '<=', $edate)->selectRaw('`date`, `accountid`, sum(`qty`) as sum')->groupBy('accountid','date')->get();

		$opAccTotalData = Oreport::raw(function ($collection) use ($sdate, $edate) {
			return $collection->aggregate([
				[
					'$match' => [
						'date' => [
							'$gte' => $sdate,
							'$lte' => $edate,
						]
					]
				],
				[
					'$group' => [
						'_id' => [
							'accountid' => '$accountid',
							'date' => '$date',
						],
						'totalQty' => [
							'$sum' => '$qty'
						]
					]
				],
				[
					'$sort' => [
						'_id' => 1
					]
				],
				[
					'$set' => [
						'date' => '$_id.date',
						'accountid' => '$_id.accountid',
						'sum' => '$totalQty',
						'_id' => '$$REMOVE',
					]
				]
			]);
		});

		// return $opAccTotalData;








		// select `date`,`accountid`,sum(`qty`) from oreports group by `accountid`,`date`
		// return $opAccTotalData;exit;


		$opData = $opData->groupBy(function ($item) {
			return Carbon::createFromFormat('Y-m-d', $item->date)->format('Y-m-d');
		});
		// return $pddData;
		foreach ($opData as $key => $value) {
			$temp = $value->groupBy(function ($item) {
				return $item->accountid;
			});
			foreach ($temp as $key2 => $value2) {
				$temp[$key2] = $value2->groupBy(function ($item) {
					return $item->userId;
				});
			}
			$opData[$key] = $temp;
		}


		$opAccTotalData = $opAccTotalData->groupBy(function ($item) {
			return $item->date;
		});
		foreach ($opAccTotalData as $key => $value) {
			$opAccTotalData[$key] = $value->groupBy(function ($item) {
				return $item->accountid;
			});
		}

		// return $opData;
		// return $opAccTotalData;

		#------Grouping Data Complete............

		#------Grouping Adjustment Data............

		$adDbData = Adjustment::where('transactionId', $trId)->get();

		$adDbData = $adDbData->groupBy(function ($item) {
			return Carbon::createFromFormat('Y-m-d', $item->date)->format('Y-m-d');
		});
		foreach ($adDbData as $key => $value) {
			$adDbData[$key] = $value->groupBy(function ($item) {
				return $item->accountid;
			});
		}
		// return $adDbData;
		// exit;
		#------Adjustment Grouping Complete............



		$adMaster = Adjustmentmaster::all();

		// return $opAccTotalData;
		foreach ($opData as $dateKey => $value1) {
			foreach ($value1 as $accKey => $value2) {
				$accTotalQty = 0;
				if (isset($opAccTotalData[$dateKey][$accKey])) {
					// return $opAccTotalData[$dateKey][$accKey][0];
					$accTotalQty = $opAccTotalData[$dateKey][$accKey][0]->sum;
				} //return $accTotalQty;
				$adm = null;
				if (isset($adDbData[$dateKey][$accKey])) {
					$adm = $adDbData[$dateKey][$accKey];
				}
				foreach ($value2 as $userKey => $value3) {
					$userTotalQty = $value3[0]->sum;
					// return $value3;
					if ($adm != null) {
						foreach ($adm as $key => $admV) {
							$debit = $credit = 0;
							// return $accTotalQty;
							// if($admV->debit!=0){
							// 	$debit = ($admV->debit/$accTotalQty)*$userTotalQty;
							// }else if($admV->credit!=0){
							// 	$credit = ($admV->credit/$accTotalQty)*$userTotalQty;
							// }

							if (strpos($admV->comment, 'Locate') !== false) {

							} else {
								// $accData = Share::where('accountid', $accKey)->first();
								$adData[] = [
									'accountid' => strval($accKey),
									'userId' => $this->getAccountMaster($accKey, $dateKey)->id,
									'date' => $dateKey,
									'category' => $admV->category,
									'comment' => $admV->comment,
									'debit' => $admV->debit,
									'credit' => $admV->credit,
									'verified' => 'Verified',
									'created_at' => new MongoDB\BSON\UTCDateTime(time() * 1000),
									'updated_at' => new MongoDB\BSON\UTCDateTime(time() * 1000),
								];
							}
							unset($adDbData[$dateKey][$accKey][$key]);
						}
					}

				}
			}
		}

		// return '$opclArr';
		// $benTen = Oreport::where('date', '>=', $sdate)->where('date', '<=', $edate);
		// Closedata::where('date', '>=', $sdate)->where('date', '<=', $edate)->union($benTen)->get(['date', 'accountid', 'userId', 'symbol']);
		$opclArr = Oreport::raw(function ($collection) use ($sdate, $edate) {
			return $collection->aggregate([
				[
					'$match' => [
						'date' => [
							'$gte' => '2022-01-03',
							'$lte' => '2022-01-03'
						]
					]
				],
				[
					'$project' => [
						'date' => '$date',
						'accountid' => '$accountid',
						'userId' => '$userId',
						'symbol' => '$symbol',
						'qty' => '$marketvalue',
						'mark' => 'oreports',
						'_id' => 0
					]
				],
				[
					'$sort' => [
						'date' => 1
					]
				],
				[
					'$unionWith' => [
						'coll' => 'closedatas',
						'pipeline' => [
							[
								'$match' => [
									'date' => [
										'$gte' => '2022-01-03',
										'$lte' => '2022-01-03'
									]
								]
							],
							[
								'$project' => [
									'date' => '$date',
									'accountid' => '$accountid',
									'userId' => '$userId',
									'symbol' => '$symbol',
									'qty' => '$close',
									'mark' => 'closedatas',
									'_id' => 0
								]
							],
							[
								'$sort' => [
									'date' => 1
								]
							]
						]
					]
				]
			]);
		});
		// $opclArr = DB::select(DB::raw("SELECT `date`, `accountid`, `userId`, `symbol`, `marketvalue` as `qty` FROM `oreports` WHERE `date`>='".$sdate."' AND `date`<='".$edate."' UNION SELECT `date`, `accountid`, `userId`, `symbol`, 'close' as `qty` FROM `closedatas` WHERE `date`>='".$sdate."' AND `date`<='".$edate."' ORDER BY date"));
		if (sizeof($adMaster) > 0 && sizeof($opclArr) > 0) {
			$ar = array();
			foreach ($opclArr as $oc) {
				// echo $oc->qty.' '; 
				if ($oc->qty != "close" && $oc->qty > 0) {

					// if($oc->qty>0){
					if (array_key_exists($oc->accountid . '_' . $oc->userId . '_' . $oc->symbol, $ar)) {
						$stDate = Carbon::parse(explode("_", $ar[$oc->accountid . '_' . $oc->userId . '_' . $oc->symbol])[0]);
						$qty = abs(explode("_", $ar[$oc->accountid . '_' . $oc->userId . '_' . $oc->symbol])[1]);
						$enDate = Carbon::parse($oc->date);
						if ($stDate->diffInDays($enDate) > 1) {
							if ($qty > 0) {
								// echo '<br>2nd Occurance'.$stDate->diffInDays($enDate);
								$stDate = $stDate->addDays(1);
								for ($dateD = $stDate; $dateD->lt($enDate); $dateD->addDay()) {
									// foreach ($adMaster->where('effectiveDate', '<=', $oc->date)->sortByDesc('effectiveDate') as $key => $value) {
									$diAsPerDate = \App\Http\Controllers\AdjustmentmasterController::getAdjMasData($oc->userId, $oc->date);
									$alreadyExistsFlg = false;
									foreach ($adData as $keyADD => $valueADD) {
										if ($valueADD['date'] == $dateD->toDateString() && $valueADD['accountid'] == $oc->accountid && $valueADD['userId'] == $oc->userId && $valueADD['category'] == $diAsPerDate->description) {
											$adData[$keyADD]['debit'] += $qty;
											$alreadyExistsFlg = true;
											break;
										}
									}

									if (!$alreadyExistsFlg) {
										#This is For Daily Interest Calculation
										$adData[] = [
											'accountid' => strval($oc->accountid),
											'userId' => $oc->userId,
											'date' => $dateD->toDateString(),
											'category' => $diAsPerDate->description,
											'comment' => $diAsPerDate->description . ' KUNUS',
											'debit' => ($qty),
											'credit' => 0,
											'verified' => 'Verified',
											'created_at' => new MongoDB\BSON\UTCDateTime(time() * 1000),
											'updated_at' => new MongoDB\BSON\UTCDateTime(time() * 1000),
										];
									}
									// echo "<br>2nd    Adding DI for ".$dateD->toDateString()." (".$oc->accountid.'_'.$oc->userId.'_'.$oc->symbol.'_'.$oc->date.')';
									// }
								}
							}

						}
						$ar[$oc->accountid . '_' . $oc->userId . '_' . $oc->symbol] = $oc->date . '_' . $oc->qty;
					} else {
						// array_push($ar, var)
						$ar = array_merge(array($oc->accountid . '_' . $oc->userId . '_' . $oc->symbol => $oc->date . '_' . $oc->qty), $ar);
						// foreach ($adMaster->where('effectiveDate', '<=', $oc->date)->sortByDesc('effectiveDate') as $key => $value) {
					}
					// $diAsPerDate = AdjustmentmasterController::getAdjMasData();
					$diAsPerDate = \App\Http\Controllers\AdjustmentmasterController::getAdjMasData($oc->userId, $oc->date);
					// echo '<br>Inserting Data 1st Occurance('.$oc->accountid.'_'.$oc->userId.'_'.$oc->symbol.'_'.$oc->date.')';
					$alreadyExistsFlg = false;
					if (isset($adData))
						foreach ($adData as $keyADD => $valueADD) {
							if ($valueADD['date'] == $oc->date && $valueADD['accountid'] == $oc->accountid && $valueADD['userId'] == $oc->userId && $valueADD['category'] == $diAsPerDate->description) {
								$adData[$keyADD]['debit'] += abs($oc->qty);
								$alreadyExistsFlg = true;
								break;
							}
						}

					if (!$alreadyExistsFlg) {
						#This is For Daily Interest Calculation
						$adData[] = [
							'accountid' => strval($oc->accountid),
							'userId' => $oc->userId,
							'date' => $oc->date,
							'category' => $diAsPerDate->description,
							'comment' => $diAsPerDate->description . ' KLS',
							'debit' => abs($oc->qty),
							'credit' => 0,
							'verified' => 'Verified',
							'created_at' => new MongoDB\BSON\UTCDateTime(time() * 1000),
							'updated_at' => new MongoDB\BSON\UTCDateTime(time() * 1000),
						];
					}
					// }
					// }

					// echo 'Shiva1<br>';
				} else {
					// echo 'Shiva2';
					// echo json_encode($ar);
					if (array_key_exists($oc->accountid . '_' . $oc->userId . '_' . $oc->symbol, $ar)) {
						$stDate = Carbon::parse(explode("_", $ar[$oc->accountid . '_' . $oc->userId . '_' . $oc->symbol])[0]);
						$qty = abs(explode("_", $ar[$oc->accountid . '_' . $oc->userId . '_' . $oc->symbol])[1]);
						$enDate = Carbon::parse($oc->date);
						if ($stDate->diffInDays($enDate) > 1) {
							if ($qty > 0) {
								// echo '<br>'.$stDate->diffInDays($enDate);
								$stDate = $stDate->addDays(1);
								for ($dateD = $stDate; $dateD->lt($enDate); $dateD->addDay()) {
									// foreach ($adMaster->where('effectiveDate', '<=', $oc->date)->sortByDesc('effectiveDate') as $key => $value) {
									$diAsPerDate = \App\Http\Controllers\AdjustmentmasterController::getAdjMasData($oc->userId, $oc->date);
									$alreadyExistsFlg = false;
									foreach ($adData as $keyADD => $valueADD) {
										if ($valueADD['date'] == $dateD->toDateString() && $valueADD['accountid'] == $oc->accountid && $valueADD['userId'] == $oc->userId && $valueADD['category'] == $diAsPerDate->description) {
											$adData[$keyADD]['debit'] += $qty;
											$alreadyExistsFlg = true;
											break;
										}
									}

									if (!$alreadyExistsFlg) {
										#This is For Daily Interest Calculation
										$adData[] = [
											'accountid' => strval($oc->accountid),
											'userId' => $oc->userId,
											'date' => $dateD->toDateString(),
											'category' => $diAsPerDate->description,
											'comment' => $diAsPerDate->description . " JKR",
											'debit' => ($qty),
											'credit' => 0,
											'verified' => 'Verified',
											'created_at' => new MongoDB\BSON\UTCDateTime(time() * 1000),
											'updated_at' => new MongoDB\BSON\UTCDateTime(time() * 1000),
										];
									}
									// echo "<br>    Adding DI for ".$dateD->toDateString()." (".$oc->accountid.'_'.$oc->userId.'_'.$oc->symbol.'_'.$oc->date.')';
									// }
								}
							}

						}
						unset($ar[$oc->accountid . '_' . $oc->userId . '_' . $oc->symbol]);
						// echo '<br>    Removing ('.$oc->accountid.'_'.$oc->userId.'_'.$oc->symbol.'_'.$oc->date.')';
					}
				}
			}

			// dd($adData);
			// foreach ($adMaster as $key => $value) {
			// 	$adData[]= [
			// 		'accountid' => $accKey,
			// 		'userId' => $userKey,
			// 		'date' => $dateKey,
			// 		'category' => $value->description,
			// 		'comment' => $value->description,
			// 		'debit' => (-1*($userTotalQty*$value->value)),
			// 		'credit' => 0,
			// 		'verified' => 'Verified',
			// 		'created_at' => new MongoDB\BSON\UTCDateTime(time()*1000),
			// 		'updated_at' => new MongoDB\BSON\UTCDateTime(time()*1000),
			// 	];
			// }
		}
		if (sizeof($adDbData) > 0) {
			foreach ($adDbData as $remDate => $value1) {
				foreach ($value1 as $remAcc => $value) {
					if (sizeof($value) > 0) {
						foreach ($value as $indde => $dataValue) {
							if (strpos($dataValue->comment, 'Locate') !== false) {

							} else {
								// $adminIdd = Share::where('accountid', $remAcc)->first()->admin_id;

								$adData[] = [
									'accountid' => strval($remAcc),
									'userId' => $this->getAccountMaster($remAcc, $remDate)->id,
									'date' => $remDate,
									'category' => $dataValue->category,
									'comment' => $dataValue->comment,
									'debit' => $dataValue->debit,
									'credit' => $dataValue->credit,
									'verified' => 'Verified',
									'created_at' => new MongoDB\BSON\UTCDateTime(time() * 1000),
									'updated_at' => new MongoDB\BSON\UTCDateTime(time() * 1000),
								];

								unset($adDbData[$remDate][$remAcc][$indde]);
								// echo 'dheiuy';
							}
						}
					}
				}
			}
		}
		// 			dd($adData);
		// return $adData;
		// exit;
		// return $adData;exit;
		if (isset($adData) && sizeof($adData) > 0 && $arInsertFlag) //==================================
			Areport::insert($adData); //=========================================
		// return 'Areport Inserted';
	}


	public function generateLocateData($sdate, $edate, $lrInsertFlag = false)
	{
		$locReport = [];
		// $userLoc = Userlocate::where('date', '>=', $sdate)->where('date', '<=', $edate)->get();
		$locateData = Locatedata::where('date', '>=', $sdate)->where('date', '<=', $edate)->get();
		// retrun $locateData;
		foreach ($locateData as $key => $value) {
			$userLoc = Userlocate::where('date', $value->date)->where('symbol', $value->symbol)->where('accountid', $value->accountid)->where('qty', $value->qty)->where('status', 'Set')->first();
			// echo Userlocate::where('date', $value->date)->where('symbol', $value->symbol)->where('accountid', $value->accountid)->count().'<br>';
			$locateUserId = $this->getAccountMaster($value->accountid, $value->date)->id;
			$locateVerifiedStat = 'Not Verified';

			if (isset($userLoc) && !empty($userLoc)) {
				$locateUserId = $userLoc->userId;
				$locateVerifiedStat = 'Verified';
			} else {
				// BIG_B
			}

			$locReport[] = [
				'accountid' => strval($value->accountid),
				'userId' => $locateUserId,
				'date' => $value->date,
				'category' => $value->category,
				'comment' => $value->comment,
				'qty' => $value->qty,
				'debit' => $value->qty * $value->bep,
				'added' => 0.00,
				'credit' => 0,
				'verified' => $locateVerifiedStat,
				'created_at' => new MongoDB\BSON\UTCDateTime(time() * 1000),
				'updated_at' => new MongoDB\BSON\UTCDateTime(time() * 1000),
			];

			if (isset($userLoc) && !empty($userLoc)) {
				$value->status = $userLoc->status = 'Verified';
				$userLoc->update();
				$value->update();
			} else {
				$value->status = 'Verified';
				$value->update();
			}

		}
		// return $locReport;
		// exit;
		if (isset($locReport) && sizeof($locReport) > 0 && $lrInsertFlag) { //==================================
			Locreport::insert($locReport); //=========================================
			// $this->createNotification('You have Un-published Report<br><a class="btn-small btn-primary" role="button" href="/dashboard/locates">Go to Locate Fees</a>', Session::get('user')->id);
		}
		return ['message' => 'success'];
		// return $locReport;
		// exit;
	}






























	public function handleReamining($sdate, $edate, $trId)
	{
		$reaminingData = DetailedBase::where('transactionId', $trId)->get();


		foreach ($reaminingData as $key => $value) {
			if ($value->{self::$userIdCol} == 0 || $value->{self::$userIdCol} == NULL) {
				// try{
				$value->{self::$userIdCol} = $this->getAccountMaster($value->accountid, $value->date)->id;

				// }catch(Exception ex){
				//     dd($value);
				// }
				$value->update();
			}
		}

		if (sizeof(self::$arr1) > 0) {
			foreach (self::$arr1 as $key => $value) {
				if ($value[self::$userIdCol] == null || $value[self::$userIdCol] == 0) {

					$adm = $this->getAccountMaster($value['accountid'], $value['date']);

					if ($adm == null) {
						return ['message' => "Master not yet assigned : " . $value['accountid'] . " on " . $value['date']];
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