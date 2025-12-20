<?php

use App\Http\Controllers\BorrowAnalyticsController;
use App\Http\Controllers\BorrowController;
use App\Http\Controllers\StudentController;
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
    Route::get('/procurements/list', [\App\Http\Controllers\ProcurementController::class, 'getAllProcurements']);
    Route::get('/procurements/view/{id}', [\App\Http\Controllers\ProcurementController::class, 'viewProcurement']);
    Route::get('/procurements/archived-list', [\App\Http\Controllers\ProcurementController::class, 'getAllArchivedProcurements']);
    Route::get('/procurements/active-list', [\App\Http\Controllers\ProcurementController::class, 'getAllActiveProcurements']);
    Route::get('/procurements/restore/{id}', [\App\Http\Controllers\ProcurementController::class, 'restoreProcurement']);

    /// Catalogue
    Route::post('/catalogues/create', [\App\Http\Controllers\CatalogueController::class, 'addCatalogue']);
    Route::put('/catalogues/edit/{id}', [\App\Http\Controllers\CatalogueController::class, 'editCatalogue']);
    Route::get('/catalogues/list', [\App\Http\Controllers\CatalogueController::class, 'listCatalogues']);
    Route::get('/catalogues/view/{id}', [\App\Http\Controllers\CatalogueController::class, 'viewCatalogue']);
    Route::post('/catalogues/archive/{id}', [\App\Http\Controllers\CatalogueController::class, 'archiveCatalogue']);
    Route::get('/catalogues/archived-list', [\App\Http\Controllers\CatalogueController::class, 'listArchivedCatalogues']);
    Route::get('/catalogues/restore/{id}', [\App\Http\Controllers\CatalogueController::class, 'restoreCatalogue']);
    Route::get('/catalogues/active-list', [\App\Http\Controllers\CatalogueController::class, 'listActiveCatalogues']);

    /// Books
    Route::post('/books/edit-status/{id}', [\App\Http\Controllers\BookController::class, 'editBookStatus']);
    Route::post('/books/archive/{id}', [\App\Http\Controllers\BookController::class, 'archiveBook']);
    Route::get('/books/view/{id}', [\App\Http\Controllers\BookController::class, 'viewBook']);
    Route::get('/books/list', [\App\Http\Controllers\BookController::class, 'listBooks']);
    Route::get('/books/archived-list', [\App\Http\Controllers\BookController::class, 'listArchivedBooks']);
    Route::get('/books/restore/{id}', [\App\Http\Controllers\BookController::class, 'restoreBook']);

    ///Borrow Books
    Route::post('/borrow', [BorrowController::class, 'borrowBook']);
    Route::get('/borrows', [BorrowController::class, 'index']);
    Route::put('/borrow/{id}', []);
    Route::post('/borrow/extend/{id}', [BorrowController::class, 'extendBorrowing']);
    Route::put('archive/borrow/{id}', [BorrowController::class, 'archive']);
    Route::post ('/borrow/restore/{id}', [BorrowController::class, 'restore']);

    Route::prefix('students')->group(function () {
        Route::post('/create', [StudentController::class, 'createStudent']);
        Route::get('/view/{id}', [StudentController::class, 'getStudentByStudentNumber']);
        Route::put('/edit/{id}', [StudentController::class, 'updateStudent']);
        Route::post('/archive/{id}', [StudentController::class, 'archiveStudent']);
        Route::post('/restore/{id}', [StudentController::class, 'restoreStudent']);
        Route::get('/status/{status}', [StudentController::class, 'getStudentsByStatus']);
        Route::get('/archived', [StudentController::class, 'getArchivedStudents']);
        Route::get('/students', [StudentController::class, 'listStudentUnarchived']);
    });

    Route::prefix('analytics/borrows')->group(function () {
        Route::get('/overview', [BorrowAnalyticsController::class, 'overview']);
        Route::get('/top-books', [BorrowAnalyticsController::class, 'mostBorrowedBooks']);
        Route::get('/top-borrowers', [BorrowAnalyticsController::class, 'topBorrowers']);
        Route::get('/trends/{range?}', [BorrowAnalyticsController::class, 'borrowTrends']); // daily | monthly
        Route::get('/average-duration', [BorrowAnalyticsController::class, 'averageBorrowDuration']);
    });

    // Admin and Branch creation (requires CheckSuperAdmin middleware)
    Route::middleware(CheckSuperAdmin::class)->group(function () {
        Route::post('/users/create-admin', [\App\Http\Controllers\UserController::class, 'createAdmin']);
        Route::post('/users/edit-admin/{id}', [\App\Http\Controllers\UserController::class, 'editAdmin']);
        Route::get('/users/list-admins', [\App\Http\Controllers\UserController::class, 'getAllUsers']);
        Route::get('/users/view-admin/{id}', [\App\Http\Controllers\UserController::class, 'getUserById']);

        // Branches
        Route::post('/branches/create', [\App\Http\Controllers\BranchController::class, 'createBranch']);
        Route::post('/branches/edit/{id}', [\App\Http\Controllers\BranchController::class, 'editBranch']);
        Route::get('/branches/list', [\App\Http\Controllers\BranchController::class, 'index']);
        Route::get('/branches/view/{id}', [\App\Http\Controllers\BranchController::class, 'show']);
        Route::post('/branches/archive/{id}', [\App\Http\Controllers\BranchController::class, 'archiveBranch']);
        Route::post('/branches/restore/{id}', [\App\Http\Controllers\BranchController::class, 'restoreBranch']);

        // Acquisitions
        Route::post('/acquisitions/create', [\App\Http\Controllers\AcquisitionController::class, 'createAcquisition']);
        Route::put('/acquisitions/edit/{id}', [\App\Http\Controllers\AcquisitionController::class, 'editAcquisition']);
        Route::get('/acquisitions/list', [\App\Http\Controllers\AcquisitionController::class, 'listAcquisitions']);
        Route::get('/acquisitions/view/{id}', [\App\Http\Controllers\AcquisitionController::class, 'viewAcquisition']);
        Route::post('/acquisition/archive/{id}', [\App\Http\Controllers\AcquisitionController::class, 'archiveAcquisition']);
        Route::get('/acquisitions/archived-list', [\App\Http\Controllers\AcquisitionController::class, 'listArchivedAcquisitions']);
        Route::get('/acquisitions/restore/{id}', [\App\Http\Controllers\AcquisitionController::class, 'restoreAcquisition']);
        Route::get('/acquisitions/active-list', [\App\Http\Controllers\AcquisitionController::class, 'listActiveAcquisitions']);
    });
});
