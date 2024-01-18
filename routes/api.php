<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('/historyjson', 'JsonHistoryDatasController@show');
Route::post('/updatejson', 'JsonHistoryDatasController@update');
Route::post('/showlogconfig', 'LogConfigsController@show');
Route::post('/updatelogconfig', 'LogConfigsController@update');
Route::post('/shownoticelog', 'NoticeLogsController@show');
Route::post('/postnoticelog', 'NoticeLogsController@create');