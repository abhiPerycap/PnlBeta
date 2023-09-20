<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Route::get('/{returnUrl}', function () {
//     // return \App\Models\Acmap::all()[0];
//     return view('index');
//     return view('index')->with('returnUrl', $returnUrl);
// });

// Route::get('/', function () {
//     // return \App\Models\Acmap::all()[0];
//     return view('index');
// });

// Route::get('/sign/in', function () {
//     // return \App\Models\Acmap::all()[0];
//     return view('index');
// });

Route::get('/{all}',function(){
    return view('index');
})->where('all','^(?!api|gate|chartApplication|chartApp|checkForIssue|multiAccMapperAction|logoUpload|treeMapper|test).*$');
Route::get('/chartApplication',function(){
    // return 'fg';
    return view('chartApp');
});
Route::post('/logoUpload', [App\Http\Controllers\SettingsController::class, 'logoUpload']);
Route::get('/gate/{userId}', [App\Http\Controllers\AccountMappingController::class, 'gate']);
Route::post('/gate/{userId}', [App\Http\Controllers\AccountMappingController::class, 'gate']);
Route::get('checkForIssue', [App\Http\Controllers\AccountMappingController::class, 'checkForIssueInRow']);
Route::get('multiAccMapperAction', [App\Http\Controllers\AccountMappingController::class, 'multiAccMapperAction']);
Route::post('treeMapper', [App\Http\Controllers\AccountMappingController::class, 'treeMapper']);

Route::get('test', [App\Http\Controllers\TradeAccountController::class, 'test']);


