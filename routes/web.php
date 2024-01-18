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

Route::get('/', 'JsonHistoryDatasController@index');

Route::post('/historyjson', 'JsonHistoryDatasController@show');
Route::post('/updatejson', 'JsonHistoryDatasController@update');
Route::post('/showlogconfig', 'LogConfigsController@show');
Route::post('/updatelogconfig', 'LogConfigsController@update');
Route::post('/shownoticelog', 'NoticeLogsController@show');
Route::post('/postnoticelog', 'NoticeLogsController@create');