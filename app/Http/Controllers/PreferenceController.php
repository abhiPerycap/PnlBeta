<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Preference;
use App\Models\TradeAccount;
use App\Models\User;
use Carbon\Carbon;


class PreferenceController extends Controller
{
    function addPreference(Request $request){
        $obj = new Preference();
        $data = $request['data'];
        
        if($this->checkIfPreferenceExists(Carbon::parse($data['effectiveFrom'])->toDateString(), $data['effectiveFor'])!=false){
            $obj = $this->checkIfPreferenceExists(Carbon::parse($data['effectiveFrom'])->toDateString(), $data['effectiveFor']);
            $data = $request['data'];

            
            $obj['effectiveFrom'] = Carbon::parse($data['effectiveFrom'])->toDateString();
            $obj['preferenceCols'] = $data['preferenceCols'];
            $obj->update();
            
            if(strpos($data['effectiveFor'], 'acc_') !== false){

                $obj->user()->dissociate()->save();
                $obj->tradeAccount()->associate(TradeAccount::where('accountid', explode('_', $data['effectiveFor'])[1])->first())->save();
            }else{
                $obj->tradeAccount()->dissociate()->save();
                $obj->user()->associate(User::where('_id', $data['effectiveFor'])->first())->save();
            }
        }else{
            
            $obj['effectiveFrom'] = Carbon::parse($data['effectiveFrom'])->toDateString();
            $obj['preferenceCols'] = $data['preferenceCols'];
            $obj->save();

            if(strpos($data['effectiveFor'], 'acc_') !== false){
                $obj->tradeAccount()->associate(TradeAccount::where('accountid', explode('_', $data['effectiveFor'])[1])->first())->save();
            }else{
                $obj->user()->associate(User::where('_id', $data['effectiveFor'])->first())->save();

            }
        }

        
        return response()->json(['message'=>"success", 'data' => $obj], 200);
    }

    function deletePreference(Request $request){
        
        $preferences = Preference::whereIn('_id', $request->ids)->get();
        foreach ($preferences as $value) {
            $value->user()->dissociate();
            $value->tradeAccount()->dissociate();
            $value->save();
        }
        Preference::whereIn('_id', $request->ids)->delete();
        return response()->json(["message"=>"success"], 200);
    }

    function editPreference(Request $request, $preference){
        try{
            $obj = Preference::where('_id', $preference)->first();
            $data = $request['data'];

            
            $obj['effectiveFrom'] = Carbon::parse($data['effectiveFrom'])->toDateString();
            $obj['preferenceCols'] = $data['preferenceCols'];
            $obj->update();
            
            if(strpos($data['effectiveFor'], 'acc_') !== false){

                $obj->user()->dissociate()->save();
                $obj->tradeAccount()->associate(TradeAccount::where('accountid', explode('_', $data['effectiveFor'])[1])->first())->save();
            }else{
                $obj->tradeAccount()->dissociate()->save();
                $obj->user()->associate(User::where('_id', $data['effectiveFor'])->first())->save();
            }
            return response()->json(['message'=>"success", 'data' => $obj], 200);

        }catch(ModelNotFoundException $e){

            return response()->json(['message' => 'Data not found'], 200);
        }

        
    }

    function fetchPreferences(){
        $datas = Preference::with('user', 'tradeAccount')->orderBy('effectiveFrom', 'desc')->get();
        $finalData = [];

        foreach ($datas as $key => $value) {
            $finalData[] = array_merge($value->toArray(), $value->preferenceCols);
        }
        return $finalData;
        


        // foreach($datas as $data){
        //     $finalData1 = array(
        //         'id' => $data['text'], 
        //         'text' => $data['text'], 

        //     );

        //     $finalData3 = array();
            
        //     foreach($data->preferenceCols as $cols){
        //         $finalData2 = array(
        //             $cols['key'] => $cols['value']
        //         );

        //         $finalData3 = array_merge($finalData3,$finalData2);
        //     }

        //     $finalData[] = array_merge($finalData1,$finalData3);
        // }
        // return response()->json($datas);
    }


    public function checkIfPreferenceExists($date, $effectiveFor)
    {
        // return Preference::with('user', 'tradeAccount')->where('effectiveFrom', $date)->where('user_id', $effectiveFor)->get();
        if(strpos($effectiveFor, 'acc_') !== false){
            $ta = TradeAccount::where('accountid', explode('_', $effectiveFor)[1])->first();
            return Preference::with('user', 'tradeAccount')->where('effectiveFrom', $date)->where('trade_account_id', $ta['_id'])->get()->count()==1?Preference::where('effectiveFrom', $date)->where('trade_account_id', $ta['_id'])->first():false;
        }else{
            return Preference::with('user', 'tradeAccount')->where('effectiveFrom', $date)->where('user_id', $effectiveFor)->get()->count()==1?Preference::where('effectiveFrom', $date)->where('user_id', $effectiveFor)->first():false;
        }
    }


}
