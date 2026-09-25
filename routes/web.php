<?php

use App\Http\Controllers\AmrRecordController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DataEntryController;
use App\Http\Controllers\EmrDashboardController;
use App\Http\Controllers\GmrSummaryController;
use App\Http\Controllers\PmrRecordController;
use App\Http\Controllers\TestWorkflowController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'create'])->name('login');
Route::post('/login', [AuthController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('login.store');
Route::post('/logout', [AuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');
Route::get('/password/forgot', [AuthController::class, 'requestReset'])->name(
    'password.request',
);
Route::post('/password/email', [AuthController::class, 'sendReset'])
    ->middleware('throttle:6,1')
    ->name('password.email');
Route::get('/password/reset/{token}', [
    AuthController::class,
    'editReset',
])->name('password.reset');
Route::post('/password/reset', [AuthController::class, 'updateReset'])->name(
    'password.reset.update',
);
Route::middleware(['auth', 'password.changed'])->group(function (): void {
    Route::get('/password/change', [
        AuthController::class,
        'editPassword',
    ])->name('password.change');
    Route::put('/password/change', [
        AuthController::class,
        'updatePassword',
    ])->name('password.update');
    Route::post('/tests/{formType}/{record}/action', [
        TestWorkflowController::class,
        'action',
    ])->name('tests.action');
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->name(
        'users.store',
    );
    Route::post('/users/{user}/disable', [
        UserController::class,
        'disable',
    ])->name('users.disable');
});

Route::middleware(['auth', 'password.changed'])->group(function (): void {
    Route::get('/', function () {
        return view('home');
    })->name('home');

    Route::get('/records/create', [DataEntryController::class, 'create'])->name(
        'records.create',
    );
    Route::post('/records', [DataEntryController::class, 'store'])->name(
        'records.store',
    );

    Route::patch('/records/{formType}/{record}', [
        DataEntryController::class,
        'update',
    ])->name('records.update');
    Route::delete('/records/{formType}/{record}', [
        DataEntryController::class,
        'destroyTrial',
    ])->name('records.destroy');
    Route::patch('/piles/{pile}/details', [
        DataEntryController::class,
        'updatePileDetails',
    ])->name('piles.details.update');
    Route::post('/warehouses', [
        DataEntryController::class,
        'createWarehouse',
    ])->name('warehouses.store');
    Route::post('/piles', [DataEntryController::class, 'createPile'])->name(
        'piles.store',
    );
    Route::post('/piles/{pile}/status', [
        DataEntryController::class,
        'updatePileStatus',
    ])->name('piles.status');

    Route::get('/amr/report', [AmrRecordController::class, 'index'])->name(
        'amr.index',
    );
    Route::get('/pmr/report', [PmrRecordController::class, 'index'])->name(
        'pmr.index',
    );

    Route::get('/emr/dashboard', [
        EmrDashboardController::class,
        'index',
    ])->name('emr.index');
    Route::get('/gmr/summary', [GmrSummaryController::class, 'index'])->name(
        'gmr.summary',
    );
    Route::get('/emr/dashboard/export', [
        EmrDashboardController::class,
        'export',
    ])->name('emr.export');

    Route::redirect('/amr', '/amr/report');
    Route::redirect('/reports/amr', '/amr/report');
    Route::redirect('/pmr', '/pmr/report');
    Route::redirect('/reports/pmr', '/pmr/report');
    Route::redirect('/arm', '/amr/report');
    Route::redirect('/arm/report', '/amr/report');
    Route::redirect('/reports/arm', '/amr/report');
});
