<?php

use App\Http\Middleware\CheckSuperAdmin;
use Illuminate\Support\Facades\Route;


Route::middleware(\App\Http\Middleware\CheckPublicIP::class)->group(function () {
    Route::post('/users/login', [\App\Http\Controllers\UserController::class, 'login']);
    Route::post('/users/logout', [\App\Http\Controllers\UserController::class, 'logout'])->middleware('auth:sanctum');

    Route::middleware(['auth:sanctum', CheckSuperAdmin::class])->group(function () {
        Route::post('/users/create-admin', [\App\Http\Controllers\UserController::class, 'createAdmin']);
        Route::post('/branches/create', [\App\Http\Controllers\BranchController::class, 'createBranch']);
    });
});

