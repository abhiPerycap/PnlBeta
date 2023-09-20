<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\TradeAccount;

use App\Models\DetailedBase;
use App\Models\Detailed;
use App\Models\Open;
use App\Models\Acmap;
use App\Models\Adjustment;
use App\Models\PreviousDayOpen;
use App\Models\Areport;
use App\Models\Dreport;
use App\Models\Oreport;
use App\Models\Closedata;
use App\Models\Locatedata;
use App\Models\Locreport;
use App\Models\Userlocate;
use App\Models\CashData;


use Carbon\Carbon;
use App\Imports\PreviousDayOpenImport;
use Excel;
use Illuminate\Support\Str;

use App\Traits\DataAdapterTrait;
use App\Traits\DataMapperTrait;
use App\Traits\ReportGeneratorTrait;
use App\Utils\DataMapperClass;
use App\Utils\ReportGeneratorClass;

class ReportManagerController extends Controller
{
	use DataAdapterTrait;
	use DataMapperTrait;
	use ReportGeneratorTrait;

	public function index()
	{

	}

	public function importServerData(Request $request)
	{
		// return $request->all();
		$trId = Str::uuid()->toString();
		if ($this->getAccountMaster(strval($request->data['account']['text']), $request->data['fromDate']) != null) {
			$serverData = $this->fetchFromServerByAccount_Date(
				$request->data['account']['id'],
				[
					'fromDate' => $request->data['fromDate'],
					'toDate' => $request->data['toDate']
				],
				$trId
			);
			// return $serverData;
			if ($serverData['message'] != 'success') {
				return response()->json(['message' => $serverData['message']], 200);

			} else {
				$detailed = $serverData['data'][0];
				$open = $serverData['data'][1];
				$adjustment = $serverData['data'][2];

				// if (sizeof($detailed)>0) {
				$accountid = strval($request->data['account']['text']);
				$sdate = Carbon::parse($request->data['fromDate'])->toDateString();
				$edate = Carbon::parse($request->data['toDate'])->toDateString();


				DetailedBase::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
				Detailed::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
				Open::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
				Adjustment::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
				PreviousDayOpen::where('generatedOn', '>=', $sdate)->where('generatedOn', '<=', $edate)->where('accountid', $accountid)->delete();
				Areport::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
				Dreport::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
				Oreport::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
				Closedata::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', strval($accountid))->delete();
				CashData::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', strval($accountid))->delete();

				Closedata::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', ($accountid))->delete();

				CashData::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('mappedAccount', $accountid)->delete();
				CashData::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', ($accountid))->delete();
				$this->deleteAssociatedMasterCashData($sdate, $edate, [$accountid]);
				Locatedata::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();
				Locreport::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->delete();

				$ulData = Userlocate::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->get();
				foreach ($ulData as $key => $value) {
					Userlocate::where('id', $value->id)->update(['status' => $value->prevstatus]);
				}

				// DB::statement("update userlocates SET `status` = `prevstatus` WHERE `date` >= $sdate AND `date` <= $edate AND `accountid` = '$accountid'");

				foreach (array_chunk($detailed, 1000) as $t) {
					DetailedBase::insert($t);
				}

				foreach (array_chunk($open, 1000) as $t) {
					Open::insert($t);
				}

				foreach (array_chunk($adjustment, 1000) as $t) {
					Adjustment::insert($t);
				}

				$dataMapper = new DataMapperClass();
				$feedBack = $dataMapper->mapDataToUsers($sdate, $edate, $trId);
				$generator = new ReportGeneratorClass();
				$finalResponse = null;
				switch ($feedBack['message']) {
					case 'success':

						$finalResponse = $generator->processReport($sdate, $edate, $trId);
						break;

					case 'User Data Mismatch found':

						$finalResponse = $generator->processReport($sdate, $edate, $trId);
						break;

					case 'detailed data is empty':

						$finalResponse = $generator->processReport($sdate, $edate, $trId);
						break;

					default:
						return response()->json(['message' => 'unknown error'], 200);
						break;
				}

				return $finalResponse;
				return response()->json(['message' => 'success'], 200);

				// }else{
				// return response()->json(['message'=>'Something Went Wrong[Detailed Data is Empty]'], 200);
				// }
			}
		} else {
			return response()->json(['message' => 'Master is Not Assigned for the Selected Account before/on the selected Date'], 200);
		}

	}


	public function resetData(Request $request, $type)
	{

		$sdate = Carbon::parse($request['data']['fromDate'])->toDateString();
		$edate = Carbon::parse($request['data']['toDate'])->toDateString();

		$accounts = array_column($request['data']['accounts'], 'text');
		if ($type == 'recompile') {
			$res = [];
			foreach ($accounts as $account) {

				$trId = Str::uuid()->toString();

				DetailedBase::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', strval($account))->update(['trId' => $trId]);
				Open::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', strval($account))->update(['trId' => $trId]);
				Adjustment::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', strval($account))->update(['trId' => $trId]);


				PreviousDayOpen::where('generatedOn', '>=', $sdate)->where('generatedOn', '<=', $edate)->where('accountid', strval($account))->delete();
				Detailed::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', strval($account))->delete();
				Areport::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', strval($account))->delete();
				Dreport::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', strval($account))->delete();
				Oreport::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', strval($account))->delete();
				Closedata::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', strval($account))->delete();
				CashData::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', strval($account))->delete();

				Closedata::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', ($account))->delete();

				CashData::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('mappedAccount', $account)->delete();
				CashData::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', ($account))->delete();
				$this->deleteAssociatedMasterCashData($sdate, $edate, [strval($account)]);
				Locatedata::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', strval($account))->delete();
				Locreport::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', strval($account))->delete();

				$ulData = Userlocate::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', strval($account))->get();
				foreach ($ulData as $key => $value) {
					Userlocate::where('id', $value['_id'])->update(['status' => $value->prevstatus]);
				}

				$dataMapper = new DataMapperClass();
				$feedBack = $dataMapper->mapDataToUsers($sdate, $edate, $trId);
				$generator = new ReportGeneratorClass();
				$finalResponse = null;
				switch ($feedBack['message']) {
					case 'success':

						$finalResponse = $generator->processReport($sdate, $edate, $trId);
						break;

					case 'User Data Mismatch found':

						$finalResponse = $generator->processReport($sdate, $edate, $trId);
						break;

					default:
						return response()->json(['message' => 'unknown error'], 200);
						break;
				}

				$res[strval($account)] = $finalResponse;
			}
			return response()->json(['message' => 'success', 'data' => $res], 200);
		} else {
			// $accounts = array_column($request['data']['accounts'], 'text');
			DetailedBase::where('date', '>=', $sdate)->where('date', '<=', $edate)->whereIn('accountid', $accounts)->delete();
			Detailed::where('date', '>=', $sdate)->where('date', '<=', $edate)->whereIn('accountid', $accounts)->delete();
			Open::where('date', '>=', $sdate)->where('date', '<=', $edate)->whereIn('accountid', $accounts)->delete();
			Adjustment::where('date', '>=', $sdate)->where('date', '<=', $edate)->whereIn('accountid', $accounts)->delete();
			PreviousDayOpen::where('generatedOn', '>=', $sdate)->where('generatedOn', '<=', $edate)->whereIn('accountid', $accounts)->delete();
			Areport::where('date', '>=', $sdate)->where('date', '<=', $edate)->whereIn('accountid', $accounts)->delete();
			Dreport::where('date', '>=', $sdate)->where('date', '<=', $edate)->whereIn('accountid', $accounts)->delete();
			Oreport::where('date', '>=', $sdate)->where('date', '<=', $edate)->whereIn('accountid', $accounts)->delete();
			Closedata::where('date', '>=', $sdate)->where('date', '<=', $edate)->whereIn('accountid', $accounts)->delete();
			CashData::where('date', '>=', $sdate)->where('date', '<=', $edate)->whereIn('mappedAccount', $accounts)->delete();
			CashData::where('date', '>=', $sdate)->where('date', '<=', $edate)->whereIn('accountid', $accounts)->delete();
			$this->deleteAssociatedMasterCashData($sdate, $edate, $accounts);
			Locatedata::where('date', '>=', $sdate)->where('date', '<=', $edate)->whereIn('accountid', $accounts)->delete();
			Locreport::where('date', '>=', $sdate)->where('date', '<=', $edate)->whereIn('accountid', $accounts)->delete();

			$ulData = Userlocate::where('date', '>=', $sdate)->where('date', '<=', $edate)->whereIn('accountid', $accounts)->get();
			foreach ($ulData as $key => $value) {
				Userlocate::where('id', $value['_id'])->update(['status' => $value->prevstatus]);
			}
			return response()->json(['message' => 'success'], 200);
		}
	}

	public function deleteAssociatedMasterCashData($sdate, $edate, $accounts)
	{
		$deleteArray = [];
		foreach ($accounts as $account) {
			$deleteArray[$account] = [];
			$startDaymaster = $this->getAccountMaster($account, $sdate);
			if ($startDaymaster != null) {
				$deleteArray[$account][$startDaymaster['_id']] = [
					'sdate' => $sdate,
					'edate' => null
				];

			}
			$acmaps = Acmap::where('startdate', '>', $sdate)->where('startdate', '<=', $edate)->where('mappedTo', $account)->where('role', 'master')->get();
			if ($acmaps->count() > 0) {
				foreach ($acmaps as $acmap) {
					if (sizeof($deleteArray[$account]) > 0 && $deleteArray[$account][array_key_last($deleteArray[$account])]['edate'] == null) {
						// $deleteArray[$account][array_key_last($deleteArray[$account])]['edate'] = $acmap->startdate;
						CashData::where('date', '>=', $deleteArray[$account][array_key_last($deleteArray[$account])]['sdate'])->
							where('date', '<=', $acmap->startdate)->
							where('userId', array_key_last($deleteArray[$account]))->delete();
						unset($deleteArray[$account][array_key_last($deleteArray[$account])]);
						$deleteArray[$account][$acmap['user']] = [
							'sdate' => $acmap->startdate,
							'edate' => null
						];
					} else {
						$deleteArray[$account][$acmap['user']] = [
							'sdate' => $acmap->startdate,
							'edate' => null
						];
					}
				}

			} else {
				if ($startDaymaster != null) {
					// $deleteArray[$account][$startDaymaster['_id']]['edate'] = $edate;
					CashData::where('date', '>=', $deleteArray[$account][$startDaymaster['_id']]['sdate'])->
						where('date', '<=', $edate)->
						where('userId', $startDaymaster['_id'])->delete();
					unset($deleteArray[$account][$startDaymaster['_id']]);
				}
			}
			if ($startDaymaster != null && sizeof($deleteArray[$account]) > 0 && $deleteArray[$account][array_key_last($deleteArray[$account])]['edate'] == null) {
				$deleteArray[$account][array_key_last($deleteArray[$account])]['edate'] = $edate;
				CashData::where('date', '>=', $deleteArray[$account][array_key_last($deleteArray[$account])]['sdate'])->
					where('date', '<=', $edate)->
					where('userId', $startDaymaster['_id'])->delete();
				unset($deleteArray[$account][array_key_last($deleteArray[$account])]);
			}
		}

		// $cashDatas = CashData::all();
		$cashDatas = CashData::where('date', '>', $sdate)->where('date', '<=', $edate)->where('userId', '!=', null)->where('mappedAccount', null)->get();
		foreach ($cashDatas as $row) {
			if (isset($row['user_id']) || isset($row['userId'])) {
				// $masterAccount = User::find($row['user_id'])->getAccountId($row['date']);
				// if($masterAccount!=null){
				// 	$row['mappedAccount'] = $masterAccount;
				// 	$row->update();
				// }
				if (!isset($row['mappedAccount'])) {
					$row->delete();
				}
			}
		}
	}

}