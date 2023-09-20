<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Symbol;
use App\Models\SymbolGroup;
use App\Models\Role;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Excel;
use App\Imports\SymbolsImport;


class SymbolManagerController extends Controller
{
  public function index()
  {
    if ($this->checkPermission('inputTradeData', 'authorised')){
      $branchList = Symbol::all()->pluck('exchange')->toArray();
      $branchList = array_unique($branchList);
      $list = [];
      foreach ($branchList as $value) {
        if($value!='')
          $list[] = [
            'id' => $value,
            'text' => $value,
          ];
      }
      return ['symbolList' => Symbol::orderBy('created_at', 'desc')->get(), 'exchangeList' => $list];
    }
    else
      return response()
        ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
  }

  public function indexGroup()
  {
    if ($this->checkPermission('inputTradeData', 'authorised'))
      return SymbolGroup::orderBy('created_at', 'desc')->get();
    else
      return response()
        ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
  }

  public function store(Request $request)
  {
    if ($this->checkPermission('inputTradeData', 'canAdd')) {
      // return $request->all();
      $obj = new Symbol();
      $obj['name'] = $request->name;
      $obj['fullName'] = $request->fullName;
      $obj['exchange'] = $request->exchange;
      $obj['status'] = ($request->status)?$request->status:'pending';
      return auth()->user()->requestedSymbols()->save($obj);
      if ($obj->save() == 1) {
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

  
  public function storeGroup(Request $request)
  {
    if ($this->checkPermission('inputTradeData', 'canAdd')) {
      // return $request->all();
      if(SymbolGroup::where('groupName', $request->groupName)->count()==0){
        $obj = new SymbolGroup();
        $obj['groupName'] = $request->groupName;
        $obj['symbol_ids'] = $request->ids;
        // return auth()->user()->requestedSymbols()->save($obj);
        if ($obj->save() == 1) {
          return response()->json(['message' => 'success', 'data' => $obj], 200);
        } else {
          return response()
            ->json(['message' => 'Couldn\'t Save the Data'], 500);
        }

      }else{
        return response()
          ->json(['message' => 'Group Name already Exists. Please choose another Name.'], 200);

      }
    } else {
      return response()
        ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
    }
  }

  public function update(Request $request, $symbol)
  {

    if ($this->checkPermission('inputTradeData', 'canModify')) {

      try {
        $obj = Symbol::find($symbol);
        foreach ($request->all() as $key => $value) {
          if (isset($obj[$key])) {
            $obj[$key] = $value;
          }
        }
        if ($obj->update() == 1) {
          return response()->json(['message' => 'success'], 200);
        } else {
          return response()
            ->json(['message' => 'Couldn\'t Save the Data'], 500);
        }
      } catch (ModelNotFoundException $e) {
        return response()->json(['message' => 'Data not found'], 404);
      }
    } else {
      return response()
        ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
    }
  }

  public function updateGroup(Request $request, $symbol)
  {

    if ($this->checkPermission('inputTradeData', 'canModify')) {

      try {
        $obj = SymbolGroup::find($symbol);
        foreach ($request->all() as $key => $value) {
          if (isset($obj[$key])) {
            $obj[$key] = $value;
          }
        }
        if ($obj->update() == 1) {
          return response()->json(['message' => 'success', 'data'=> $obj], 200);
        } else {
          return response()
            ->json(['message' => 'Couldn\'t Save the Data'], 500);
        }
      } catch (ModelNotFoundException $e) {
        return response()->json(['message' => 'Data not found'], 404);
      }
    } else {
      return response()
        ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
    }
  }


  public function updateMultiple(Request $request)
  {

    if ($this->checkPermission('inputTradeData', 'canModify')) {

      try {
        $obj = Symbol::whereIn('_id', $request->ids)->update(['status' => $request->status]);
        return response()->json(['message' => 'success'], 200);
      } catch (ModelNotFoundException $e) {
        return response()->json(['message' => 'Data not found'], 404);
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
        $symbolsData = Symbol::whereIn('_id', $request->ids)->get();
      } catch (ModelNotFoundException $e) {
        return response()->json(['message' => 'Symbol Data not found'], 404);
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
        $userDatas = Symbol::whereIn('_id', $request->ids)->get();
      } catch (ModelNotFoundException $e) {
        return response()->json(['message' => 'User Data not found'], 404);
      }
      $ids = [];
      foreach ($userDatas as $userData) {
        $ids[] = $userData->_id;
      }

      Symbol::whereIn('_id', $ids)->delete();
      return response()->json(['message' => 'success'], 200);
    } else {
      return response()
        ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
    }
  }
  public function destroyMultipleGroup(Request $request)
  {
    if ($this->checkPermission('inputTradeData', 'canDelete')) {
      try {
        $userDatas = SymbolGroup::whereIn('_id', $request->ids)->get();
      } catch (ModelNotFoundException $e) {
        return response()->json(['message' => 'User Data not found'], 404);
      }
      $ids = [];
      foreach ($userDatas as $userData) {
        $ids[] = $userData->_id;
      }

      SymbolGroup::whereIn('_id', $ids)->delete();
      return response()->json(['message' => 'success'], 200);
    } else {
      return response()
        ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
    }
  }

  public function downloadSample()
  {
    $myFile = public_path("Symbol.xlsx");
    $headers = ['Content-Type: application/xlsx'];
    $newName = 'sample-symbol-' . time() . '.xlsx';

    return response()->download($myFile, $newName, $headers);
  }

  public function uploadSymbol(Request $request)
  {
    // return auth()->user()['_id'];
    // $array = (new SymbolsImport())->toArray(request()->file('file'));
    // $array = Excel::import(new SymbolsImport, request()->file('file'));
    Excel::import(new SymbolsImport(auth()->user()['_id']), request()->file('file'));
    // return $array;
  }
}