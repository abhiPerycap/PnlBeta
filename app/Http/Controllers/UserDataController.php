<?php

namespace App\Http\Controllers;
use DB;
use DateTime;
use DateInterval;
use DatePeriod;
use App\Models\User;
use App\Models\UserData;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use App\Events\UserTradeDataAdded;
use App\Events\UserTradeDataUpdated;
use App\Events\UserTradeDataDeleted;

class UserDataController extends Controller
{
    public function index(Request $request)
    {
        if ($this->checkPermission('inputTradeData', 'authorised')) {
            $fromDate = Carbon::today()->toDateString();
            $toDate = Carbon::today()->toDateString();
            if ($request->has('from') && $request->has('to')) {
                $fromDate = $request->from;
                $toDate = $request->to;
            }

            $data = UserData::with('user')->where('date', '>=', $fromDate)->where('date', '<=', $toDate)->get();
            foreach ($data as $key => $value) {
                $data[$key]['admin'] = $value->user->getAccountMaster();
            }
            return $data;

        } else {
            return response()
                ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
        }

    }

    public function store(Request $request)
    {
        if ($this->checkPermission('inputTradeData', 'canAdd')) {
            // return $request->all();
            // $obj = new UserData($request->all());
            $tempData = $request->data;
            foreach ($tempData as $key => $value) {
                if($key=='date')
                    $tempData[$key] = Carbon::parse($value)->toDateString();
            }
            $obj = auth()->user()->userDatas()->save(
                new UserData($tempData)
            );
            $obj->user;
            $obj['admin'] = $obj->user->getAccountMaster()['memberId'];
            $formattedData = [
                '_id' => $obj['_id'],
                'executionId' => $obj['executionId'],
                'accountid' => $obj['accountid'],
                'admin' => $obj['admin'],
                'date' => $obj['date'],
                'grossPnl' => $obj['grossPnl'],
                'quantity' => $obj['quantity'],
                'req_by' => $obj['user']['memberId'],
                'user_id' => $obj['user']['memberId'],
                'status' => $obj['status'],
                'symbol' => $obj['symbol'],
                'real_user_id' => $obj['user']['_id'],
                'time' => $obj['created_at']->format('H:i:s')??'',
                'verifiedBy' => $obj['verifiedBy']?$obj['verifiedBy']:'Not Verified',
            ];
            event(new UserTradeDataAdded($formattedData));
            // return $formattedData;
                return response()->json(['message' => 'success', 'data' => $formattedData], 200);
            // if ($obj->save() == 1) {
            //     return response()->json(['message' => 'success', 'data' => $formattedData], 200);
            // } else {
            //     return response()
            //         ->json(['message' => 'Couldn\'t Save the Data'], 500);
            // }
        } else {
            return response()
                ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
        }
    }

    public function update(Request $request, $userData)
    {

        if ($this->checkPermission('inputTradeData', 'canModify')) {

            try {
                $obj = UserData::find($userData);
            } catch (ModelNotFoundException $e) {
                return response()->json(['message' => 'Data not found'], 404);
            }
            if ($obj->update($request->all()) == 1) {  
                event(new UserTradeDataUpdated($obj));
                return response()->json(['message' => 'success'], 200);
            } else {
                return response()
                    ->json(['message' => 'Couldn\'t Save the Data'], 500);
            }
        } else {
            return response()
                ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
        }
    }

    public function destroy($userData)
    {
        if ($this->checkPermission('inputTradeData', 'canDelete')) {
            try {
                $userData = UserData::findOrFail($userData);
            } catch (ModelNotFoundException $e) {
                return response()->json(['message' => 'Trade Data not found'], 404);
            }
            $userData->delete();
            return response()->json(['message' => 'success'], 200);
        } else {
            return response()
                ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
        }
    }

    public function destroyMultiple(Request $request)
    {
      if ($this->checkPermission('inputTradeData', 'canDelete')) {
          try {
              $userDatas = UserData::whereIn('_id', $request->ids)->get();
          } catch (ModelNotFoundException $e) {
              return response()->json(['message' => 'User Data not found'], 404);
          }
          $ids = [];
          foreach ($userDatas as $userData) {
              $ids[] = $userData->_id;
          }

          UserData::whereIn('_id', $ids)->delete();
          return response()->json(['message' => 'success'], 200);
      } else {
          return response()
              ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
      }
  }



    public function getUserOfDate(Request $request){
        $sdate      = date('Y-m-d', strtotime($request->sdate));
        $edate      = date('Y-m-d', strtotime($request->edate));

        if($request->status && $request->status != ""){
            $accountids =  UserData::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('status', $request->status)->select('accountid')->distinct('accountid')->get();
            $userids    =  UserData::where('date', '>=', $sdate)->where('date', '<=', $edate)->where('status', $request->status)->select('user_id')->pluck('user_id');
        }else{
            $accountids =  UserData::where('date', '>=', $sdate)->where('date', '<=', $edate)->select('accountid')->distinct('accountid')->get();
            $userids    =  UserData::where('date', '>=', $sdate)->where('date', '<=', $edate)->select('user_id')->pluck('user_id');
        }


        // $accountids = array_unique($accountids, SORT_STRING);


        $userids =    User::whereIn('_id', $userids)->select('name', '_id')->get();
        return response()->json(['accountids' => $accountids, 'userids'=> $userids, 'status' => $request->status], 200);
    }

    public function getStatusOfTheDay(Request $request){
        
        $sdate      = date('Y-m-d', strtotime($request->sdate));
        $edate      = date('Y-m-d', strtotime($request->edate));
        // return UserData::where('date', '>=', $sdate)->where('date', '<=', $edate)->get();
        $statuss =  UserData::where('date', '>=', $sdate)->where('date', '<=', $edate)->select('status')->distinct('status')->get()->toArray();
        $status = [];
        foreach ($statuss as $key => $value) {
            if(!in_array($value[0], $status))
                $status[] = $value[0];
        }
        return ['status' => $status];
    }


    public function manualUserData(Request $request){
        $sdate      = date('Y-m-d', strtotime($request->sdate));
        $edate      = date('Y-m-d', strtotime($request->edate));
        $reportType = $request->reportType;

        
        if($reportType ==  '_all'){
            $query = UserData::where('date', '>=', $sdate)->where('date', '<=', $edate);

            if(isset($request->acc)){
                if(strlen($request->acc) >= 24)
                    $query->where('user_id', $request->acc);
                else
                    $query->where('accountid', $request->acc);
            }

            if(isset($request->sym))
                $query->where('symbol', $request->sym);

            if(isset($request->status))
                $query->where('status', $request->status);

            $datas  = $query->get();

            foreach ($datas as $key => $value) {
                $data[] = array(
                    'user_id'    => User::find($value->user_id)->memberId??$value->user_id,
                    'date'       => $value->date,
                    'time'       => $value->created_at->format('H:i:s')??'',
                    'admin'      => User::find($value->user_id)->getAccountMaster($value->date)->memberId??'',
                    'accountid'  => $value->accountid,
                    'status'     => $value->status,
                    'verifiedBy' => $value->verifiedBy,
                    'symbol'     => $value->symbol,
                    'grossPnl'   => $value->grossPnl,
                    'quantity'   => $value->quantity,
                    'executionId' => $value->executionId,
                    'real_user_id'    => $value->user_id,
                    '_id'=> $value->_id,
                    
                );
                // $data[] = array(
                //     'user_id'    => $value->user_id,
                //     'date'       => $value->date,
                //     'created_at'       => $value->created_at,
                //     'updated_at'       => $value->updated_at,
                //     'admin'      => User::find($value->user_id)->getAccountMaster($value->date)->memberId??'',
                //     'accountid'  => $value->accountid,
                //     'status'     => $value->status,
                //     'verifiedBy' => $value->verifiedBy,
                //     'symbol'     => $value->symbol,
                //     'grossPnl'   => $value->grossPnl,
                //     'quantity'   => $value->quantity,
                //     'executionId' => $value->executionId,
                //     'real_user_id'    => $value->user_id,
                //     '_id'=> $value->_id,
                    
                // );
            }
        }


        if($reportType == 'sbd'){

            if(isset($request->acc)){
                if(strlen($request->acc) >= 24)
                    $match_acc = ['user_id' => $request->acc]; 
                else
                    $match_acc = ['accountid' => $request->acc]; 
            }else
                $match_acc = [];
            
            if(isset($request->sym))
                $match_sym = ['symbol' => $request->sym]; 
            else
                $match_sym = [];

            
            if(isset($request->status))
                $match_status = ['status' => $request->status];
            else
                $match_status = [];
    
            
            $match_date = [
                'date' => [ 
                    '$gte' => $sdate, '$lte' => $edate
                ]
            ];

            $match = array_merge($match_acc,$match_sym,$match_status,$match_date);

            $data =UserData::raw(function($collection) use($sdate, $edate, $match) {
                return $collection->aggregate([
                    [ 
                        '$match' => $match
                    ], 
                    [
                        '$group' => [
                            '_id' => [
                                'date' => '$date',
                                'status' => '$status'
                            ],
                            'quantity' => ['$sum' => ['$toInt'=>'$quantity']],
                            'grossPnl' => ['$sum' => ['$toDouble'=>'$grossPnl']],                            
                        ]
                    ],
                    [
                        '$set' => [
                            'date' => '$_id.date',
                            'quantity' => '$quantity',
                            'grossPnl' => '$grossPnl',
                            'status' => '$_id.status'
                        ]
                    ]
                ]);
            });
        }

        if($reportType == 'tbs'){

            if(isset($request->acc)){
                if(strlen($request->acc) >= 24)
                    $match_acc = ['user_id' => $request->acc]; 
                else
                    $match_acc = ['accountid' => $request->acc]; 
            }else
                $match_acc = [];
            
            
            if(isset($request->sym))
                $match_sym = ['symbol' => $request->sym]; 
            else
                $match_sym = [];

            
            if(isset($request->status))
                $match_status = ['status' => $request->status];
            else
                $match_status = [];
    
            
            $match_date = [
                'date' => [ 
                    '$gte' => $sdate, '$lte' => $edate
                ]
            ];

            $match = array_merge($match_acc,$match_sym,$match_status,$match_date);

            $data  = UserData::raw(function($collection) use($sdate, $edate, $match) {
                return $collection->aggregate([
                    [ 
                        '$match' => $match
                    ], 
                    [
                        '$group' => [
                            '_id' => [
                                'symbol' => '$symbol',
                                'status' => '$status'
                            ],
                            'quantity' => ['$sum' => ['$toInt'=>'$quantity']],
                            'grossPnl' => ['$sum' => ['$toDouble'=>'$grossPnl']],                            
                        ]
                    ],
                    [
                        '$set' => [
                            'symbol'   => '$_id.symbol',
                            'quantity' => '$quantity',
                            'grossPnl' => '$grossPnl',
                            'status'   => '$_id.status'
                        ]
                    ]
                ]);
            });
        }

        if($reportType == 'tba'){

            if(isset($request->acc)){
                if(strlen($request->acc) >= 24)
                    $match_acc = ['user_id' => $request->acc]; 
                else
                    $match_acc = ['accountid' => $request->acc]; 
            }else
                $match_acc = [];
            
            
            if(isset($request->sym))
                $match_sym = ['symbol' => $request->sym]; 
            else
                $match_sym = [];

            
            if(isset($request->status))
                $match_status = ['status' => $request->status];
            else
                $match_status = [];
    
            
            $match_date = [
                'date' => [ 
                    '$gte' => $sdate, '$lte' => $edate
                ]
            ];

            $match = array_merge($match_acc,$match_sym,$match_status,$match_date);

            $data  = UserData::raw(function($collection) use($sdate, $edate, $match) {
                return $collection->aggregate([
                    [ 
                        '$match' => $match
                    ], 
                    [
                        '$group' => [
                            '_id' => [
                                'accountid' => '$accountid',
                                'status'    => '$status'
                            ],
                            'quantity' => ['$sum' => ['$toInt'=>'$quantity']],
                            'grossPnl' => ['$sum' => ['$toDouble'=>'$grossPnl']],                            
                        ]
                    ],
                    [
                        '$set' => [
                            'accountid' => '$_id.accountid',
                            'quantity'  => '$quantity',
                            'grossPnl'  => '$grossPnl',
                            'status'    => '$_id.status'
                        ]
                    ]
                ]);
            });
        }

        if($reportType == 'tbu'){

            if(isset($request->acc)){
                if(strlen($request->acc) >= 24)
                    $match_acc = ['user_id' => $request->acc]; 
                else
                    $match_acc = ['accountid' => $request->acc]; 
            }else
                $match_acc = [];
            
            
            if(isset($request->sym))
                $match_sym = ['symbol' => $request->sym]; 
            else
                $match_sym = [];

            
            if(isset($request->status))
                $match_status = ['status' => $request->status];
            else
                $match_status = [];
    
            
            $match_date = [
                'date' => [ 
                    '$gte' => $sdate, '$lte' => $edate
                ]
            ];

            $match = array_merge($match_acc,$match_sym,$match_status,$match_date);

            $data  = UserData::raw(function($collection) use($sdate, $edate, $match) {
                return $collection->aggregate([
                        [ 
                            '$match' => $match
                        ], 
                        [
                            '$group' => [
                                '_id' => [
                                    'user_id' => '$user_id',
                                    'status' => '$status'
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
                                'user_id' => '$user.memberId',
                                'userId' => '$user._id',
                                'quantity' => '$quantity',
                                'grossPnl' => '$grossPnl',
                                'status' => '$_id.status',
                                '_id' => 0
                            ]
                        ]
                    ]);
            });
        }

        if($reportType == 'sbu'){

            if(isset($request->acc)){
                if(strlen($request->acc) >= 24)
                    $match_acc = ['user_id' => $request->acc]; 
                else
                    $match_acc = ['accountid' => $request->acc]; 
            }else
                $match_acc = [];
            
            
            if(isset($request->sym))
                $match_sym = ['symbol' => $request->sym]; 
            else
                $match_sym = [];

            
            if(isset($request->status))
                $match_status = ['status' => $request->status];
            else
                $match_status = [];
    
            
            $match_date = [
                'date' => [ 
                    '$gte' => $sdate, '$lte' => $edate
                ]
            ];

            $match = array_merge($match_acc,$match_sym,$match_status,$match_date);

            $data  = UserData::raw(function($collection) use($sdate, $edate, $match) {
                return $collection->aggregate([
                        [ 
                            '$match' => $match
                        ], 
                        [
                            '$group' => [
                                '_id' => [
                                    'symbol'  =>  '$symbol',
                                    'user_id' => '$user_id',
                                    'status'  => '$status'
                                    
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
                                'user_id' => '$user.memberId',
                                'symbol'  => '$_id.symbol',
                                'quantity' => '$quantity',
                                'grossPnl' => '$grossPnl',
                                'status' => '$_id.status',
                                '_id' => 0
                            ]
                        ]
                    ]);
            });
        }

        if($reportType == 'tbum'){


            $start    = (new DateTime($sdate))->modify('first day of this month');
            $end      = (new DateTime($edate))->modify('first day of next month');
            $interval = DateInterval::createFromDateString('1 month');
            $period   = new DatePeriod($start, $interval, $end);

            foreach ($period as $dt) {
                $children[0]   = array(
                    'field'      => 'quantity'.$dt->format("M Y"),
                    'headerName' => 'QTY',
                    'sortable'   => true,
                    'filter'     => true,
                    'minWidth'   => 130,
                    'maxWidth'   => 130,
                );
    
                $children[1]   = array(
                    'field'      => 'grossPnl'.$dt->format("M Y"),
                    'headerName' => 'GROSS PnL',
                    'sortable'   => true,
                    'filter'     => true,
                    'minWidth'   => 130,
                    'maxWidth'   => 130,
                );
                $month_arr[] =  array(
                    'headerName' => $dt->format("M Y"),
                    'children'   => $children
                );
            }


            if(isset($request->acc)){
                if(strlen($request->acc) >= 24)
                    $match_acc = ['user_id' => $request->acc]; 
                else
                    $match_acc = ['accountid' => $request->acc]; 
            }else
                $match_acc = [];
            
            
            if(isset($request->sym))
                $match_sym = ['symbol' => $request->sym]; 
            else
                $match_sym = [];

            
            if(isset($request->status))
                $match_status = ['status' => $request->status];
            else
                $match_status = [];
    
            
            $match_date = [
                'date' => [ 
                    '$gte' => $sdate, '$lte' => $edate
                ]
            ];

            $match = array_merge($match_acc,$match_sym,$match_status,$match_date);

            $notprocesseddata = UserData::raw(function($collection) use($sdate, $edate, $match) {
                return $collection->aggregate([
                        [ 
                            '$match' => $match
                        ], 
                        [
                            '$addFields' => [
                                'dateObj' => [
                                    '$toDate' => '$date'
                                ],
                                'month' =>  [
                                    '$month'=> [
                                        '$toDate' => '$date'
                                    ]
                                ],
                                'year' => [
                                    '$year'=> [
                                        '$toDate' => '$date'
                                    ]
                                ]
                            ]
                        ],
                        [
                            '$group' => [
                                
                                '_id' => [
                                    'user_id' => '$user_id',
                                    'month' =>   '$month',
                                    'year' =>    '$year',
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
                                ],
                                'date' => ['$addToSet' => '$date' ]

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
                                'user_id' => '$user.memberId',
                                'quantity' => '$quantity',
                                'grossPnl' => '$grossPnl',
                                'date'     => '$date',
                                '_id' => 0
                            ]
                        ]
                    ]);
            });


            $tbumMemWiseTotal = UserData::raw(function($collection) use($sdate, $edate, $match) {
                return $collection->aggregate([
                        [ 
                            '$match' => $match
                        ], 
                        [
                            '$group' => [
                                
                                '_id' => [
                                    'user_id' => '$user_id',
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
                                ],
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
                                'user_id' => '$user.memberId',
                                'quantity' => '$quantity',
                                'grossPnl' => '$grossPnl',
                                'date'     => '$date',
                                '_id' => 0
                            ]
                        ]
                    ]);
            });


            $tbumMonthWiseTotal = UserData::raw(function($collection) use($sdate, $edate, $match) {
                return $collection->aggregate([
                        [ 
                            '$match' => $match
                        ], 
                        [
                            '$addFields' => [
                                'dateObj' => [
                                    '$toDate' => '$date'
                                ],
                                'month' =>  [
                                    '$month'=> [
                                        '$toDate' => '$date'
                                    ]
                                ],
                                'year' => [
                                    '$year'=> [
                                        '$toDate' => '$date'
                                    ]
                                ]
                            ]
                        ],
                        [
                            '$group' => [
                                
                                '_id' => [
                                    
                                    'month' =>   '$month',
                                    'year' =>    '$year',
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
                                ],
                                'date' => ['$addToSet' => '$date' ]

                            ]
                        ],
                        [
                            '$project' => [
                                'user_id' => '$user.memberId',
                                'quantity' => '$quantity',
                                'grossPnl' => '$grossPnl',
                                'date'     => '$date',
                                '_id' => 0
                            ]
                        ]
                    ]);
            });

            




            foreach($notprocesseddata as $k => $v){
                $user_id  = $v->user_id;
                $data1     = array(
                    'user_id' => $v->user_id,
                    'quantity'.date('M Y', strtotime($v->date[0])) => $v->quantity,
                    'grossPnl'.date('M Y', strtotime($v->date[0])) => $v->grossPnl,
                );

                foreach($notprocesseddata as  $key => $val){
                    if($user_id == $val->user_id){
                        $data2 = array(
                            'user_id' => $val->user_id,
                            'quantity'.date('M Y', strtotime($val->date[0])) => $val->quantity,
                            'grossPnl'.date('M Y', strtotime($val->date[0])) => $val->grossPnl,
                        );
                        $data1 = array_merge ($data1, $data2);
                    }
    
                }
                
                $databt[] = $data1;
            }

            $data = $databt;

            // $data = array_unique($databt,SORT_REGULAR);
            // $data = (array) $data;
            


            foreach($data as $keyT=>$valueT){
                $user_id = $valueT['user_id'];
                foreach($tbumMemWiseTotal as $kT=>$vT){
                    if($user_id == $vT->user_id){
                        $quantity_total = $vT->quantity;
                        $grossPnl_total = $vT->grossPnl;
                    }
                }
                $temp = array(
                    'quantity_total' => $quantity_total,
                    'grossPnl_total' => $grossPnl_total,
                );
                $data[$keyT] = array_merge($data[$keyT], $temp);
            }

            $totalGrossPnl = 0;
            $totalQty      = 0;
            foreach($data as $key=>$value){
                $totalGrossPnl =  $totalGrossPnl + $value['grossPnl_total'];
                $totalQty      =  $totalQty      + $value['quantity_total'];
            }

            $data = array_values(array_unique($data,SORT_REGULAR));


            $footerTbum[0] = array(
                "user_id"        => 'Total',
                "quantity_total" => $totalQty,
                "grossPnl_total" => $totalGrossPnl,
            );
    
            $bottomQty      = 0;
            $bottomGrossPnl = 0;
            foreach($tbumMonthWiseTotal as $km=>$vm){
    
                $tempfooter[0] = array(
                    'quantity'.date('M Y', strtotime($vm->date[0])) => $vm->quantity,
                    'grossPnl'.date('M Y', strtotime($vm->date[0])) => $vm->grossPnl,
                );
                $footerTbum[0] = array_merge($footerTbum[0], $tempfooter[0]);
            }
    
            $footerTbum = (array) $footerTbum;



        }

       
        $totalGrossPnl = 0;
        $totalQty      = 0;
        if($reportType == '_all'){
            foreach($datas as $key=>$value){
                $totalGrossPnl =  $totalGrossPnl + $value->grossPnl;
                $totalQty      =  $totalQty      + ($value->quantity??0);
            }
        }else{
            if($reportType != 'tbum'){
                foreach($data as $key=>$value){
                    $totalGrossPnl =  $totalGrossPnl + $value->grossPnl;
                    $totalQty      =  $totalQty      + $value->quantity;
                }
            }
        }

        
        
        if(!isset($month_arr))
            $month_arr = "";

        if(!isset($data)){
            $data= NULL;
        }

        if(!isset($footerTbum)){
            $footerTbum= "";
        }

        return response()->json(['data'=> $data, 'footerTbum'=>$footerTbum, 'totalGrossPnl'=>$totalGrossPnl, 'totalQty'=>$totalQty, 'reportType'=>$reportType, 'month_arr'=>$month_arr], 200);
    }
    public function edit_detailed_report(Request $request){
        $techFee = UserData::where('_id', $request->_id)->first();
        foreach ($request->all() as $key => $value) {
            if($key != 'user_id')
                $techFee->{$key} = $value;
        }$techFee->update();
        return response()->json(["message"=>"success"]);
    }
    public function fetch_detailed_report(){
        $datas = UserData::all();
        // $finalData = [];
        
        // foreach ($datas as  $value) {
            
        //     $finalData[] = ;
        // }
       
        return response()->json($datas);
    }
    public function del_detailed_report(Request $request){
        UserData::whereIn('_id', $request->ids)->delete();
        return response()->json(["message"=>"success"]);
    }

    public function input_trade_data_edit(Request $request){
        // return Carbon::parse($request->date)->toDateString();
        // $user = UserData::where('_id', $request->_id )->first();
        $user = UserData::where('_id', $request->_id)->first();
    
        $editedUser =  UserData::find($request->_id);
        $user->date = Carbon::parse($request->date)->toDateString();
        $user->symbol = $request->symbol;
        $user->grossPnl = $request->grossPnl;
        $user->quantity = $request->quantity;
        $user->status   = "not verified";
        $user->save();

        // $editedUser =  UserData::find($request->_id);

        event(new UserTradeDataUpdated($editedUser));

        return response()->json($editedUser);

    }

    function trade_data_delete(Request $request){
        UserData::whereIn('_id', $request->ids)->delete();
        event(new UserTradeDataDeleted($request->rows));
        return response()->json($request->rows);        
    }

}
