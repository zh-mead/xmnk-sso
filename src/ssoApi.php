<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'namespace' => 'ZhMead\XmnkSso\Controllers',
    'prefix' => 'base/sso'
], function () {
    Route::post('/getSsoAuthUrl', 'SsoClientApiController@getSsoAuthUrl');
    Route::post('/doLoginByTicket', 'SsoClientApiController@doLoginByTicket');
    Route::post('/getCurrInfo', 'SsoClientApiController@getCurrInfo');
    Route::post('/logout', 'SsoClientApiController@logout');
    Route::post('/logoutCall', 'SsoClientApiController@logoutCall');
    Route::post('/checkSaTokenLoginId', 'SsoClientApiController@checkSaTokenLoginId');
    Route::post('/updatePW', 'SsoClientApiController@updatePW');
    Route::post('/updateUser', 'SsoClientApiController@updateUser');
});