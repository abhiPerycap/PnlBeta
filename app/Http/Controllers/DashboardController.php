<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ManualTrade;
use App\Models\Role;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ManualTradeController extends Controller
{
    public function index()
    {
      if($this->checkPermission('inputTradeData', 'authorised'))
        return ManualTrade::all();
      else
        return response()
          ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
    }

    public function store(Request $request)
    {
      if($this->checkPermission('inputTradeData', 'canAdd')){
        // return $request->all();
        // $obj = new ManualTrade($request->all());
        return auth()->user()->userDatas()->save(
            new ManualTrade($request->all())
        );
        if($obj->save()==1){
          return response()->json(['message' => 'success'], 200);
        }else{
          return response()
            ->json(['message' => 'Couldn\'t Save the Data'], 500);
        }
      }else{
        return response()
            ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
      }
    }

    public function update(Request $request, $userData)
    {

      if($this->checkPermission('inputTradeData', 'canModify')){

        try{
          $obj = ManualTrade::find($userData);
        }catch(ModelNotFoundException $e){
          return response()->json(['message' => 'Data not found'], 404);
        }
        if($obj->update($request->all())==1){
          return response()->json(['message' => 'success'], 200);
        }else{
          return response()
            ->json(['message' => 'Couldn\'t Save the Data'], 500);
        }
      }else{
        return response()
            ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
      }
    }

    public function destroy($userData)
    {
      if($this->checkPermission('inputTradeData', 'canDelete')){
        try{
          $userData = ManualTrade::findOrFail($userData);
        }catch(ModelNotFoundException $e){
          return response()->json(['message' => 'Trade Data not found'], 404);
        }
        $userData->delete();
        return response()->json(['message' => 'success'], 200);
      }else{
        return response()
            ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
      }
    }


    public function destroyMultiple(Request $request)
    {
      if($this->checkPermission('inputTradeData', 'canDelete')){
        try{
          $userDatas = ManualTrade::whereIn('_id', $request->ids)->get();
        }catch(ModelNotFoundException $e){
          return response()->json(['message' => 'User Data not found'], 404);
        }
        $ids = [];
        foreach($userDatas as $userData){
          $ids[] = $userData->_id;
        }

        ManualTrade::whereIn('_id', $ids)->delete();
        return response()->json(['message' => 'success'], 200);
      }else{
        return response()
            ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
      }
    }
}
