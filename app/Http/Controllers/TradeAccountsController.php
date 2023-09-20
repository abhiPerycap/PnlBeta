<?php

namespace App\Http\Controllers;
use App\Models\TradeAccount;
use Illuminate\Http\Request;

class TradeAccountsController extends Controller
{
    function tradeAccounts(){
        $tradeAccounts = TradeAccount::all();
        return response()->json($tradeAccounts);
    }

    function deleteTradeAccount(Request $request){

        $ids = explode(',', $request->ids);
        try{
            $tradeAccount = TradeAccount::whereIn('_id', $ids)->delete();
            return response()->json(['message' => 'success'], 200);
        }catch(ModelNotFoundException $e){
            return response()->json(['message' => 'Data not found'], 404);
        }  
    }

    function EditTradeAccount(Request $request){
        $ids = explode(',', $request->ids);
        try{
            $obj = TradeAccount::whereIn('_id', $ids)->update($request->all());
            return response()->json(['message' => 'success'], 200);
        }catch(ModelNotFoundException $e){
            return response()->json(['message' => 'Data not found'], 404);
        }
    }

    function addTradeAccounts(Request $request){
        tradeAccount::create($request->all());
        return response()->json(['message'=>"OK"]);
    }

    function getPreferences(Request $request){
        // $data = TradeAccount::find('62ad87c7801b69c2aa0217cb');
        // return $data->getTradeAccountPreferences;

        // $data = TradeAccount::find('62ad87c7801b69c2aa0217cb')->getTradeAccountPreferences->where('effectiveFor', 'CTS013')->where('eff_from', '4-5-2022');
        // return $data;

        
        if($request->date)
            $date = $request->date;
        else
            $date = date('d-m-Y');
        
        $data = TradeAccount::findOrFail($request->acc)->getTradeAccountPreferences->where('eff_from', $date)->first();
        
        if($data)
            return response()->json(['message' => $data->preferenceCols], 200);
        else 
            return response()->json(['message' => 'Data not found'], 404);
    }
}
