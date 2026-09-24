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
Route::get('/records/{formType}/{record}/edit', [DataEntryController::class, 'edit'])->name(
    'records.edit',
);
Route::patch('/records/{formType}/{record}', [DataEntryController::class, 'update'])->name(
    'records.update',
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
