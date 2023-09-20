<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Settings;
use App\Models\TradeAccount;
use App\Models\Dreport;
use App\Models\CashData;
use App\Models\Detailed;
use App\Models\PreviousDayOpen;
use App\Models\Acmap;
use App\Models\Preference;
use App\Models\DailyInterest;
use App\Models\Adjustmentlog;
use App\Models\User;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use App\Traits\AccountMappingTrait;


class SettingsController extends Controller
{

    use AccountMappingTrait;

    public function index()
    {
        if (sizeof(Settings::all()) == 0) {
            return response()->json(['message' => 'Settings Data not Set till Now'], 200);
        } else {

            $obj = Settings::first()->toArray();
            $obj['cronJobStatusDiff'] = Carbon::createFromTimeStamp($obj['cronJobStatus'])->diffForHumans();
            return response()->json(['message' => 'success', 'data' => $obj], 200);
        }
    }

    public function update(Request $request)
    {
        if (sizeof(Settings::all()) == 0) {
            $obj = new Settings();
            foreach ($request->data as $key => $value) {
                $obj[$key] = $value;
            }
            $obj->save();
        } else {
            $obj = Settings::first();

            foreach ($request->data as $key => $value) {
                if (!in_array($key, ['cronJobStatus', 'cronJobStatusDiff']))
                    $obj[$key] = $value;
            }
            $obj->update();
        }
        return response()->json(['message' => 'success'], 200);
    }

    public function autoReportStatus(Request $request)
    {
        $sdate = $request['sdate'] ?? Carbon::now()->toDateString();
        $edate = $request['edate'] ?? Carbon::now()->toDateString();
        $period = CarbonPeriod::create($sdate, $edate);
        $dates = $period->toArray();

        // $accounts = TradeAccount::distinct('accountid')->where('autoReport', true)->get()->toArray();
        $accounts = TradeAccount::distinct('accountid')->get()->toArray();
        $tmp = [];

        foreach ($accounts as $key => $value) {
            if (!in_array($value[0], $tmp))
                $tmp[] = $value[0];
        }
        $rowFormat = array_fill_keys($tmp, false);
        $rowFormat = array_merge(['Date' => ''], $rowFormat);

        // $dates = ['2022-10-10'];
        $rows = [];
        $header = [];
        foreach ($dates as $key => $date) {
            $temm = Dreport::where('date', $date->toDateString())->distinct('accountid')->get()->toArray();
            if (sizeof($temm) > 0) {
                $row = $rowFormat;
                $row['Date'] = $date->toDateString();
                foreach ($temm as $value) {
                    if (!in_array($value[0], $header))
                        $header[] = $value[0];
                    $row[$value[0]] = true;
                }
                $rows[] = $row;

            }
        }


        // return $dates;
        return [
            'header' => array_merge(['Date'], $header),
            'rows' => $rows
        ];
    }

    public function accountAuditReport(Request $request)
    {
        $res = [];
        $res['accountType'] = 'User';
        $res['account'] = $request['account'];
        $res['accId'] = '';
        $res['accountId'] = $request['accountText'];
        $res['accountAddedOn'] = '';
        $res['cash'] = '';
        $res['cashOnPdoDay'] = '';
        $res['firstTraded'] = '';
        $res['pdoExistsFrom'] = '';
        $res['pdoFetchedOn'] = '';
        $res['brokerName'] = '';
        $res['firstMapping'] = [
            'masterUser' => '',
            'subUsers' => '',
            'mappedOn' => '',
        ];
        $res['currentMapping'] = [
            'masterUser' => '',
            'subUsers' => '',
            'mappedOn' => '',
        ];
        $res['charges'] = [
            'totalPreferences' => '',
            'totalDailyInterests' => '',
            'adjustments' => '',
            'transfers' => '',
        ];
        if (strpos($res['account'], 'acc_') !== false) {
            $res['accountType'] = 'Account';
            $res['account'] = explode('_', $res['account'])[1];
            $tmp = TradeAccount::where('accountid', $res['account'])->with('broker')->first();
            if ($tmp) {
                $res['accId'] = $tmp['_id'];
                $res['accountAddedOn'] = Carbon::parse($tmp['created_at'])->toDateString();
                $res['brokerName'] = $tmp['broker']['name'];
                if (CashData::where('accountid', $res['account'])->count() > 0) {
                    $res['cash'] = CashData::where('accountid', $res['account'])->orderBy('date')->first();
                    $res['cash'] = $res['cash']['cash'] . ' - ' . $res['cash']['date'];
                }

                if (Dreport::where('accountid', $res['account'])->count() > 0) {
                    $res['firstTraded'] = Dreport::where('accountid', $res['account'])->orderBy('date')->first();
                    $res['firstTraded'] = $res['firstTraded']['date'];
                }

                if (PreviousDayOpen::where('accountid', $res['account'])->count() > 0) {
                    $res['pdoExistsFrom'] = PreviousDayOpen::where('accountid', $res['account'])->orderBy('date')->first();
                    $res['pdoFetchedOn'] = Carbon::parse($res['pdoExistsFrom']['created_at'])->toDateString();
                    $res['pdoFetchedFor'] = Carbon::parse($res['pdoExistsFrom']['generatedOn'])->toDateString();
                    $res['pdoExistsFrom'] = $res['pdoExistsFrom']['date'];
                    $res['cashOnPdoDay'] = CashData::where('date', $res['pdoFetchedFor'])->where('accountid', $res['account'])->first()->cash.' - '.$res['pdoFetchedFor'];
                }

                if (Acmap::where('mappedTo', $res['account'])->where('role', 'master')->count() > 0) {
                    $tmp = Acmap::where('mappedTo', $res['account'])->where('role', 'master')->with('effectedUser')->orderBy('startdate')->first();
                    $res['firstMapping']['masterUser'] = $tmp['effectedUser']['memberId'];
                    $res['firstMapping']['mappedOn'] = $tmp['startdate'];
                    if (Acmap::where('mappedTo', $res['account'])->where('role', 'sub')->where('startdate', $tmp['startdate'])->count() > 0) {
                        $tmp = Acmap::where('mappedTo', $res['account'])->where('role', 'sub')->where('startdate', $tmp['startdate'])->with('effectedUser')->get();
                        $subs = '';
                        $subsMappedOn = '';
                        foreach ($tmp as $t) {
                            if ($subs == '') {
                                $subs = $t['effectedUser']['memberId'];
                                $subsMappedOn = $t['startdate'];
                            } else {
                                $subs = $subs . ', ' . $t['effectedUser']['memberId'];
                                $subsMappedOn = $subsMappedOn . ', ' . $t['startdate'];
                            }
                        }
                        $res['firstMapping']['subUsers'] = $subs;
                        $res['firstMapping']['subMappedOn'] = $subsMappedOn;
                    }
                }
                if (Acmap::where('mappedTo', $res['account'])->where('role', 'master')->count() > 0) {
                    $tmp = Acmap::where('mappedTo', $res['account'])->where('role', 'master')->with('effectedUser')->orderBy('startdate', 'desc')->first();
                    $res['currentMapping']['masterUser'] = $tmp['effectedUser']['memberId'];
                    $res['currentMapping']['mappedOn'] = $tmp['startdate'];

                    $activeMapping = $this->getMappingByDate();
                    $activeMappingIds = [];
                    foreach ($activeMapping as $key => $value) {
                        $activeMappingIds[] = $value->_id;
                    }

                    $tmp = Acmap::whereIn('_id', $activeMappingIds)->where('mappedTo', $res['account'])->where('role', 'sub')->with('effectedUser')->get();

                    $subs = '';
                    $subsMappedOn = '';
                    foreach ($tmp as $t) {
                        if ($subs == '') {
                            $subs = $t['effectedUser']['memberId'];
                        } else {
                            $subs = $subs . ', ' . $t['effectedUser']['memberId'];
                        }

                        if ($subsMappedOn == '') {
                            $subsMappedOn = $t['startdate'];
                        } else {
                            $subsMappedOn = $subsMappedOn . ', ' . $t['startdate'];
                        }
                    }
                    $res['currentMapping']['subUsers'] = $subs;
                    $res['currentMapping']['subMappedOn'] = $subsMappedOn;
                }

                $res['charges']['totalPreferences'] = Preference::where('trade_account_id', $res['accId'])->count();
                $res['charges']['totalDailyInterests'] = DailyInterest::where('trade_account_id', $res['accId'])->count();
                $res['charges']['adjustments'] = Adjustmentlog::where('effectiveFor', $res['account'])->where('category', '!=', 'Transfer')->count();
                $res['charges']['transfers'] = Adjustmentlog::where('effectiveFor', $res['account'])->where('category', 'Transfer')->count();
            }
        } else {
            $res['accountType'] = 'User';
            // $res['account'] = $res['account'];
            $tmp = User::find($res['account']);
            if ($tmp) {
                $res['accId'] = $tmp['_id'];
                $res['accountAddedOn'] = Carbon::parse($tmp['created_at'])->toDateString();
                $res['brokerName'] = 'Not Applicable';
                // if (CashData::where('accountid', $res['account'])->count() > 0) {
                //     $res['cash'] = CashData::where('accountid', $res['account'])->orderBy('date')->first();
                //     $res['cash'] = $res['cash']['cash'] . ' - ' . $res['cash']['date'];
                // }
                $res['cash'] = 'Not Applicable';

                if (Dreport::where('userId', $res['account'])->count() > 0) {
                    $res['firstTraded'] = Dreport::where('userId', $res['account'])->orderBy('date')->first();
                    $res['firstTraded'] = $res['firstTraded']['date'];
                }

                if (PreviousDayOpen::where('userId', $res['account'])->count() > 0) {
                    $res['pdoExistsFrom'] = PreviousDayOpen::where('userId', $res['account'])->orderBy('date')->first();
                    $res['pdoFetchedOn'] = Carbon::parse($res['pdoExistsFrom']['created_at'])->toDateString();
                    $res['pdoFetchedFor'] = Carbon::parse($res['pdoExistsFrom']['generatedOn'])->toDateString();
                    $res['pdoExistsFrom'] = $res['pdoExistsFrom']['date'];
                }

                if (Acmap::where('user', $res['account'])->count() > 0) {
                    $tmp = Acmap::where('user', $res['account'])->with('effectedUser')->orderBy('date')->first();
                    $res['firstMapping']['mappedTo'] = $tmp['mappedTo'];
                    $res['firstMapping']['mappingType'] = $tmp['role'];
                    $res['firstMapping']['mappedOn'] = $tmp['startdate'];

                    $activeMapping = $this->getMappingByDate();
                    $activeMappingIds = [];
                    foreach ($activeMapping as $key => $value) {
                        $activeMappingIds[] = $value->_id;
                    }

                    $tmp = Acmap::whereIn('_id', $activeMappingIds)->where('user', $res['account'])->with('effectedUser')->first();

                    $res['currentMapping']['mappedTo'] = $tmp['mappedTo'];
                    $res['currentMapping']['mappingType'] = $tmp['role'];
                    $res['currentMapping']['mappedOn'] = $tmp['startdate'];
                }

                $res['charges']['totalPreferences'] = Preference::where('user_id', $res['accId'])->count();
                $res['charges']['totalDailyInterests'] = DailyInterest::where('user_id', $res['accId'])->count();
                $res['charges']['adjustments'] = Adjustmentlog::where('effectiveFor', $res['account'])->where('category', '!=', 'Transfer')->count();
                $res['charges']['transfers'] = Adjustmentlog::where('effectiveFor', $res['account'])->where('category', 'Transfer')->count();
            }
        }
        return [$res];
    }
    public function logoUpload(Request $request) {
        // return 'hel';
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);
    
        $imageName = 'logo.'.$request->file->extension();  
     
        $request->file->move(public_path(), $imageName);
  
        /* Store $imageName name in DATABASE from HERE */
        return ['success'];
    //     return back()
    //         ->with('success','You have successfully upload image.')
    //         ->with('image',$imageName); 
    }
    public function faviconUpload(Request $request) {
        // return 'hel';
        // $request->validate([
        //     'file' => 'required|image|mimes:ico|max:2048',
        // ]);
        if($request->file->extension()=='ico'){
            $imageName = 'favicon.'.$request->file->extension();  
         
            $request->file->move(public_path(), $imageName);
      
            /* Store $imageName name in DATABASE from HERE */
            return ['success'];
        //     return back()
        //         ->with('success','You have successfully upload image.')
        //         ->with('image',$imageName); 

        }
    
    }
    public function dashboardLogoUpload(Request $request) {
        // return 'hel';
        $request->validate([
            'file' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);
    
        $imageName = 'logo1.'.$request->file->extension();  
     
        $request->file->move(public_path(), $imageName);
  
        /* Store $imageName name in DATABASE from HERE */
        return ['success'];
    //     return back()
    //         ->with('success','You have successfully upload image.')
    //         ->with('image',$imageName); 
    
    }
}