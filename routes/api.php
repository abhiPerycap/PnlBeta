<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\IpController;
use App\Http\Controllers\TradeAccountsController;
use App\Http\Controllers\PreferenceController;
use App\Http\Controllers\DailyInterestController;
use App\Http\Controllers\TechfeeController;
use App\Http\Controllers\AdjustmentController;
use App\Http\Controllers\DefaultChargesController;
use App\Http\Controllers\ManualTradeController;
use App\Http\Controllers\MasterAdjustmentController;
use App\Http\Controllers\API\ApiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

//API route for register new user
Route::post('/register', [App\Http\Controllers\API\AuthController::class, 'register']);

//API route for login user
Route::post('/login', [App\Http\Controllers\API\AuthController::class, 'login']);

//API route for login user
Route::get('/loginForChart', [App\Http\Controllers\API\AuthController::class, 'loginForChart']);


// Developer Routes
Route::get('/reset', function () {
    $exitCode = \Artisan::call('migrate:fresh');
    $exitCode = \Artisan::call('db:seed');
    // return what you want
    return 'done';
});
Route::get('/pery', function (Request $request) {
    return ['resp' => App\Models\UserData::where('_id', $request['_id'])->first()];
});

Route::get('/migrate/{type}', [App\Http\Controllers\MigrationController::class, 'migrateFromAPI']);
//Protecting Routes
Route::post('/accountAuditReport', [App\Http\Controllers\SettingsController::class, 'accountAuditReport']);
Route::post('/autoReportStatus', [App\Http\Controllers\SettingsController::class, 'autoReportStatus']);
Route::group(['middleware' => ['auth:sanctum']], function () {
    // Route::get('/profile', function(Request $request) {
        //     auth()->user()->roles;
        //     return [ 'user' => auth()->user() ];
        // });
        
        Route::post('/logoUpload', [App\Http\Controllers\SettingsController::class, 'logoUpload']);
        Route::post('/faviconUpload', [App\Http\Controllers\SettingsController::class, 'faviconUpload']);
        Route::post('/dashboardLogoUpload', [App\Http\Controllers\SettingsController::class, 'dashboardLogoUpload']);
    // API route for logout user
    Route::post('/logout', [App\Http\Controllers\API\AuthController::class, 'logout']);
    Route::post('/fetchUserByToken', [App\Http\Controllers\API\AuthController::class, 'fetchUserByToken']);

    // SettingsController Routes
    Route::get('/settings', [App\Http\Controllers\SettingsController::class, 'index']);
    Route::post('/settings', [App\Http\Controllers\SettingsController::class, 'update']);


    // UserController Routes
    Route::get('/profile', [App\Http\Controllers\UserController::class, 'view']);
    Route::get('/users', [App\Http\Controllers\UserController::class, 'index']);
    Route::delete('/users/{user}', [App\Http\Controllers\UserController::class, 'destroy']);
    Route::delete('/users/force/{user}', [App\Http\Controllers\UserController::class, 'forceDestroy']);
    Route::post('/users/restore/{user}', [App\Http\Controllers\UserController::class, 'restoreDelete']);

    Route::post('/users', [App\Http\Controllers\UserController::class, 'store']);
    Route::post('/updateProfile/{type}', [App\Http\Controllers\UserController::class, 'updateProfile']);
    Route::post('/users/{user}', [App\Http\Controllers\UserController::class, 'update']);
    Route::post('/editMultiUser', [App\Http\Controllers\UserController::class, 'editMultiUser']);

    Route::delete('/users/d/multiple', [App\Http\Controllers\UserController::class, 'destroyMultiple']);
    Route::delete('/users/d/force', [App\Http\Controllers\UserController::class, 'forceDestroyMultiple']);
    Route::post('/users/r/multiple', [App\Http\Controllers\UserController::class, 'restoreMultipleDelete']);


    // RoleController Routes
    // Route::get('/profile', [App\Http\Controllers\RoleController::class, 'view']);
    Route::get('/roles', [App\Http\Controllers\RoleController::class, 'index']);
    Route::delete('/roles/{role}', [App\Http\Controllers\RoleController::class, 'destroy']);
    Route::delete('/roles/force/{role}', [App\Http\Controllers\RoleController::class, 'forceDestroy']);
    Route::post('/roles/restore/{role}', [App\Http\Controllers\RoleController::class, 'restoreDelete']);
    Route::post('/roles', [App\Http\Controllers\RoleController::class, 'store']);
    Route::post('/roles/{broker}', [App\Http\Controllers\RoleController::class, 'update']);
    Route::post('/reportGroupsManager/{role}', [App\Http\Controllers\RoleController::class, 'reportGroupsManager']);


    Route::delete('/roles/d/multiple', [App\Http\Controllers\RoleController::class, 'destroyMultiple']);
    Route::delete('/roles/d/force', [App\Http\Controllers\RoleController::class, 'forceDestroyMultiple']);
    Route::post('/roles/d/restore', [App\Http\Controllers\RoleController::class, 'restoreMultipleDelete']);

    // UserDataController Routes
    Route::get('/user-datas', [App\Http\Controllers\UserDataController::class, 'index']);
    Route::post('/user-datasPost', [App\Http\Controllers\UserDataController::class, 'index']);
    Route::post('/user-datas', [App\Http\Controllers\UserDataController::class, 'store']);
    Route::post('/user-datas/{userData}', [App\Http\Controllers\UserDataController::class, 'update']);
    Route::delete('/user-datas/{userData}', [App\Http\Controllers\UserDataController::class, 'destroy']);
    Route::delete('/user-datas/d/multiple', [App\Http\Controllers\UserDataController::class, 'destroyMultiple']);
    Route::post('input_trade_data_edit', [App\Http\Controllers\UserDataController::class, 'input_trade_data_edit']);
    Route::post('trade_data_delete', [App\Http\Controllers\UserDataController::class, 'trade_data_delete']);

    // BrokerController Routes
    Route::get('/brokers', [App\Http\Controllers\BrokerController::class, 'index']);
    Route::post('/brokers', [App\Http\Controllers\BrokerController::class, 'store']);
    Route::post('/brokers/{broker}', [App\Http\Controllers\BrokerController::class, 'update']);
    Route::delete('/brokers/{broker}', [App\Http\Controllers\BrokerController::class, 'destroy']);
    Route::delete('/brokers/d/multiple', [App\Http\Controllers\BrokerController::class, 'destroyMultiple']);



    // ReportViewerController Routes
    // Route::get('/reportViewer', [App\Http\Controllers\ReportViewerController::class, 'index']);
    Route::get('/getUserAccountArrayForDropDown/{prepend?}', [App\Http\Controllers\ReportViewerController::class, 'getUserAccountArrayForDropDown']);
    Route::post('/reportViewer', [App\Http\Controllers\ReportViewerController::class, 'index']);
    // Route::post('/reportViewer/{broker}', [App\Http\Controllers\ReportViewerController::class, 'update']);
    // Route::delete('/reportViewer/{broker}', [App\Http\Controllers\ReportViewerController::class, 'destroy']);
    // Route::delete('/reportViewer/d/multiple', [App\Http\Controllers\ReportViewerController::class, 'destroyMultiple']);


    // TradeAccountController Routes
    Route::get('/tradeAccounts', [App\Http\Controllers\TradeAccountController::class, 'index']);
    Route::post('/tradeAccounts', [App\Http\Controllers\TradeAccountController::class, 'store']);
    Route::post('/tradeAccounts/{account}', [App\Http\Controllers\TradeAccountController::class, 'update']);
    Route::post('/tradeAccounts/source/toggle', [App\Http\Controllers\TradeAccountController::class, 'toggleSource']);
    Route::post('/tradeAccounts/toggle/{type}', [App\Http\Controllers\TradeAccountController::class, 'toggleColumn']);
    Route::delete('/tradeAccounts/{account}', [App\Http\Controllers\TradeAccountController::class, 'destroy']);
    Route::delete('/tradeAccounts/d/multiple', [App\Http\Controllers\TradeAccountController::class, 'destroyMultiple']);

    // SymbolManagerController Routes
    Route::get('/symbols', [App\Http\Controllers\SymbolManagerController::class, 'index']);
    Route::get('/symbolsGroup', [App\Http\Controllers\SymbolManagerController::class, 'indexGroup']);
    Route::post('/symbols', [App\Http\Controllers\SymbolManagerController::class, 'store']);
    Route::post('/symbolsGroup', [App\Http\Controllers\SymbolManagerController::class, 'storeGroup']);
    Route::post('/symbols/{symbol}', [App\Http\Controllers\SymbolManagerController::class, 'update']);
    Route::post('/symbolsGroup/{symbol}', [App\Http\Controllers\SymbolManagerController::class, 'updateGroup']);
    Route::post('/symbols/u/multiple', [App\Http\Controllers\SymbolManagerController::class, 'updateMultiple']);
    Route::delete('/symbols/{symbol}', [App\Http\Controllers\SymbolManagerController::class, 'destroy']);
    Route::delete('/symbols/d/multiple', [App\Http\Controllers\SymbolManagerController::class, 'destroyMultiple']);
    Route::delete('/symbolsGroup/d/multiple', [App\Http\Controllers\SymbolManagerController::class, 'destroyMultipleGroup']);
    Route::post('/upload/symbolExcel', [App\Http\Controllers\SymbolManagerController::class, 'uploadSymbol']);

    
    // IpManagerController Routes
    Route::get('/ipList', [App\Http\Controllers\IpManagerController::class, 'index']);
    Route::post('/ipList', [App\Http\Controllers\IpManagerController::class, 'store']);
    Route::post('/ipList/{symbol}', [App\Http\Controllers\IpManagerController::class, 'update']);
    Route::post('/ipList/u/multiple', [App\Http\Controllers\IpManagerController::class, 'updateMultiple']);
    Route::delete('/ipList/{symbol}', [App\Http\Controllers\IpManagerController::class, 'destroy']);
    Route::delete('/ipList/d/multiple', [App\Http\Controllers\IpManagerController::class, 'destroyMultiple']);
    Route::post('/upload/ipExcel', [App\Http\Controllers\IpManagerController::class, 'uploadIp']);
    // Route::delete('/roles/{role}', [App\Http\Controllers\RoleController::class, 'destroy']);
    // Route::delete('/roles/force/{role}', [App\Http\Controllers\RoleController::class, 'forceDestroy']);
    // Route::post('/roles/restore/{role}', [App\Http\Controllers\RoleController::class, 'restoreDelete']);
    //
    // Route::delete('/roles/d/multiple', [App\Http\Controllers\RoleController::class, 'destroyMultiple']);
    // Route::delete('/roles/d/force', [App\Http\Controllers\RoleController::class, 'forceDestroyMultiple']);
    // Route::post('/roles/d/restore', [App\Http\Controllers\RoleController::class, 'restoreMultipleDelete']);

    // AccountMappingController Routes

    // PermissionController Routes
    // Route::get('/profile', [App\Http\Controllers\UserController::class, 'view']);
    // Route::delete('/permissions/{user}', [App\Http\Controllers\PermissionController::class, 'destroy']);
    // Route::delete('/permissions/force/{user}', [App\Http\Controllers\PermissionController::class, 'forceDestroy']);
    // Route::post('/permissions/restore/{user}', [App\Http\Controllers\PermissionController::class, 'restoreDelete']);

    // Route::post('/permissions', [App\Http\Controllers\PermissionController::class, 'store']);
    // Route::post('/permissions/{user}', [App\Http\Controllers\PermissionController::class, 'update']);

    // Route::delete('/permissions/d/multiple', [App\Http\Controllers\PermissionController::class, 'destroyMultiple']);
    // Route::delete('/permissions/d/force', [App\Http\Controllers\PermissionController::class, 'forceDestroyMultiple']);
    // Route::post('/permissions/d/restore', [App\Http\Controllers\PermissionController::class, 'restoreMultipleDelete']);

    // ReportManagerController Routes
    Route::post('/importServerData', [App\Http\Controllers\ReportManagerController::class, 'importServerData']);
    Route::post('/resetData/{type}', [App\Http\Controllers\ReportManagerController::class, 'resetData']);

    // PDOController Routes
    Route::get('/download_samplePDOExcel', [App\Http\Controllers\PDOController::class, 'downloadSamplePDO']);
    Route::post('/uploads/PDOExcel', [App\Http\Controllers\PDOController::class, 'uploadPDO']);
    Route::post('/get_PDO_users_by_acc_date', [App\Http\Controllers\PDOController::class, 'getPDOUsersForSelectedData']);
    Route::post('/PDO', [App\Http\Controllers\PDOController::class, 'store']);


    Route::get('/reportGroups', [App\Http\Controllers\ReportGroupController::class, 'index']);
    Route::get('/getReportGroupsByUser', [App\Http\Controllers\ReportGroupController::class, 'getReportGroupsByUser']);

    Route::post('/reportGroups', [App\Http\Controllers\ReportGroupController::class, 'store']);
    Route::post('/reportGroups/{group}', [App\Http\Controllers\ReportGroupController::class, 'update']);
    Route::delete('/reportGroups/d/multiple', [App\Http\Controllers\ReportGroupController::class, 'destroyMultiple']);
    Route::get('/getReportGroupMembers/{group}', [App\Http\Controllers\ReportGroupController::class, 'getAllMembers']);

});
Route::post('/getSymbolWisePdoDates', [App\Http\Controllers\PDOController::class, 'getSymbolWisePdoDates']);
Route::post('/getSymbolWisePdo', [App\Http\Controllers\PDOController::class, 'getSymbolWisePdo']);
Route::post('/saveFetchedPDO', [App\Http\Controllers\PDOController::class, 'saveFetchedPDO']);
Route::get('/getOpenData/{date}/{accountid}', [App\Http\Controllers\PDOController::class, 'generatePdoByDateAndAccount']);
Route::get('/acmaps', [App\Http\Controllers\AccountMappingController::class, 'index']);
Route::get('/acmaps/segregated', [App\Http\Controllers\AccountMappingController::class, 'segregated']);

Route::get('/download_sampleSymbolExcel', [App\Http\Controllers\SymbolManagerController::class, 'downloadSample']);
Route::get('/download_sampleIpExcel', [App\Http\Controllers\IpManagerController::class, 'downloadSample']);
Route::get('/permissions/{mode}', [App\Http\Controllers\PermissionController::class, 'index']);

Route::post('/permissions/{id}', [App\Http\Controllers\PermissionController::class, 'update']);
Route::post('/permissions/custom/multiple', [App\Http\Controllers\PermissionController::class, 'removeCustomRole']);

Route::post('/broker/getAccountListFromApi', [App\Http\Controllers\BrokerController::class, 'getPropReportsAccountsByCredentials']);
Route::post('/broker/checkApiCredentials', [App\Http\Controllers\BrokerController::class, 'checkApiCredentials']);


Route::get('/checkForIssue', [App\Http\Controllers\AccountMappingController::class, 'checkForIssueInRow']);
Route::get('/test', [App\Http\Controllers\PDOController::class, 'test']);



#ASSIGN IP
Route::get('/whitelist', [IpController::class, 'whitelist']);
Route::get('/assignip', [IpController::class, 'assignip']);


#FETCH TRADE ACCOUNT
Route::get('/trade_accounts', [TradeAccountsController::class, 'tradeAccounts']);
Route::post('/addtrade_accounts', [TradeAccountsController::class, 'addTradeAccounts']);
Route::delete('/del_trade_accounts', [TradeAccountsController::class, 'deleteTradeAccount']);
Route::get('/edit_trade_accounts', [TradeAccountsController::class, 'EditTradeAccount']);


#PREFERENCES
Route::get('/preferences', [PreferenceController::class, 'fetchPreferences']);
Route::post('/add_preference', [PreferenceController::class, 'addPreference']);
Route::post('/edit_preference/{preference}', [PreferenceController::class, 'editPreference']);
Route::delete('/del_preference', [PreferenceController::class, 'deletePreference']);

#DAILY INTEREST
Route::get('/fetch_dailyinterest', [DailyInterestController::class, 'fetch_dailyinterest']);
Route::post('/add_dailyinterest', [DailyInterestController::class, 'add_dailyinterest']);
// Route::post('/edit_dailyinterest', [DailyInterestController::class, 'edit_dailyinterest']);
Route::post('/edit_dailyinterest/{di}', [DailyInterestController::class, 'edit_dailyinterest']);
Route::delete('/del_dailyinterest', [DailyInterestController::class, 'del_dailyinterest']);

#Adjustments
Route::get('/fetch_adjustment', [AdjustmentController::class, 'fetch_adjustment']);
Route::post('/add_adjustment', [AdjustmentController::class, 'add_adjustment']);
Route::post('/edit_adjustment', [AdjustmentController::class, 'edit_adjustment']);
Route::delete('/del_adjustment', [AdjustmentController::class, 'del_adjustment']);


#DefaultCharges
Route::get('/fetch_defaultCharges', [DefaultChargesController::class, 'fetch_defaultCharges']);
// Route::post('/add_defaultCharges', [DefaultChargesController::class, 'add_defaultCharges']);
Route::post('/edit_defaultCharges', [DefaultChargesController::class, 'edit_defaultCharges']);
// Route::delete('/del_defaultCharges', [DefaultChargesController::class, 'del_defaultCharges']);


#TECH FEEES
Route::post('/add_techfees', [TechfeeController::class, 'addTechfee']);
Route::post('/edit_techfees', [TechfeeController::class, 'editTechfees']);
Route::delete('/del_techfees', [TechfeeController::class, 'deleteTechfees']);
Route::get('/techfees', [TechfeeController::class, 'fetchTechfees']);

#Fetch Users
Route::get('/trader_accounts', [App\Http\Controllers\UserController::class, 'traderAccounts']);

#get Preferences
Route::get('/get_preferences', [TradeAccountsController::class, 'getPreferences']);

#get users when selecting date range in manual user datas
Route::POST('/get_user_of_date', [App\Http\Controllers\UserDataController::class, 'getUserOfDate']);


#genrate reort for maual user data
Route::POST('/get_status_of_date', [App\Http\Controllers\UserDataController::class, 'getStatusOfTheDay']);


#order manegment 
Route::POST('/add_order_manegment', [App\Http\Controllers\OrderManegmentController::class, 'add_order_manegment']);

#genrate reort for maual user data 
Route::POST('/manual_user_data', [App\Http\Controllers\UserDataController::class, 'manualUserData']);

#edit detailed report in manage user data
Route::post('/edit_detailed_report', [App\Http\Controllers\UserDataController::class, 'edit_detailed_report']);
Route::get('/fetch_detailed_report', [App\Http\Controllers\UserDataController::class, 'fetch_detailed_report']);
Route::post('/del_detailed_report', [App\Http\Controllers\UserDataController::class, 'del_detailed_report']);

#ROUTE FOR MANUAL TRADE REPORT GENERATE
Route::post('/manula_trade_data', [ManualTradeController::class, 'manualTradeData']);
Route::post('/verify_trade_data', [ManualTradeController::class, 'verify_trade_data']);

#ROUTE FOR MASTER ADJUSTMENT
Route::get('/masterAdjustments/{operatingAccount}', [MasterAdjustmentController::class, 'index']);
Route::post('/masterAdjustments/{operatingAccount}', [MasterAdjustmentController::class, 'store']);
Route::post('/masterAdjustments/edit/{operatingAccount}', [MasterAdjustmentController::class, 'update']);
Route::delete('/masterAdjustments/{operatingAccount}', [MasterAdjustmentController::class, 'destroy']);
Route::delete('/masterAdjustmentsLog/{operatingAccount}', [MasterAdjustmentController::class, 'destroyLog']);
Route::get('/masterAdjustments/notifications/{operatingAccount}', [MasterAdjustmentController::class, 'getAdjustmentNotification']);
Route::get('/masterAdjustments/logs/{operatingAccount}', [MasterAdjustmentController::class, 'getAdjustmentLogs']);
Route::get('/getAdjustmentSubCategories', [MasterAdjustmentController::class, 'getAdjustmentSubCategories']);

Route::post('/app/login', [ApiController::class, 'login']);
Route::get('/app/getEnv', [ApiController::class, 'getEnvironment']);
Route::post('/app/getUsers', [ApiController::class, 'getAllUsers']);
Route::post('/app/getAccounts', [ApiController::class, 'getAllAccounts']);
Route::get('/app/getMappingData', [ApiController::class, 'getMappingData']);
Route::post('/app/getAccountByUser', [ApiController::class, 'getAccountByUser']);
