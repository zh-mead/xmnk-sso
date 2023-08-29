<?php

use Illuminate\Support\Facades\Route;

$prefix = config('sso.routePrefix', 'base/sso');
Route::group([
    'namespace' => 'ZhMead\XmnkSso\Controllers',
    'prefix' => $prefix
], function () {
    Route::post('/getSsoAuthUrl', 'SsoClientApiController@getSsoAuthUrl');
    Route::post('/doLoginByTicket', 'SsoClientApiController@doLoginByTicket');
    Route::post('/getCurrInfo', 'SsoClientApiController@getCurrInfo');
    Route::post('/logout', 'SsoClientApiController@logout');
    Route::post('/logoutCall', 'SsoClientApiController@logoutCall');
    Route::post('/checkSaTokenLoginId', 'SsoClientApiController@checkSaTokenLoginId');
    Route::post('/updatePW', 'SsoClientApiController@updatePW');
    Route::post('/updateUser', 'SsoClientApiController@updateUser');

    Route::post('/getClientList', 'SsoClientApiController@getClientList');
    Route::post('/getLogin', 'SsoClientApiController@getLogin');
    Route::post('/getAppLogo', 'SsoClientApiController@getAppLogo');
    Route::post('/createTicket', 'SsoClientApiController@createTicket');
    Route::post('/getClientVisit', 'SsoClientApiController@getClientVisit');
    Route::post('/getMainClientUrl', 'SsoClientApiController@getMainClientUrl');
});