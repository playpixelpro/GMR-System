<?php

use App\Http\Controllers\AmrRecordController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DataCleanupController;
use App\Http\Controllers\DataEntryController;
use App\Http\Controllers\EmrDashboardController;
use App\Http\Controllers\GmrApprovalController;
use App\Http\Controllers\GmrReportConfigController;
use App\Http\Controllers\GmrReportPrintController;
use App\Http\Controllers\GmrSummaryController;
use App\Http\Controllers\MillerController;
use App\Http\Controllers\MillingController;
use App\Http\Controllers\PmrRecordController;
use App\Http\Controllers\ReportColumnSettingController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TestWorkflowController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name(
        'login',
    );
    Route::post('/login', [AuthController::class, 'store'])->name(
        'login.submit',
    );
    Route::post('/login/store', [AuthController::class, 'store'])->name(
        'login.store',
    );

    Route::get('/register', [
        AuthController::class,
        'createRegistration',
    ])->name('register');
    Route::post('/register', [
        AuthController::class,
        'storeRegistration',
    ])->name('register.store');
    Route::get('/register/confirmation', [
        AuthController::class,
        'registerConfirmation',
    ])->name('register.confirmation');

    Route::get('/forgot-password', [
        AuthController::class,
        'requestReset',
    ])->name('password.request');
    Route::post('/forgot-password', [
        AuthController::class,
        'sendReset',
    ])->name('password.email');

    Route::get('/reset-password/{token}', [
        AuthController::class,
        'editReset',
    ])->name('password.reset');
});

Route::match(['POST', 'PUT'], '/reset-password', [
    AuthController::class,
    'updateReset',
])->name('password.update');

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/change-password', [
        AuthController::class,
        'editPassword',
    ])->name('password.change');
    Route::post('/change-password', [
        AuthController::class,
        'updatePassword',
    ])->name('password.change.submit');

    Route::get('/profile', [UserController::class, 'profile'])->name('profile.edit');
    Route::match(['PATCH', 'PUT'], '/profile', [
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
        Route::delete('/users/{user}', [
            UserController::class,
            'destroy',
        ])->name('users.destroy');
        Route::post('/users/{user}/disable', [
            UserController::class,
            'disable',
        ])->name('users.disable');
        Route::post('/users/{user}/reset-password', [
            UserController::class,
            'resetPassword',
        ])->name('users.reset-password');
        Route::post('/users/{user}/resend-temporary-password', [
            UserController::class,
            'resetPassword',
        ])->name('users.resend-temporary-password');
        Route::post('/users/{user}/confirm-registration', [
            UserController::class,
            'confirmRegistration',
        ])->name('users.confirm-registration');
        Route::post('/users/{user}/unlock-edit', [
            UserController::class,
            'unlockEdit',
        ])->name('users.unlock-edit');
        Route::post('/users/{user}/lock-edit', [
            UserController::class,
            'lockEdit',
        ])->name('users.lock-edit');
        Route::post('/users/{user}/reset-edit-mode', [
            UserController::class,
            'resetEditMode',
        ])->name('users.reset-edit-mode');
        Route::patch('/users/{user}/branch', [
            UserController::class,
            'updateBranch',
        ])->name('users.branch');
        Route::patch('/users/{user}/role', [
            UserController::class,
            'updateRole',
        ])->name('users.role');

        Route::get('/settings/blocked-ips', [
            SettingsController::class,
            'blockedIps',
        ])->name('settings.blocked-ips');
        Route::post('/settings/blocked-ips/{ip}/unlock', [
            SettingsController::class,
            'unlockIp',
        ])->name('settings.blocked-ips.unlock');

        Route::get('/settings/activity-logs', [
            SettingsController::class,
            'activityLogs',
        ])->name('settings.activity-logs');
        Route::get('/settings/activity-logs/download', [
            SettingsController::class,
            'downloadActivityLogs',
        ])->name('settings.activity-logs.download');
    });

    // Admin-only data cleanup: delete test data, piles, then warehouses,
    // strictly bottom-up, with an audit entry for every deletion.
    Route::middleware('can:manage-data-cleanup')->group(function (): void {
        Route::get('/settings/data-cleanup', [
            DataCleanupController::class,
            'index',
        ])->name('settings.data-cleanup');
        Route::delete('/settings/data-cleanup/warehouses/{warehouse}', [
            DataCleanupController::class,
            'destroyWarehouse',
        ])->name('settings.data-cleanup.warehouses.destroy');
        Route::delete('/settings/data-cleanup/piles/{pile}', [
            DataCleanupController::class,
            'destroyPile',
        ])->name('settings.data-cleanup.piles.destroy');
        Route::delete('/settings/data-cleanup/piles/{pile}/amr', [
            DataCleanupController::class,
            'destroyAmrData',
        ])->name('settings.data-cleanup.amr.destroy');
        Route::delete('/settings/data-cleanup/piles/{pile}/pmr', [
            DataCleanupController::class,
            'destroyPmrData',
        ])->name('settings.data-cleanup.pmr.destroy');
        Route::delete('/settings/data-cleanup/trials/{formType}/{record}', [
            DataCleanupController::class,
            'destroyTrial',
        ])->name('settings.data-cleanup.trials.destroy');
    });
});

Route::middleware(['auth', 'password.changed'])->group(function (): void {
    Route::get('/', function () {
        if (auth()->user()?->hasRole('VIEWER')) {
            return redirect()->route('amr.index');
        }

        return view('home');
    })->name('home');

    Route::middleware('can:access-data-entry')->group(function (): void {
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
    });

    Route::middleware('can:access-milling')->group(function (): void {
        Route::get('/millers', [MillerController::class, 'index'])->name(
            'millers.index',
        );
        Route::post('/millers', [MillerController::class, 'store'])->name(
            'millers.store',
        );
    });

    Route::middleware('can:manage-millings')->group(function (): void {
        Route::get('/settings/millers', [
            MillerController::class,
            'manage',
        ])->name('settings.millers');
        Route::get('/settings/millers/{miller}/edit', [
            MillerController::class,
            'edit',
        ])->name('settings.millers.edit');
        Route::patch('/settings/millers/{miller}', [
            MillerController::class,
            'update',
        ])->name('settings.millers.update');
        Route::delete('/settings/millers/{miller}', [
            MillerController::class,
            'destroy',
        ])->name('settings.millers.destroy');
    });

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
    Route::get('/gmr/summary/export/excel', [
        GmrSummaryController::class,
        'exportExcel',
    ])->name('gmr.summary.export.excel');
    Route::get('/emr/dashboard/export', [
        EmrDashboardController::class,
        'export',
    ])->name('emr.export')->middleware('can:export-emr-report');
    Route::get('/emr/dashboard/export/excel', [
        EmrDashboardController::class,
        'exportExcel',
    ])->name('emr.export.excel')->middleware('can:export-emr-report');
    Route::get('/emr/dashboard/export/pdf', [
        EmrDashboardController::class,
        'exportPdf',
    ])->name('emr.export.pdf')->middleware('can:export-emr-report');

    // GMR Report Configuration (RMEC / Administrator only)
    Route::get('/gmr/config', [GmrReportConfigController::class, 'edit'])->name('gmr.config.edit');
    Route::put('/gmr/config', [GmrReportConfigController::class, 'update'])->name('gmr.config.update');
    Route::get('/gmr/config/preview', [GmrReportConfigController::class, 'preview'])->name('gmr.config.preview');

    Route::post('/gmr/config/signatories', [GmrReportConfigController::class, 'storeSignatory'])->name('gmr.config.signatories.store');
    Route::put('/gmr/config/signatories/{signatory}', [GmrReportConfigController::class, 'updateSignatory'])->name('gmr.config.signatories.update');
    Route::delete('/gmr/config/signatories/{signatory}', [GmrReportConfigController::class, 'destroySignatory'])->name('gmr.config.signatories.destroy');
    Route::patch('/gmr/config/signatories/{signatory}/toggle', [GmrReportConfigController::class, 'toggleSignatory'])->name('gmr.config.signatories.toggle');

    // Report Column Visibility Settings (RMEC / Administrator)
    Route::get('/settings/reports/columns', [ReportColumnSettingController::class, 'edit'])->name('settings.reports.columns');
    Route::put('/settings/reports/columns', [ReportColumnSettingController::class, 'update'])->name('settings.reports.columns.update');
    Route::post('/settings/reports/columns/reset', [ReportColumnSettingController::class, 'reset'])->name('settings.reports.columns.reset');

    // GMR Report Print & Selection (restricted — Staff cannot print the GMR report)
    Route::match(['get', 'post'], '/gmr/report/print', [GmrReportPrintController::class, 'print'])->name('gmr.report.print')->middleware('can:print-gmr-report');

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
    Route::post('/tests/{formType}/{record}/action', [
        TestWorkflowController::class,
        'action',
    ])->name('tests.action');
    Route::post('/tests/workflow/action', [
        TestWorkflowController::class,
        'applyAction',
    ])->name('tests.workflow.action');
    Route::post('/piles/{pile}/rmec-action', [
        TestWorkflowController::class,
        'pileAction',
    ])->name('piles.rmec-action');
    Route::post('/piles/{pile}/rmec-reset', [
        TestWorkflowController::class,
        'resetAction',
    ])->name('piles.rmec-reset');
    Route::post('/piles/{pile}/retest', [
        TestWorkflowController::class,
        'requestRetest',
    ])->name('piles.workflow.retest')->middleware('can:access-data-entry');

    // Central-Office GMR approval workflow
    Route::get('/gmr-approvals', [GmrApprovalController::class, 'index'])->name('gmr-approvals.index');
    Route::post('/gmr-approvals', [GmrApprovalController::class, 'store'])->name('gmr-approvals.store')->middleware('can:manage-gmr-approvals');
    Route::get('/gmr-approvals/{approval}', [GmrApprovalController::class, 'show'])->name('gmr-approvals.show');
    Route::post('/gmr-approvals/{approval}/approve', [GmrApprovalController::class, 'approve'])->name('gmr-approvals.approve')->middleware('can:manage-gmr-approvals');
    Route::post('/gmr-approvals/{approval}/reject', [GmrApprovalController::class, 'reject'])->name('gmr-approvals.reject')->middleware('can:manage-gmr-approvals');

    // Rice-milling progress monitoring
    Route::middleware('can:access-milling')->group(function (): void {
        Route::get('/millings', [MillingController::class, 'index'])->name('millings.index');
        Route::get('/millings/create', [MillingController::class, 'create'])->name('millings.create')->middleware('can:manage-millings');
        Route::post('/millings', [MillingController::class, 'store'])->name('millings.store')->middleware('can:manage-millings');
        Route::get('/millings/{milling}', [MillingController::class, 'show'])->name('millings.show');
        Route::patch('/millings/{milling}', [MillingController::class, 'update'])->name('millings.update')->middleware('can:manage-millings');
        Route::post('/millings/{milling}/progress', [MillingController::class, 'storeProgress'])->name('millings.progress.store')->middleware('can:record-milling-progress');
        Route::delete('/millings/progress/{progress}', [MillingController::class, 'destroyProgress'])->name('millings.progress.destroy')->middleware('can:manage-millings');
    });
});
