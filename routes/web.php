<?php

use App\Http\Controllers\AmrRecordController;
use App\Http\Controllers\DataEntryController;
use App\Http\Controllers\PmrRecordController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::get('/records/create', [DataEntryController::class, 'create'])->name(
    'records.create',
);
Route::post('/records', [DataEntryController::class, 'store'])->name(
    'records.store',
);

Route::patch('/records/{formType}/{record}', [DataEntryController::class, 'update'])->name(
    'records.update',
);
Route::delete('/records/{formType}/{record}', [DataEntryController::class, 'destroyTrial'])->name(
    'records.destroy',
);
Route::patch('/piles/{pile}/details', [DataEntryController::class, 'updatePileDetails'])->name(
    'piles.details.update',
);
Route::post('/warehouses', [DataEntryController::class, 'createWarehouse'])->name(
    'warehouses.store',
);
Route::post('/piles', [DataEntryController::class, 'createPile'])->name(
    'piles.store',
);
Route::post('/piles/{pile}/status', [DataEntryController::class, 'updatePileStatus'])->name(
    'piles.status',
);

Route::get('/amr/report', [AmrRecordController::class, 'index'])->name(
    'amr.index',
);
Route::get('/pmr/report', [PmrRecordController::class, 'index'])->name(
    'pmr.index',
);

Route::redirect('/amr', '/amr/report');
Route::redirect('/reports/amr', '/amr/report');
Route::redirect('/pmr', '/pmr/report');
Route::redirect('/reports/pmr', '/pmr/report');
Route::redirect('/arm', '/amr/report');
Route::redirect('/arm/report', '/amr/report');
Route::redirect('/reports/arm', '/amr/report');
