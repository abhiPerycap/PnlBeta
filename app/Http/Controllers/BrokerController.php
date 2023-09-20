<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Broker;
use App\Models\DetailedBase;
use App\Models\Open;
use App\Models\Adjustment;
use App\Models\PreviousDayOpen;

use App\Models\Acmap;
use App\Models\TradeAccount;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Utils\PropReportsApiClient;
use Carbon\Carbon;

class BrokerController extends Controller
{
    public function index()
    {
      if($this->checkPermission('broker', 'authorised')){
        return $data = Broker::with('accounts')->get()->toArray();
        // $accountsCountData = TradeAccount::raw(function ($collection) {
        //   return $collection->aggregate([
				// 		[
				// 			'$group' => [
				// 				'_id' => '$broker_id',
				// 				'count' => [
				// 					'$sum' => 1
				// 				]
				// 			]
				// 		]
				// 	]);
        // });



          foreach ($data as $key1 => $value1) {
            foreach ($value1['accounts'] as $key => $value) {
              $removeFlag = true;
              $data[$key1]['accounts'][$key]['removable'] = true;

              if(Acmap::where('mappedTo', $value['accountid'])->count()>0)
                $removeFlag = false;

              if(DetailedBase::where('accountid', $value['accountid'])->count()>0)
                $removeFlag = false;

              if(Adjustment::where('accountid', $value['accountid'])->count()>0)
                $removeFlag = false;

              if(Open::where('accountid', $value['accountid'])->count()>0)
                $removeFlag = false;

              if(PreviousDayOpen::where('accountid', $value['accountid'])->count()>0)
                $removeFlag = false;

              $data[$key1]['accounts'][$key]['removable'] = $removeFlag;
            }
          }
          return $data;
        // return Broker::with('accounts')->get();
      }
      else
        return response()
          ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
    }

    public function store(Request $request)
    {
      if($this->checkPermission('broker', 'canAdd')){
        $obj = new Broker();
        foreach($request->broker as $key => $value){
          $obj->{$key} = $value;
        }
        if($obj->save()==1){
          foreach($request->accounts as $value){
            $tradeAccount = new TradeAccount();
            foreach ($value as $key => $value1) {
              if($key!='selected')
                  $tradeAccount->{$key} = $value1;
            }
            $tradeAccount['source'] = 'fetched';
            $tradeAccount->autoReport = true;
            $tradeAccount->authorised = true;
            $obj->accounts()->save($tradeAccount);
          }
          return response()->json(['message' => 'success', 'broker' => Broker::with('accounts')->find($obj->_id)], 200);
        }else{
          return response()
            ->json(['message' => 'Couldn\'t Save the Data'], 500);
        }
      }else{
        return response()
            ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
      }
    }

    public function update(Request $request, $broker)
    {

      if($this->checkPermission('broker', 'canModify')){
        try{
          $obj = Broker::find($broker);
        }catch(ModelNotFoundException $e){
          return response()->json(['message' => 'Data not found'], 404);
        }
        foreach($request->broker as $key => $value){
          $obj->{$key} = $value;
        }
        if($obj->update()==1){
          $fetchedAccounts = [];
          $existingAccounts = $obj->accounts()->pluck('propReportId')->toArray();
          foreach($request->accounts as $value){
            $fetchedAccounts[] = $value['propReportId'];
          }
          $newAccounts = array_diff($fetchedAccounts, $existingAccounts);
          $deletedAccounts = array_diff($existingAccounts, $fetchedAccounts);
          // return $newAccounts;

          foreach ($obj->accounts as $value) {
            if(in_array($value->propReportId, $deletedAccounts)){
              // return $value;
              $value->delete();
            }
          }

          foreach($request->accounts as $value){
            if(in_array($value['propReportId'], $newAccounts)){
              $tradeAccount = new TradeAccount();

              foreach ($value as $key => $value1) {
                if($key!='selected')
                  $tradeAccount->{$key} = $value1;
              }
              $tradeAccount['source'] = 'fetched';
              $tradeAccount->autoReport = true;
              $tradeAccount->authorised = true;
              $obj->accounts()->save($tradeAccount);
            }
          }
          return response()->json(['message' => 'success', 'broker' => $obj], 200);
        }else{
          return response()
            ->json(['message' => 'Couldn\'t Save the Data'], 500);
        }
        if($obj->update()==1){
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

    public function destroy($broker)
    {
      if($this->checkPermission('broker', 'canDelete')){
        try{
          $broker = Broker::findOrFail($broker);
        }catch(ModelNotFoundException $e){
          return response()->json(['message' => 'Broker not found'], 404);
        }
        // $broker->accounts()->delete();
        if($broker->accounts()->count()==0)
          $broker->delete();
        else
          return response()->json(['message' => 'Cannot Delete Selected Broker, Account[s] Exists'], 200);

        return response()->json(['message' => 'success'], 200);
      }else{
        return response()
            ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
      }
    }


    public function destroyMultiple(Request $request)
    {
      if($this->checkPermission('broker', 'canDelete')){
        try{
          $brokers = Broker::whereIn('_id', $request->ids)->get();
        }catch(ModelNotFoundException $e){
          return response()->json(['message' => 'Broker not found'], 404);
        }
        $ids = [];
        $accountExists = false;
        foreach($brokers as $broker){
          if($broker->accounts()->count()==0){
            $ids[] = $broker->_id;
            $broker->accounts()->delete();

          }else
            $accountExists = true;
          // $broker->accounts->delete();
          // $broker->accounts()->destroy();
          // $broker->accounts->destroy();
        }



        Broker::whereIn('_id', $ids)->delete();
        if(!$accountExists)
          return response()->json(['message' => 'success'], 200);
        else
          return response()->json(['message' => 'Some of the Selected Accounts could not be Deleted as there are Multiple Accounts Mapped with Them'], 200);
      }else{
        return response()
            ->json(['message' => 'Unauthorized. You don\'t have permission'], 403);
      }
    }

    public function checkApiCredentials(Request $request)
    {
      // return $request->Input('host');
      $host = $request->Input('host');
      $username = $request->Input('username');
      $password = $request->Input('password');
      // return $host;

      $propReportsClient = new PropReportsApiClient($host);
      $propReportsClient->setCredentials($username, $password);

      // echo 'PropReports version: ' . $propReportsClient->version() . '<br/><br/>';
      $version = $propReportsClient->version();
      $token = $propReportsClient->getToken();
      if($propReportsClient->getLastError()) {
          return response()->json(['message' => $propReportsClient->getLastError()], 201);
      }else{
        if(isset($token)){
          if(strpos($token, 'DOCTYPE') !== false){
            return response()->json(['message' => 'Invalid URL Provided'], 201);
          }elseif($token==''){
            return response()->json(['message' => 'Wrong Credentials'], 201);
          }else{
            return response()->json(['message' => 'success'], 200);
          }
        }else{
          return response()->json(['message' => $version], 201);
        }
      }
    }

    public function getPropReportsAccountsByCredentials(Request $request)
    {
      $host = $request->Input('host');
      $username = $request->Input('username');
      $password = $request->Input('password');

      $propReportsClient = new PropReportsApiClient($host);
      $propReportsClient->setCredentials($username, $password);

      // echo 'PropReports version: ' . $propReportsClient->version() . '<br/><br/>';
      $version = $propReportsClient->version();
      $token = $propReportsClient->getToken();
      if($propReportsClient->getLastError()) {
          return response()->json(['message' => $propReportsClient->getLastError()], 500);
      }else{
        $accounts = $propReportsClient->accounts();

        $accounts = array_map("str_getcsv", explode("\n", $accounts));
        $arr = [];
        $headerArray = $accounts[0];
        unset($accounts[0]);
        $sampleAccountID = null;
        $lastTrDate = null;
        $fills = [];


        foreach ($accounts as $key => $value) {
            if(sizeof($value)==7){
              if($sampleAccountID==null){
                $sampleAccountID = $value[0];
                $lastTrDate = $value[3];
              }
              $arr1 = [
                  'propReportId' => $value[0],
                  'accountid' => $value[1],
                  'firstTraded' => $value[2],
                  'lastTraded' => $value[3],
                  'currency' => $value[4],
                  'cash' => $value[5],
                  'unrealized' => $value[6]
              ];
              $arr[] = $arr1;
            }

        }
        if($sampleAccountID!=null){
          $fills = $propReportsClient->fills(null, $sampleAccountID, Carbon::parse($lastTrDate)->toDateString(), Carbon::parse($lastTrDate)->toDateString());
          $fills = array_map("str_getcsv", explode("\n", $fills));
          $fills2 = $fills[0];
          $fills = [];
          $exceptCols = [
            '_id',
            'date',
            'accountid',
            'account',
            'b/s',
            'date/time',
            'propreports_id',
            'time',
            'type',
            'qty',
            'symbol',
            'price',
            'route',
            'liq',
            'order_id',
            'fill_id',
            'currency',
            'isin/cusip',
            'status'
          ];
          foreach ($fills2 as $key => $value) {
            if(!in_array(str_replace(" ", "_", strtolower($value)), $exceptCols))
              $fills[] = [
                'col' => str_replace(" ", "_", strtolower($value)),
                'name' => $value,
              ];
          }
        }
        $propReportsClient->logout();
        return response()->json([
          'message' => 'success',
          'accounts' => $arr,
          'fills' => $fills,
        ], 200);
      }
    }
}
