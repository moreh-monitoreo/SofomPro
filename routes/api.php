<?php

use App\Http\Controllers\AuthSiteController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('simulator', [AuthSiteController::class, 'simulate'])->middleware('throttle:60,1');
Route::get('simulator/config', [AuthSiteController::class, 'simulatorConfig'])->middleware('throttle:60,1');
