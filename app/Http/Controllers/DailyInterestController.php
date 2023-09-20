<?php

namespace App\Http\Controllers;
use App\Models\DailyInterest;
use App\Models\User;
use App\Models\TradeAccount;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DailyInterestController extends Controller
{
    function add_dailyinterest(Request $request){

        $obj = new DailyInterest();
        $data = $request['data'];
        
        if($this->checkIfDiExists(Carbon::parse($data['effectiveFrom'])->toDateString(), $data['effectiveFor'])!=false){
            $obj = $this->checkIfDiExists(Carbon::parse($data['effectiveFrom'])->toDateString(), $data['effectiveFor']);
            $data = $request['data'];

            $obj['category'] = $data['category'];
            $obj['comment'] = $data['comment'];
            $obj['effectiveFrom'] = Carbon::parse($data['effectiveFrom'])->toDateString();
            $obj['value'] = $data['value'];
            $obj->update();
            
            if(strpos($data['effectiveFor'], 'acc_') !== false){

                $obj->user()->dissociate()->save();
                $obj->tradeAccount()->associate(TradeAccount::where('accountid', explode('_', $data['effectiveFor'])[1])->first())->save();
            }else{
                $obj->tradeAccount()->dissociate()->save();
                $obj->user()->associate(User::where('_id', $data['effectiveFor'])->first())->save();
            }
        }else{
            $obj['category'] = $data['category'];
            $obj['comment'] = $data['comment'];
            $obj['effectiveFrom'] = Carbon::parse($data['effectiveFrom'])->toDateString();
            $obj['value'] = $data['value'];
            $obj->save();

            if(strpos($data['effectiveFor'], 'acc_') !== false){
                $obj->tradeAccount()->associate(TradeAccount::where('accountid', explode('_', $data['effectiveFor'])[1])->first())->save();
            }else{
                $obj->user()->associate(User::where('_id', $data['effectiveFor'])->first())->save();

            }
        }

        
        return response()->json(['message'=>"success", 'data' => $obj], 200);
    }

    public function checkIfDiExists($date, $effectiveFor)
    {
        // return DailyInterest::with('user', 'tradeAccount')->where('effectiveFrom', $date)->where('user_id', $effectiveFor)->get();
        if(strpos($effectiveFor, 'acc_') !== false){
            $ta = TradeAccount::where('accountid', explode('_', $effectiveFor)[1])->first();
            return DailyInterest::with('user', 'tradeAccount')->where('effectiveFrom', $date)->where('trade_account_id', $ta['_id'])->get()->count()==1?DailyInterest::where('effectiveFrom', $date)->where('trade_account_id', $ta['_id'])->first():false;
        }else{
            return DailyInterest::with('user', 'tradeAccount')->where('effectiveFrom', $date)->where('user_id', $effectiveFor)->get()->count()==1?DailyInterest::where('effectiveFrom', $date)->where('user_id', $effectiveFor)->first():false;
        }
    }

    function del_dailyinterest(Request $request){

        $dis = DailyInterest::whereIn('_id', $request->ids)->get();
        foreach ($dis as $value) {
            $value->user()->dissociate();
            $value->tradeAccount()->dissociate();
            $value->save();
        }
        DailyInterest::whereIn('_id', $request->ids)->delete();
        return response()->json(["message"=>"success"], 200);
    }

    function edit_dailyinterest(Request $request, $di){
       
        // $techFee = DailyInterest::where('_id',$request->_id)->first();
        // foreach($request->all() as $key => $value){
        //     $techFee->{$key} = $value;
        // }$techFee->update();
        // return response()->json(['message'=>'success']);


        $obj = DailyInterest::where('_id', $di)->first();
        $data = $request['data'];

        $obj['category'] = $data['category'];
        $obj['comment'] = $data['comment'];
        $obj['effectiveFrom'] = Carbon::parse($data['effectiveFrom'])->toDateString();
        $obj['value'] = $data['value'];
        $obj->update();
        
        if(strpos($data['effectiveFor'], 'acc_') !== false){

            $obj->user()->dissociate()->save();
            $obj->tradeAccount()->associate(TradeAccount::where('accountid', explode('_', $data['effectiveFor'])[1])->first())->save();
        }else{
            $obj->tradeAccount()->dissociate()->save();
            $obj->user()->associate(User::where('_id', $data['effectiveFor'])->first())->save();
        }
        return response()->json(['message'=>"success", 'data' => $obj], 200);
    }

    function fetch_dailyinterest(){

        $datas = DailyInterest::with('user', 'tradeAccount')->orderBy('effectiveFrom', 'desc')->get();
   
       
        return response()->json($datas);
    }
}
