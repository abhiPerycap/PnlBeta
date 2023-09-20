<?php

namespace App\Http\Controllers;
use App\Models\Techfee;
use Illuminate\Http\Request;

class TechfeeController extends Controller
{
    function addTechfee(Request $request){
        Techfee::create($request->all());
        return response()->json($request);
    }

    function deleteTechfees(Request $request){

        Techfee::whereIn('_id', $request->ids)->delete();
        return response()->json(["message"=>"success"]);
    }

    function editTechfees(Request $request){
        $techFee = Techfee::where('_id',$request->_id)->first();
        foreach($request->all() as $key => $value){
            $techFee->{$key} = $value;
        }$techFee->update();
        return response()->json(['message'=>'success']);

       
    }
    function fetchTechfees(){
        $datas = Techfee::all();
        // $finalData = [];
        
        // foreach ($datas as  $value) {
            
        //     $finalData[] = ;
        // }
       
        return response()->json($datas);


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
    }
}
