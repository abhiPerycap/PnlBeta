<?php

namespace App\Http\Controllers;
use DB;
use DateTime;
use DateInterval;
use DatePeriod;
use App\Models\User;
use App\Models\UserData;
use App\Models\DemoUserData;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use App\Events\UserTradeDataStatus;

class ManualTradeController extends Controller
{
    function manualTradeData(Request $request){
        $sdate      = date('Y-m-d', strtotime($request->sdate));
        $edate      = date('Y-m-d', strtotime($request->edate));
        // return [$sdate, $edate];
        $user_id    = $request->user_id;
        $accountid  = $request->accountid;
        $reportType = $request->reportType;
        
        if($reportType ==  'dd' || $reportType == 'ddg'){
            if($reportType ==  'dd')
                $datas = UserData::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('user_id', $user_id)->get();
            if($reportType ==  'ddg')
                $datas = UserData::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('accountid', $accountid)->get();
            // return '$datas';
            foreach ($datas as $key => $value) {
                $data[] = array(    
                    '_id'        => $value->_id,
                    'req_by' => User::find($value->user_id)->memberId??$value->user_id,
                    'user_id' => User::find($value->user_id)->memberId??$value->user_id,
                    'qty' => User::find($value->user_id)->getAccountMaster($value->date)->memberId??'',
                    'admin' => User::find($value->user_id)->getAccountMaster($value->date)->memberId??'',
                    'date'       => $value->date,
                    'time'       => $value->created_at->format('H:i:s')??'',
                    'accountid'  => $value->accountid,
                    'status'     => $value->status,
                    'verifiedBy' => $value->verifiedBy??'Not Verified',
                    'symbol'     => $value->symbol,
                    'grossPnl'   => $value->grossPnl,
                    'quantity'   => $value->quantity,
                    'executionId' => $value->executionId,
                    'real_user_id' => $value->user_id,
  
                );
            }
        }

        if($reportType == 'sbd' || $reportType == 'sbdg'){

            if($reportType == 'sbd')
                $match_user_id = ['user_id' => $request->user_id]; 
            if($reportType == 'sbdg')
                $match_user_id = ['accountid' => $request->accountid]; 

            $match_date    = [
                'date' => [ 
                    '$gte' => $sdate, '$lte' => $edate
                ]
            ];

            $match = array_merge($match_user_id,$match_date);

            $data =UserData::raw(function($collection) use($sdate, $edate, $match) {
                return $collection->aggregate([
                    [ 
                        '$match' => $match
                    ], 
                    [
                        '$group' => [
                            '_id' => [
                                'date' => '$date',
                                'accountid' => '$accountid',
                                'user_id' => '$user_id',
                            ],
                            'quantity' => ['$sum' => ['$toInt'=>'$quantity']],
                            'grossPnl' => ['$sum' => ['$toDouble'=>'$grossPnl']],                            
                        ]
                    ],
                    [
                        '$set' => [
                            'date' => '$_id.date',
                            'accountid' => '$_id.accountid',
                            'user_id' => '$_id.user_id',
                            'admin' => 'admin',
                            'quantity' => '$quantity',
                            'grossPnl' => '$grossPnl'
                        ]
                    ]
                ]);
            });
        }

        if($reportType == 'tbs' || $reportType == 'tbsg'){
            if($reportType == 'tbs')
                $match_user_id = ['user_id' => $request->user_id]; 
            if($reportType == 'tbsg')
                $match_user_id = ['accountid' => $request->accountid]; 

            $match_date    = [
                'date' => [ 
                    '$gte' => $sdate, '$lte' => $edate
                ]
            ];

            $match = array_merge($match_user_id,$match_date);

            $data  = UserData::raw(function($collection) use($sdate, $edate, $match) {
                return $collection->aggregate([
                    [ 
                        '$match' => $match
                    ], 
                    [
                        '$group' => [
                            '_id' => [
                                'symbol' => '$symbol',
                                // 'date'   => '$date',
                                'accountid'   => '$accountid',
                            ],
                            'quantity' => ['$sum' => ['$toInt'=>'$quantity']],
                            'grossPnl' => ['$sum' => ['$toDouble'=>'$grossPnl']],                            
                        ]
                    ],
                    [
                        '$set' => [
                            'accountid'   => '$_id.accountid',
                            'admin'   => 'admin',
                            'symbol'   => '$_id.symbol',
                            // 'date'     => '$_id.date',
                            'quantity' => '$quantity',
                            'grossPnl' => '$grossPnl',
                        ]
                    ]
                ]);
            });
        }

        if($reportType == 'sbu' || $reportType == 'sbuu'){
            if($reportType == 'sbu')
                $match_user_id = ['user_id' => $request->user_id]; 
            if($reportType == 'sbuu')
                $match_user_id = ['accountid' => $request->accountid]; 

            $match_date    = [
                'date' => [ 
                    '$gte' => $sdate, '$lte' => $edate
                ]
            ];

            $match = array_merge($match_user_id,$match_date);

            $data  = UserData::raw(function($collection) use($sdate, $edate, $match) {
                return $collection->aggregate([
                        [ 
                            '$match' => $match
                        ], 
                        [
                            '$group' => [
                                '_id' => [
                                    'symbol'    => '$symbol',
                                    'user_id'   => '$user_id',
                                    'accountid' => '$accountid',
                                    // 'date'      => '$date'
                                
                                    
                                ],
                                'quantity' => [
                                    '$sum' => [
                                        '$toInt' => '$quantity'
                                    ]
                                ],
                                'grossPnl' => [
                                    '$sum' => [
                                        '$toDouble' => '$grossPnl'
                                    ]
                                ]
                            ]
                        ],
                        [
                            '$set' => [
                                'userId' => [
                                    '$toObjectId' => '$_id.user_id'
                                ]
                            ]
                        ],
                        [
                            '$lookup' => [
                                'from' => 'users',
                                'localField' => 'userId',
                                'foreignField' => '_id',
                                'as' => 'user'
                            ]
                        ],
                        [
                            '$unwind' => [
                                'path' => '$user',
                                'preserveNullAndEmptyArrays' => false
                            ]
                        ],
                        [
                            '$project' => [
                                'user_id'   => '$user.memberId',
                                // 'date'      => '$_id.date',
                                'accountid' => '$_id.accountid',
                                'admin' => 'admin',
                                'symbol'    => '$_id.symbol',
                                'quantity'  => '$quantity',
                                'grossPnl'  => '$grossPnl',
                                'status'    => '$_id.status',
                                '_id'       => 0
                            ]
                        ]
                    ]);
            });
        }

        if(in_array($reportType, ['sbd', 'sbdg'])){
            foreach ($data as $key => $value) {
                $data[$key]['admin'] = User::find($data[$key]['user_id'])->getAccountMaster($data[$key]['date'])->memberId??'';
                $data[$key]['user_id'] = User::find($value->user_id)->memberId??$value->user_id;
            }
        }

        if(in_array($reportType, ['sbu', 'sbuu'])){
            foreach ($data as $key => $value) {
                $data[$key]['admin'] = User::where('memberId', $data[$key]['user_id'])->first()->getAccountMaster($data[$key]['date'])->memberId??'';
                // $data[$key]['user_id'] = User::find($value->user_id)->memberId??$value->user_id;
            }
        }

        if(!isset($data))
            $data = "";

        return response()->json($data, 200);
    }


    function verify_trade_data(Request $request){
        $ids       = $request->ids;
        $event     = $request->event == 'verify'?'verified':'rejected';
        $accountId = $request->accountId; 
        $verifiedBy = $request->verifier; 

        $statusData = array(
            'ids'       => $ids,
            'event'     => $event,
            'accountid' => $accountId
        );


        // if($event == 'verify'){
            UserData::whereIn('_id', $ids)->update([
                'status' => $event,
                'verifiedBy' => $verifiedBy
            ]);
        // }else{
            // UserData::whereIn('_id', $ids)->update([
                // 'status' => 'rejected',
                // 'verifiedBy' => $verifiedBy
            // ]);


            // UserData::where('date', '2022-01-18')->update(['date'=>'2022-01-17']);
        // }
        event(new UserTradeDataStatus($statusData));
        return response()->json($ids);
    }
}
