<?php

namespace App\Http\Controllers;

use App\Models\Adjustment;
use Illuminate\Http\Request;

class AdjustmentController extends Controller
{
    public function fetch_adjustment()
    {
        $datas = Adjustment::all();
        return response()->json($datas);
    }
    public function add_adjustment(Request $request)
    {
        Adjustment::create($request->all());
        return response()->json(['messages' => "OK"]);
    }
    public function edit_adjustment(Request $request)
    {
        $techFee = Adjustment::where('_id', $request->_id)->first();
        foreach ($request->all() as $key => $value) {
            $techFee->{$key} = $value;
        }$techFee->update();
        return response()->json(['message' => 'success']);
    }
    public function del_adjustment(Request $request)
    {
        Adjustment::whereIn('_id', $request->ids)->delete();
        return response()->json(["message" => "success"]);
    }
}
