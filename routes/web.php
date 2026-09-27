<?php

use App\Http\Controllers\AmrRecordController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DataEntryController;
use App\Http\Controllers\EmrDashboardController;
use App\Http\Controllers\GmrReportConfigController;
use App\Http\Controllers\GmrReportPrintController;
use App\Http\Controllers\GmrSummaryController;
use App\Http\Controllers\PmrRecordController;
use App\Http\Controllers\TestWorkflowController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name(
        'login',
    );
    Route::post('/login', [AuthController::class, 'login'])->name(
        'login.submit',
    );

    Route::get('/forgot-password', [
        AuthController::class,
        'showForgotPasswordForm',
    ])->name('password.request');
    Route::post('/forgot-password', [
        AuthController::class,
        'sendResetLinkEmail',
    ])->name('password.email');

    Route::get('/reset-password/{token}', [
        AuthController::class,
        'showResetPasswordForm',
    ])->name('password.reset');
    Route::post('/reset-password', [
        AuthController::class,
        'resetPassword',
    ])->name('password.update');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/change-password', [
        AuthController::class,
        'showChangePasswordForm',
    ])->name('password.change');
    Route::post('/change-password', [
        AuthController::class,
        'changePassword',
    ])->name('password.change.submit');

    Route::get('/profile', [UserController::class, 'profile'])->name('profile.edit');
    Route::patch('/profile', [
        UserController::class,
        'updateProfile',
    ])->name('profile.update');
    Route::put('/profile/password', [
        UserController::class,
        'updatePassword',
    ])->name('profile.password');

    Route::middleware('can:manage-users')->group(function (): void {
        Route::get('/users', [UserController::class, 'index'])->name(
            'users.index',
        );
        Route::post('/users', [UserController::class, 'store'])->name(
            'users.store',
        );
        Route::patch('/users/{user}/role', [
            UserController::class,
            'updateRole',
        ])->name('users.update-role');
        Route::patch('/users/{user}/status', [
            UserController::class,
            'updateStatus',
        ])->name('users.update-status');
        Route::delete('/users/{user}', [
            UserController::class,
            'destroy',
        ])->name('users.destroy');
        Route::post('/users/{user}/generate-temp-password', [
            UserController::class,
            'generateTemporaryPassword',
        ])->name('users.generate-temp-password');
        Route::patch('/users/{user}/edit-mode', [
            UserController::class,
            'updateEditMode',
        ])->name('users.update-edit-mode');
    });
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
        'destroy',
    ])->name('records.destroy');
    Route::patch('/piles/{pile}/details', [
        DataEntryController::class,
        'updatePileDetails',
    ])->name('piles.details.update');

    Route::get('/branches/{branch}/warehouses', [
        DataEntryController::class,
        'warehouses',
    ])->name('branches.warehouses');
    Route::get('/warehouses/{warehouse}/piles', [
        DataEntryController::class,
        'piles',
    ])->name('warehouses.piles');

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
    Route::get('/amr/export/excel', [
        AmrRecordController::class,
        'exportExcel',
    ])->name('amr.export.excel');
    Route::get('/amr/export/pdf', [
        AmrRecordController::class,
        'exportPdf',
    ])->name('amr.export.pdf');

    Route::get('/pmr/report', [PmrRecordController::class, 'index'])->name(
        'pmr.index',
    );
    Route::get('/pmr/export/excel', [
        PmrRecordController::class,
        'exportExcel',
    ])->name('pmr.export.excel');
    Route::get('/pmr/export/pdf', [
        PmrRecordController::class,
        'exportPdf',
    ])->name('pmr.export.pdf');

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

    // GMR Report Configuration (RMEC / Administrator only)
    Route::get('/gmr/config', [GmrReportConfigController::class, 'edit'])->name('gmr.config.edit');
    Route::put('/gmr/config', [GmrReportConfigController::class, 'update'])->name('gmr.config.update');
    Route::get('/gmr/config/preview', [GmrReportConfigController::class, 'preview'])->name('gmr.config.preview');

    Route::post('/gmr/config/signatories', [GmrReportConfigController::class, 'storeSignatory'])->name('gmr.config.signatories.store');
    Route::put('/gmr/config/signatories/{signatory}', [GmrReportConfigController::class, 'updateSignatory'])->name('gmr.config.signatories.update');
    Route::delete('/gmr/config/signatories/{signatory}', [GmrReportConfigController::class, 'destroySignatory'])->name('gmr.config.signatories.destroy');
    Route::patch('/gmr/config/signatories/{signatory}/toggle', [GmrReportConfigController::class, 'toggleSignatory'])->name('gmr.config.signatories.toggle');

    // GMR Report Print & Selection (ARMIC / general report print)
    Route::match(['get', 'post'], '/gmr/report/print', [GmrReportPrintController::class, 'print'])->name('gmr.report.print');

    Route::redirect('/amr', '/amr/report');
    Route::redirect('/reports/amr', '/amr/report');
    Route::redirect('/pmr', '/pmr/report');
    Route::redirect('/reports/pmr', '/pmr/report');
    Route::redirect('/arm', '/amr/report');
    Route::redirect('/arm/report', '/amr/report');
    Route::redirect('/reports/arm', '/amr/report');
    Route::redirect('/gmr', '/gmr/summary');
    Route::redirect('/gmr/report', '/gmr/summary');

    // Workflow transition actions
    Route::post('/tests/workflow/action', [
        TestWorkflowController::class,
        'applyAction',
    ])->name('tests.workflow.action');
    Route::post('/piles/{pile}/retest', [
        TestWorkflowController::class,
        'requestRetest',
    ])->name('piles.workflow.retest');
});
