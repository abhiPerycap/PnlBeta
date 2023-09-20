<?php

namespace App\Http\Controllers;

use App\Models\DefaultCharges;
use Illuminate\Http\Request;
class DefaultChargesController extends Controller
{
    // public function del_defaultCharges(Request $request)
    // {
    //     DefaultCharges::whereIn('_id', $request->ids)->delete();
    //     return response()->json(["message" => "success"]);
    // }
    public function edit_defaultCharges(Request $request)
    {
        if(DefaultCharges::all()->isNotEmpty()){
            $techFee = DefaultCharges::all()->first();
            foreach ($request->all() as $key => $value) {
                $techFee->{$key} = $value;
            }$techFee->update();
            return response()->json(['message' => 'success'], 200);
        }else{
            DefaultCharges::create($request->all());
            return response()->json(['message' => "success"], 200);
        }
    }

    public function fetch_defaultCharges()
    {
        if(DefaultCharges::all()->isNotEmpty()){
            $data = DefaultCharges::all()->first();
            return response()->json(['message' => "success", 'data' => $data->toArray()], 200);
        }else{
            return response()->json(['message' => "Data Not Found"], 200);

        }
    }
}
