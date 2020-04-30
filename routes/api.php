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
//API auth
Route::group(['prefix' => 'v1/auth'], function () {
    Route::group(['middleware' => 'auth:api'], function() {
        Route::get('logout', 'API\AuthController@logout');
        Route::get('user', 'API\AuthController@user');
        Route::resource('compatibility/conveyor/al/guide', 'Compatibility\Conveyor\AmericanLogisticGuideController');
        //Asterisk
        Route::get('compatibility/asterisk/queue/{queue}', 'Compatibility\Asterisk\AsteriskController@queue');
        Route::post('compatibility/asterisk/call/originate', 'Compatibility\Asterisk\AsteriskController@originate');
        Route::post('compatibility/asterisk/call/info', 'Compatibility\Asterisk\AsteriskController@callInfo');
        Route::get('compatibility/asterisk/call/detailed/info/{src}', 'Compatibility\Asterisk\AsteriskController@detailedCallInfo');
    });
});
Route::post('v1/auth/login', 'API\AuthController@login');
// Route::post('v1/auth/signup', 'API\AuthController@signup');

