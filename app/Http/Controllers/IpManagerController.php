<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\IpList;
use App\Models\Role;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Excel;
use App\Imports\IpListImport;


class IpManagerController extends Controller
{
  public function index()
  {
    if ($this->checkPermission('inputTradeData', 'authorised')) {
      $branchList = IpList::all()->pluck('branch')->toArray();
      $branchList = array_unique($branchList);
      $list = [];
      foreach ($branchList as $value) {
        $list[] = [
          'id' => $value,
          'text' => $value,
        ];
      }
      return ['ipList' => IpList::orderBy('_id')->get(), 'branchList' => $list];
    } else
      return response()
        ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
  }

  public function store(Request $request)
  {
    if ($this->checkPermission('inputTradeData', 'canAdd')) {
      // return $request->all();
      $obj = new IpList();
      $obj['ipAddress'] = $request->ipAddress;
      $obj['branch'] = $request->branch;
      $obj['comments'] = $request->comments;
      $obj['authorised'] = true;
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

  public function update(Request $request, $symbol)
  {

    if ($this->checkPermission('inputTradeData', 'canModify')) {

      try {
        $obj = IpList::find($symbol);
        foreach ($request->all() as $key => $value) {
          if (isset($obj[$key])) {
            $obj[$key] = $value;
          }
        }
        if ($obj->update() == 1) {
          return response()->json(['message' => 'success', 'data' => $obj], 200);
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
        $obj = IpList::whereIn('_id', $request->ids)->update(['authorised' => $request->authorised]);
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
        $symbolsData = IpList::whereIn('_id', $userData)->get();
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
        $userDatas = IpList::whereIn('_id', $request->ids)->get();
      } catch (ModelNotFoundException $e) {
        return response()->json(['message' => 'User Data not found'], 404);
      }
      $ids = [];
      foreach ($userDatas as $userData) {
        $ids[] = $userData->_id;
      }

      IpList::whereIn('_id', $ids)->delete();
      return response()->json(['message' => 'success'], 200);
    } else {
      return response()
        ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
    }
  }

  public function downloadSample()
  {
    $myFile = public_path("Ip.xlsx");
    $headers = ['Content-Type: application/xlsx'];
    $newName = 'sample-ip-' . time() . '.xlsx';

    return response()->download($myFile, $newName, $headers);
  }

  public function uploadIp(Request $request)
  {
    // return auth()->user()['_id'];
    // $array = (new IpListImport())->toArray(request()->file('file'));
    // $array = Excel::import(new IpListImport, request()->file('file'));
    Excel::import(new IpListImport(auth()->user()['_id']), request()->file('file'));
    // return $array;
  }
}