<?php

namespace App\Http\Controllers;
use App\Models\Masteradjustment;
use App\Models\Adjustmentlog;
use App\Models\Areport;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;


class MasterAdjustmentController extends Controller
{

    function index($operatingAccount){//fetchCurrentAdjustment
        $operatingAccount = $this->formatOperatingAccount($operatingAccount);
        $rawData = Masteradjustment::where('effectiveFor', $operatingAccount)->get();
        return response()->json(['message'=> 'success', 'data' => $rawData], 200);
    }

    function store(Request $request, $operatingAccount){//addActionAdjustment
        $operatingAccount = $this->formatOperatingAccount($operatingAccount);
        $request['effectiveFor'] = $operatingAccount;
        $request['effectiveFrom'] = Carbon::parse($request->effectiveFrom)->toDateString();
        // $request['effectiveFrom'] = date('d-M-Y', strtotime("1 day", strtotime($request->effectiveFrom)));
        Masteradjustment::create($request->all());
        

        // if(Adjustmentlog::where('effectiveFor', $operatingAccount)->where('category', $request->category)->where('effectiveFrom', $request->effectiveFrom)->exists()){
        //     $adjustment = Adjustmentlog::where('effectiveFor', $operatingAccount)->where('category', $request->category)->where('effectiveFrom', $request->effectiveFrom)->first();
        //     foreach($request->all() as $key => $value){
        //         $adjustment->{$key} = $value;
        //     }$adjustment->update();
        // }else 
            Adjustmentlog::create($request->all());
           
        return response()->json(['message'=>'success'], 200);

    }

    function update(Request $request, $operatingAccount){//editCurrentAdjustment
        $operatingAccount = $this->formatOperatingAccount($operatingAccount);
        $request['effectiveFor'] = $operatingAccount;
        $request['effectiveFrom'] = Carbon::parse($request->effectiveFrom)->toDateString();
        $adjustment = Masteradjustment::where('effectiveFor', $operatingAccount)->where('_id',$request->_id)->first();
        
        $tempCategory = $adjustment['category'];
        
        foreach($request->all() as $key => $value){
            $adjustment->{$key} = $value;
        }$adjustment->update();

        if(Adjustmentlog::where('effectiveFor', $operatingAccount)->where('category', $tempCategory)->where('effectiveFrom', $request->effectiveFrom)->exists()){
            $adjustment = Adjustmentlog::where('effectiveFor', $operatingAccount)->where('category', $tempCategory)->where('effectiveFrom', $request->effectiveFrom)->first();
            foreach($request->all() as $key => $value){
                if($key!='_id')
                    $adjustment->{$key} = $value;
            }$adjustment->update();
        }else 
            Adjustmentlog::create($request->all());
           
        return response()->json(['message'=>'success'], 200);       
    }

    public function destroy(Request $request, $operatingAccount){//deleteCurrentAdjustment
        $operatingAccount = $this->formatOperatingAccount($operatingAccount);
        Masteradjustment::where('effectiveFor', $operatingAccount)->whereIn('_id', $request->ids)->delete();
        return response()->json(["message"=>"success"], 200);
    }

    public function destroyLog(Request $request, $operatingAccount){//deleteCurrentAdjustment
        $operatingAccount = $this->formatOperatingAccount($operatingAccount);
        Adjustmentlog::where('effectiveFor', $operatingAccount)->whereIn('_id', $request->ids)->delete();
        return response()->json(["message"=>"success"], 200);
    }

    public function formatOperatingAccount($operatingAccount)
    {
        // $operatingAccount = $this->formatOperatingAccount($operatingAccount);
        if(strpos($operatingAccount, 'acc_') !== false){
            return explode('_', $operatingAccount)[1];
        }else{
            return $operatingAccount;
            // return User::find($operatingAccount)->getAccountId();
        }
    }

    function getAdjustmentNotification($operatingAccount){//fetchActionAdjustment
        $isUser = false;
        if(strpos($operatingAccount, 'acc_') !== false){
            $operatingAccount = $this->formatOperatingAccount($operatingAccount);
            $adjCategories = array_values(array_unique(Areport::where('accountid', $operatingAccount)->get()->pluck('category')->toArray()));
            $masterAdjCategories = array_values(array_unique(Masteradjustment::where('effectiveFor', $operatingAccount)->get()->pluck('fullCategory')->toArray()));
            
        }else{
            $isUser = true;
            $userMappedTo = User::find($operatingAccount)->getAccountIds();

            // $operatingAccount = $this->formatOperatingAccount($operatingAccount);
            $adjCategories = array_values(array_unique(Areport::whereIn('accountid', $userMappedTo)->get()->pluck('category')->toArray()));
            $masterAdjCategories = array_values(array_unique(Masteradjustment::where('effectiveFor', $operatingAccount)->get()->pluck('fullCategory')->toArray()));
            
        }

        $difference = array_diff($adjCategories, $masterAdjCategories);
        // return $difference;
        $newCategories = [];
        foreach ($difference as $key => $value) {
            array_push($newCategories, [
                'fullCategory'=> $value,
                'category'=> explode(': ', $value)[0],
                'toBeShownAs'=> explode(': ', $value)[1],
                'comment'=> $value,
                'source' => $operatingAccount,
                'parent' => Areport::whereIn('accountid', (!$isUser)?[$operatingAccount]:$userMappedTo)->where('category', $value)->oldest('date')->first()['accountid']??'',
                'effectiveFor' => $operatingAccount,
                'effectiveFrom' => Areport::whereIn('accountid', (!$isUser)?[$operatingAccount]:$userMappedTo)->where('category', $value)->oldest('date')->first()['date']??Carbon::now()->toDateString(),
            ]);
        }
        return response()->json(['message'=> 'success', 'data' => $newCategories], 200);
    }

    function getAdjustmentLogs($operatingAccount){//fetchAdjustmentLog
        $operatingAccount = $this->formatOperatingAccount($operatingAccount);
        $rawData = Adjustmentlog::where('effectiveFor', $operatingAccount)->get();
        return response()->json(['message'=> 'success', 'data' => $rawData]);
    }

    public function getAdjustmentSubCategories(Request $request)
    {
        $logFeeCategories = Adjustmentlog::where('category', 'Fee')->where('source', 'manual')->get()->pluck('toBeShownAs')->toArray();
        $logTransferCategories = Adjustmentlog::where('category', 'Transfer')->where('source', 'manual')->get()->pluck('toBeShownAs')->toArray();
        $logFeeCategories = array_unique($logFeeCategories);
        $logTransferCategories = array_unique($logTransferCategories);
        $tmp1 = [];
        foreach ($logFeeCategories as $key => $value) {
            $tmp1[] = [
                'id'=> $value,
                'text'=> $value,
            ];
        }
        $logFeeCategories = $tmp1;
        $tmp1 = [];
        foreach ($logTransferCategories as $key => $value) {
            $tmp1[] = [
                'id'=> $value,
                'text'=> $value,
            ];
        }
        $logTransferCategories = $tmp1;
        $tmp1 = [];
        
        return [
            'message'=>'success',
            'data'=> [
                'Fee'=>$logFeeCategories,
                'Transfer'=>$logTransferCategories,

            ]
        ];

    }
}
