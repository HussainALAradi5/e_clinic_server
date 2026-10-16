<?php

use App\Modules\Reference\Addresses\Http\Controllers\AddressController;
use App\Modules\Reference\Companies\Http\Controllers\CompanyController;
use App\Modules\Reference\Countries\Http\Controllers\CountryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reference Data API (prefix /api/v1) — Countries, Addresses, Companies
|--------------------------------------------------------------------------
*/

// Public: country picker of portal registration and the public website.
Route::get('public/countries', [CountryController::class, 'options'])->middleware('throttle:public')->name('public.countries');

Route::middleware('auth:sanctum')->prefix('reference')->name('reference.')->group(function (): void {

    // Countries
    Route::prefix('countries')->name('countries.')->controller(CountryController::class)->group(function (): void {
        Route::middleware('throttle:read')->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('options', 'options')->name('options');
            Route::get('{country}', 'show')->where('country', '[A-Za-z]{2}')->name('show');
        });
        Route::put('{country}', 'update')->where('country', '[A-Za-z]{2}')->middleware(['access:reference.write', 'throttle:write'])->name('update');
    });

    // Addresses (readable when the owner record is)
    Route::prefix('addresses')->name('addresses.')->middleware('throttle:read')->controller(AddressController::class)->group(function (): void {
        Route::get('{address}', 'show')->whereUuid('address')->name('show');
        Route::get('{address}/formatted', 'formatted')->whereUuid('address')->name('formatted');
    });

    // Companies
    Route::prefix('companies')->name('companies.')->controller(CompanyController::class)->group(function (): void {
        Route::middleware(['access:companies.read_all|companies.read_own', 'throttle:read'])->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('{company}', 'show')->whereUuid('company')->name('show');
        });
        Route::middleware('throttle:write')->group(function (): void {
            Route::post('/', 'store')->middleware('access:companies.create')->name('store');
            Route::put('{company}', 'update')->whereUuid('company')->middleware('access:companies.write')->name('update');
            Route::patch('{company}/archive', 'archive')->whereUuid('company')->middleware('access:companies.write')->name('archive');
            Route::patch('{company}/unarchive', 'unarchive')->whereUuid('company')->middleware('access:companies.write')->name('unarchive');
            Route::delete('{company}', 'destroy')->whereUuid('company')->middleware('access:companies.delete')->name('destroy');
        });
        Route::patch('{company}/restore', 'restore')->whereUuid('company')->middleware(['access:companies.delete', 'throttle:sensitive'])->name('restore');
    });
    Route::bulkActions('companies', 'companies')->middleware('access:companies.write|companies.delete');
});
