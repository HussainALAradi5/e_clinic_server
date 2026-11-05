<?php

use App\Http\Controllers\Pharmacy\DispensingController;
use App\Http\Controllers\Pharmacy\InventoryController;
use App\Http\Controllers\Pharmacy\MedicationController;
use App\Http\Controllers\Pharmacy\PrescriptionController;
use App\Http\Middleware\EnsureIdempotency;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Pharmacy API (prefix /pharmacy) — medications, prescriptions, dispensing, inventory
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function (): void {

    Route::prefix('pharmacy')->name('pharmacy.')->group(function (): void {

        Route::prefix('medications')->name('medications.')->controller(MedicationController::class)->group(function (): void {
            Route::middleware(['access:medications.read_all|medications.read_own', 'throttle:read'])->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::get('low-stock', 'lowStock')->name('low-stock');
                Route::get('barcode/{gtin}', 'byBarcode')->where('gtin', '[0-9]{8,14}')->name('barcode');
                Route::get('{medication}', 'show')->whereUuid('medication')->name('show');
            });
            Route::middleware('throttle:write')->group(function (): void {
                Route::post('/', 'store')->middleware('access:medications.create')->name('store');
                Route::put('{medication}', 'update')->whereUuid('medication')->middleware('access:medications.write')->name('update');
                Route::patch('{medication}/archive', 'archive')->whereUuid('medication')->middleware('access:medications.write')->name('archive');
                Route::patch('{medication}/unarchive', 'unarchive')->whereUuid('medication')->middleware('access:medications.write')->name('unarchive');
                Route::delete('{medication}', 'destroy')->whereUuid('medication')->middleware('access:medications.delete')->name('destroy');
            });
            Route::patch('{medication}/restore', 'restore')->whereUuid('medication')->middleware(['access:medications.delete', 'throttle:sensitive'])->name('restore');
        });
        Route::bulkActions('medications', 'medications')->middleware('access:medications.write|medications.delete');
        Route::dataTransfer('medications', 'medications');

        Route::prefix('prescriptions')->name('prescriptions.')->controller(PrescriptionController::class)->group(function (): void {
            Route::middleware(['access:prescriptions.read_all|prescriptions.read_own', 'throttle:read'])->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::get('queue', 'queue')->middleware('access:dispensing.dispense')->name('queue');
                Route::get('{prescription}', 'show')->whereUuid('prescription')->name('show');
            });
            Route::middleware('throttle:write')->group(function (): void {
                Route::post('/', 'store')->middleware('access:prescriptions.create')->name('store');
                Route::put('{prescription}', 'update')->whereUuid('prescription')->middleware('access:prescriptions.write')->name('update');
                Route::patch('{prescription}/cancel', 'cancel')->whereUuid('prescription')->middleware('access:prescriptions.write')->name('cancel');
            });
        });

        Route::prefix('dispensations')->name('dispensations.')->controller(DispensingController::class)->group(function (): void {
            Route::middleware(['access:dispensing.read_all|dispensing.read_own', 'throttle:read'])->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::get('{dispensation}', 'show')->whereUuid('dispensation')->name('show');
            });
            Route::middleware('throttle:sensitive')->group(function (): void {
                Route::post('/', 'store')->middleware(['access:dispensing.create&dispensing.dispense', EnsureIdempotency::ALIAS])->name('store');
                Route::patch('{dispensation}/cancel', 'cancel')->whereUuid('dispensation')->middleware('access:dispensing.write&dispensing.dispense')->name('cancel');
            });
        });
        Route::get('colleagues', [DispensingController::class, 'colleagues'])
            ->middleware(['access:dispensing.dispense|dispensing.administer|inventory.adjust', 'throttle:read'])->name('colleagues');
        Route::dataTransfer('dispensations', 'dispensing');

        Route::prefix('inventory')->name('inventory.')->controller(InventoryController::class)->group(function (): void {
            Route::middleware(['access:inventory.read_all|inventory.read_own', 'throttle:read'])->group(function (): void {
                Route::get('stock', 'stock')->name('stock');
                Route::get('movements', 'movements')->name('movements');
                Route::get('batches/{batch}', 'show')->whereUuid('batch')->name('batches.show');
            });
            Route::post('receipts', 'receive')->middleware(['access:inventory.create&inventory.receive', 'throttle:write', EnsureIdempotency::ALIAS])->name('receive');
            Route::middleware('throttle:sensitive')->group(function (): void {
                Route::post('batches/{batch}/adjust', 'adjust')->whereUuid('batch')->middleware('access:inventory.write&inventory.adjust')->name('adjust');
                Route::post('batches/{batch}/transfer', 'transfer')->whereUuid('batch')->middleware('access:inventory.write&inventory.adjust')->name('transfer');
                Route::patch('batches/{batch}/quarantine', 'quarantine')->whereUuid('batch')->middleware('access:inventory.write&inventory.adjust')->name('quarantine');
            });
        });
        Route::dataTransfer('inventory/stock', 'inventory');
    });

    // Medicines of a session (prescribed there, given or handed over).
    Route::prefix('appointments/{appointment}/medications')->whereUuid('appointment')->name('appointments.medications.')->controller(DispensingController::class)->group(function (): void {
        Route::get('/', 'forAppointment')
            ->middleware(['access:(dispensing.read_all|dispensing.read_own)|(appointments.read_all|appointments.read_own)', 'throttle:read'])->name('index');
        Route::post('/', 'administer')->middleware(['access:dispensing.administer', 'throttle:sensitive', EnsureIdempotency::ALIAS])->name('administer');
    });
});
