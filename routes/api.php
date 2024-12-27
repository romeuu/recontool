<?php

use App\Http\Controllers\MonitorHostsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/hosts', [MonitorHostsController::class, 'index']);