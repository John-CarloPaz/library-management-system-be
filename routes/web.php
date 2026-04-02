<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-broadcast', [\App\Http\Controllers\UserController::class, 'testBroadcast']);

Route::get('/foo', function () {
    Artisan::call('storage:link');
});

// Demo helper: simulate overdue borrows for tomorrow's presentation.
// Usage:
//   GET /simulate/overdue-borrows?token=YOUR_TOKEN
Route::get('/simulate/overdue-borrows', [\App\Http\Controllers\DemoSimulationController::class, 'overdueBorrows']);