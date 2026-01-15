<?php

use App\Http\Controllers\BorrowAnalyticsController;
use App\Http\Controllers\BorrowController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\SemesterController;
use App\Http\Controllers\NotificationController;
use App\Http\Middleware\CheckBranchIp;
use App\Http\Middleware\CheckPublicIP;
use App\Http\Middleware\CheckSuperAdmin;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;


Route::middleware(CheckPublicIP::class)->group(function () {
    Route::post('/users/login', [\App\Http\Controllers\UserController::class, 'login']);
});

// Authenticated routes (all require auth, public IP check, branch IP, and active admin account)
Route::middleware(['auth:sanctum', EnsureUserIsActive::class, CheckBranchIp::class])->group(function () {
    Route::post('/users/logout', [\App\Http\Controllers\UserController::class, 'logout']);

    // Procurements
    Route::post('/procurements/create', [\App\Http\Controllers\ProcurementController::class, 'createProcurement']);
    Route::put('/procurements/edit/{id}', [\App\Http\Controllers\ProcurementController::class, 'editProcurement']);
    Route::post('/procurements/archive/{id}', [\App\Http\Controllers\ProcurementController::class, 'archiveProcurement']);
    Route::get('/procurements/list', [\App\Http\Controllers\ProcurementController::class, 'getAllProcurements']);
    Route::get('/procurements/view/{id}', [\App\Http\Controllers\ProcurementController::class, 'viewProcurement']);
    Route::get('/procurements/restore/{id}', [\App\Http\Controllers\ProcurementController::class, 'restoreProcurement']);

    /// Catalogue
    Route::post('/catalogues/create', [\App\Http\Controllers\CatalogueController::class, 'addCatalogue']);
    Route::post('/catalogues/bulk-create', [\App\Http\Controllers\CatalogueController::class, 'bulkAddFromCsv']);
    Route::put('/catalogues/edit/{id}', [\App\Http\Controllers\CatalogueController::class, 'editCatalogue']);
    Route::get('/catalogues/list', [\App\Http\Controllers\CatalogueController::class, 'listCatalogues']);
    Route::get('/catalogues/view/{id}', [\App\Http\Controllers\CatalogueController::class, 'viewCatalogue']);
    Route::post('/catalogues/archive/{id}', [\App\Http\Controllers\CatalogueController::class, 'archiveCatalogue']);
    Route::get('/catalogues/restore/{id}', [\App\Http\Controllers\CatalogueController::class, 'restoreCatalogue']);
    Route::get('/catalogues/active-list', [\App\Http\Controllers\CatalogueController::class, 'listActiveCatalogues']);

    /// Books
    Route::post('/books/edit-status/{id}', [\App\Http\Controllers\BookController::class, 'editBookStatus']);
    Route::post('/books/archive/{id}', [\App\Http\Controllers\BookController::class, 'archiveBook']);
    Route::get('/books/view/{id}', [\App\Http\Controllers\BookController::class, 'viewBook']);
    Route::get('/books/find/{reference_number}', [\App\Http\Controllers\BookController::class, 'findBookByReferenceId']);
    Route::get('/books/list', [\App\Http\Controllers\BookController::class, 'listBooks']);
    Route::get('/books/restore/{id}', [\App\Http\Controllers\BookController::class, 'restoreBook']);

    ///Borrow Books
    Route::post('/borrow', [BorrowController::class, 'borrowBook']);
    Route::get('/borrows', [BorrowController::class, 'index']);
    Route::put('/borrow/{id}', [BorrowController::class, 'processReturnOrStatus']);
    Route::post('/borrow/extend', [BorrowController::class, 'extendBorrowing']);
    Route::put('archive/borrow/{id}', [BorrowController::class, 'archive']);
    Route::post('/borrow/restore/{id}', [BorrowController::class, 'restore']);
    Route::post('/borrow/pay-fine/{id}', [BorrowController::class, 'payFine']);
    Route::post('/return', [BorrowController::class, 'returnBookDetails']);

    Route::prefix('students')->group(function () {
        Route::post('/create', [StudentController::class, 'createStudent']);
        Route::get('/view/{id}', [StudentController::class, 'getStudentByStudentNumber']);
        Route::put('/edit/{id}', [StudentController::class, 'updateStudent']);
        Route::post('/archive/{id}', [StudentController::class, 'archiveStudent']);
        Route::post('/restore/{id}', [StudentController::class, 'restoreStudent']);
        Route::get('/list', [StudentController::class, 'listStudents']);
    });

    Route::prefix('semesters')->group(function () {
        Route::get('/', [SemesterController::class, 'index']);
        Route::get('/active', [SemesterController::class, 'active']);
        Route::get('/archived', [SemesterController::class, 'archived']);
        Route::post('/', [SemesterController::class, 'store']);
        Route::get('/{id}', [SemesterController::class, 'show']);
        Route::put('/{id}', [SemesterController::class, 'update']);
        Route::delete('/{id}', [SemesterController::class, 'destroy']);
        Route::post('/restore/{id}', [SemesterController::class, 'restore']);
    });

    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/mark-read/{id}', [NotificationController::class, 'markAsRead']);
        Route::post('/mark-read', [NotificationController::class, 'bulkMarkAsRead']);
        Route::post('/announcements', [NotificationController::class, 'createAnnouncement']);
    });

    Route::prefix('analytics/borrows')->group(function () {
        Route::get('/overview', [BorrowAnalyticsController::class, 'overview']);
        Route::get('/top-books', [BorrowAnalyticsController::class, 'mostBorrowedBooks']);
        Route::get('/top-borrowers', [BorrowAnalyticsController::class, 'topBorrowers']);
        Route::get('/trends/{range?}', [BorrowAnalyticsController::class, 'borrowTrends']); // daily | monthly
        Route::get('/average-duration', [BorrowAnalyticsController::class, 'averageBorrowDuration']);
    });

    // Admin chat module (admins only via PermissionService)
    Route::prefix('chat')->group(function () {
        Route::get('/chats', [ChatController::class, 'index']);
        Route::post('/chats', [ChatController::class, 'store']);
        Route::get('/chats/{chatId}/messages', [ChatController::class, 'messages']);
        Route::post('/chats/{chatId}/messages', [ChatController::class, 'sendMessage']);
    });

    Route::get('/branches/list', [\App\Http\Controllers\BranchController::class, 'index']);
    Route::get('/branches/active-list', [\App\Http\Controllers\BranchController::class, 'listActive']);
    Route::get('/branches/view/{id}', [\App\Http\Controllers\BranchController::class, 'show']);

    Route::get('/acquisitions/list', [\App\Http\Controllers\AcquisitionController::class, 'listAcquisitions']);
    Route::get('/acquisitions/view/{id}', [\App\Http\Controllers\AcquisitionController::class, 'viewAcquisition']);

    Route::get('/users/me', [\App\Http\Controllers\UserController::class, 'fetchLoggedInUser']);
    Route::post('/users/edit-me', [\App\Http\Controllers\UserController::class, 'editLoggedInUser']);
    Route::get('/users/list-admins', [\App\Http\Controllers\UserController::class, 'getAllUsers']);

    // Admin and Branch creation (requires CheckSuperAdmin middleware)
    Route::middleware(CheckSuperAdmin::class)->group(function () {
        Route::post('/users/create-admin', [\App\Http\Controllers\UserController::class, 'createAdmin']);
        Route::post('/users/edit-admin/{id}', [\App\Http\Controllers\UserController::class, 'editAdmin']);
        Route::get('/users/view-admin/{id}', [\App\Http\Controllers\UserController::class, 'getUserById']);

        // Branches
        Route::post('/branches/create', [\App\Http\Controllers\BranchController::class, 'createBranch']);
        Route::post('/branches/edit/{id}', [\App\Http\Controllers\BranchController::class, 'editBranch']);
        Route::post('/branches/archive/{id}', [\App\Http\Controllers\BranchController::class, 'archiveBranch']);
        Route::post('/branches/restore/{id}', [\App\Http\Controllers\BranchController::class, 'restoreBranch']);

        // Acquisitions
        Route::post('/acquisitions/create', [\App\Http\Controllers\AcquisitionController::class, 'createAcquisition']);
        Route::post('/acquisitions/bulk-create', [\App\Http\Controllers\AcquisitionController::class, 'bulkCreateFromCsv']);
        Route::put('/acquisitions/edit/{id}', [\App\Http\Controllers\AcquisitionController::class, 'editAcquisition']);
        Route::post('/acquisition/archive/{id}', [\App\Http\Controllers\AcquisitionController::class, 'archiveAcquisition']);
        Route::get('/acquisitions/restore/{id}', [\App\Http\Controllers\AcquisitionController::class, 'restoreAcquisition']);
    });
});
