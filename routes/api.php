<?php

use App\Http\Middleware\CheckBranchIp;
use App\Http\Middleware\CheckPublicIP;
use App\Http\Middleware\CheckSuperAdmin;
use Illuminate\Support\Facades\Route;

// Login route (unauthenticated but still checks public IP)
Route::middleware(\App\Http\Middleware\CheckPublicIP::class)->group(function () {
    Route::post('/users/login', [\App\Http\Controllers\UserController::class, 'login']);
});

// Authenticated routes (all require CheckPublicIP and auth:sanctum)
Route::middleware(['auth:sanctum', CheckPublicIP::class, CheckBranchIp::class])->group(function () {
    Route::post('/users/logout', [\App\Http\Controllers\UserController::class, 'logout']);

    // Procurements
    Route::post('/procurements/create', [\App\Http\Controllers\ProcurementController::class, 'createProcurement']);
    Route::put('/procurements/edit/{id}', [\App\Http\Controllers\ProcurementController::class, 'editProcurement']);
    Route::post('/procurements/archive/{id}', [\App\Http\Controllers\ProcurementController::class, 'archiveProcurement']);
    Route::get('/procurements/list', [\App\Http\Controllers\ProcurementController::class, 'getAllActiveProcurements']);
    Route::get('/procurements/view/{id}', [\App\Http\Controllers\ProcurementController::class, 'viewProcurement']);

    // Acquisitions
    Route::post('/acquisitions/create', [\App\Http\Controllers\AcquisitionController::class, 'createAcquisition']);
    Route::put('/acquisitions/edit/{id}', [\App\Http\Controllers\AcquisitionController::class, 'editAcquisition']);
    Route::get('/acquisitions/list', [\App\Http\Controllers\AcquisitionController::class, 'listAcquisitions']);
    Route::get('/acquisitions/view/{id}', [\App\Http\Controllers\AcquisitionController::class, 'viewAcquisition']);
    Route::post('/acquisition/archive/{id}', [\App\Http\Controllers\AcquisitionController::class, 'archiveAcquisition']);

    // Admin and Branch creation (requires CheckSuperAdmin middleware)
    Route::middleware(CheckSuperAdmin::class)->group(function () {
        Route::post('/users/create-admin', [\App\Http\Controllers\UserController::class, 'createAdmin']);
        Route::post('/branches/create', [\App\Http\Controllers\BranchController::class, 'createBranch']);
    });
});
