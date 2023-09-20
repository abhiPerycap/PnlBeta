<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\DetailedBase;
use App\Models\Open;
use App\Models\Adjustment;
use App\Models\PreviousDayOpen;
use App\Models\TradeAccount;
use App\Models\Role;
use App\Models\Acmap;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TradeAccountController extends Controller
{
  public function index()
  {
    // return 'hi';
    if ($this->checkPermission('broker', 'authorised')) {
      $accounts = TradeAccount::with('broker')->get()->toArray();

      $accounts = $this->processTradeAccounts($accounts);

      return $accounts;
      // return TradeAccount::with('broker')->get();
    } else
      return response()
        ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
  }

  public function store(Request $request)
  {
    if ($this->checkPermission('broker', 'canAdd')) {
      $obj = new TradeAccount();
      if (TradeAccount::where('accountid', $request->account['accountid'])->count() > 0) {
        return response()->json(['message' => 'Account Already Exists'], 200);
      } else {
        foreach ($request->account as $key => $value) {
          $obj->{$key} = $value;
        }
        if ($obj->save() == 1) {
          $obj->broker;
          return response()->json(['message' => 'success', 'account' => $obj], 200);
        } else {
          return response()
            ->json(['message' => 'Couldn\'t Save the Data'], 500);
        }
      }
    } else {
      return response()
        ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
    }
  }

  public function update(Request $request, $tradeAccount)
  {

    if ($this->checkPermission('broker', 'canModify')) {
      try {
        $obj = TradeAccount::find($tradeAccount);
      } catch (ModelNotFoundException $e) {
        return response()->json(['message' => 'Data not found'], 404);
      }
      foreach ($request->account as $key => $value) {
        $obj->{$key} = $value;
      }
      if ($obj->update() == 1) {
        $obj->broker;
        return response()->json(['message' => 'success', 'account' => $obj], 200);
      } else {
        return response()
          ->json(['message' => 'Couldn\'t Save the Data'], 500);
      }
    } else {
      return response()
        ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
    }
  }

  public function destroy($tradeAccount)
  {
    if ($this->checkPermission('broker', 'canDelete')) {
      try {
        $tradeAccount = TradeAccount::findOrFail($tradeAccount);
      } catch (ModelNotFoundException $e) {
        return response()->json(['message' => 'TradeAccount not found'], 404);
      }
      $tradeAccount->delete();
      return response()->json(['message' => 'success'], 200);
    } else {
      return response()
        ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
    }
  }


  public function destroyMultiple(Request $request)
  {
    if ($this->checkPermission('broker', 'canDelete')) {
      try {
        $tradeAccounts = TradeAccount::whereIn('_id', $request->ids)->get();
      } catch (ModelNotFoundException $e) {
        return response()->json(['message' => 'TradeAccount not found'], 404);
      }
      $ids = [];
      foreach ($tradeAccounts as $tradeAccount) {
        $ids[] = $tradeAccount->_id;
      }

      TradeAccount::whereIn('_id', $ids)->delete();
      return response()->json(['message' => 'success'], 200);
    } else {
      return response()
        ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
    }
  }

  public function test()
  {
    $datas = TradeAccount::all();
    foreach ($datas as $key => $value) {
      echo json_encode($value->getApiDetails());
    }
  }


  public function toggleColumn(Request $request, $type)
  {
    if ($this->checkPermission('broker', 'canModify')) {
      try {
        $tradeAccounts = TradeAccount::whereIn('_id', $request->ids)->get();
        foreach ($tradeAccounts as $key => $value) {
          $value[$type] = !$value[$type];
          $value->update();
        }

        $accounts = TradeAccount::whereIn('_id', $request->ids)->get()->toArray();
        return response()->json(['message' => 'success', 'rows' => $this->processTradeAccounts($accounts)], 200);
      } catch (ModelNotFoundException $e) {
        return response()->json(['message' => 'TradeAccount(s) not found'], 404);
      }
      
    } else {
      return response()
        ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
    }
  }

  public function toggleSource(Request $request)
  {
    if ($this->checkPermission('broker', 'canModify')) {
      try {
        $tradeAccounts = TradeAccount::whereIn('_id', $request->ids)->with('broker')->get();
      } catch (ModelNotFoundException $e) {
        return response()->json(['message' => 'TradeAccount not found'], 404);
      }
      $ids = [];
      foreach ($tradeAccounts as $tradeAccount) {
        if ($tradeAccount['source'] == 'fetched') {
          $tradeAccount['source'] = 'manual';
          $tradeAccount['apiDetails'] = [
            'url' => $tradeAccount['broker']['apiDetails']['url'],
            'username' => $tradeAccount['broker']['apiDetails']['username'],
            'password' => $tradeAccount['broker']['apiDetails']['password'],
          ];
          $tradeAccount->update();
          $ids[] = $tradeAccount->_id;
        } else {
          if ($tradeAccount['propReportId'] == null) {
            $tradeAccount['autoReport'] = false;
          } else {
            $tradeAccount['source'] = 'fetched';
            $tradeAccount['apiDetails'] = null;
            $tradeAccount['autoReport'] = true;
          }
          $tradeAccount->update();
          $ids[] = $tradeAccount->_id;
        }
      }
      $accounts = TradeAccount::whereIn('_id', $ids)->get()->toArray();

      return response()->json(['message' => 'success', 'rows' => $this->processTradeAccounts($accounts)], 200);
    } else {
      return response()
        ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
    }
  }

  public function processTradeAccounts($accounts)
  {
    foreach ($accounts as $key => $value) {
      $removeFlag = true;
      $accounts[$key]['removable'] = true;

      if (Acmap::where('mappedTo', $value['accountid'])->count() > 0)
        $removeFlag = false;

      // if(DetailedBase::where('accountid', $value['accountid'])->count()>0)
      //   $removeFlag = false;
      //
      // if(Adjustment::where('accountid', $value['accountid'])->count()>0)
      //   $removeFlag = false;
      //
      // if(Open::where('accountid', $value['accountid'])->count()>0)
      //   $removeFlag = false;
      //
      // if(PreviousDayOpen::where('accountid', $value['accountid'])->count()>0)
      //   $removeFlag = false;
      $accounts[$key]['removable'] = $removeFlag;
    }
    return $accounts;
  }
}