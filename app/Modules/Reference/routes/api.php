<?php

use App\Http\Controllers\Reference\AddressController;
use App\Http\Controllers\Reference\ClinicProfileController;
use App\Http\Controllers\Reference\CompanyController;
use App\Http\Controllers\Reference\CountryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reference Data API (prefix /api/v1) — Countries, Addresses, Companies
|--------------------------------------------------------------------------
*/

// Public: country picker of portal registration and the public website.
Route::get('public/countries', [CountryController::class, 'options'])->middleware('throttle:public')->name('public.countries');

// Public: the clinic's name, contacts and logo (letterheads, the public website, the apps' sign-in screens).
Route::prefix('public/clinic-profile')->name('public.clinic-profile.')->controller(ClinicProfileController::class)->middleware('throttle:public')->group(function (): void {
    Route::get('/', 'show')->name('show');
    Route::get('{image}', 'image')->whereIn('image', ['logo', 'stamp'])->name('image');
});

Route::middleware('auth:sanctum')->prefix('reference')->name('reference.')->group(function (): void {

    // Clinic profile (admins): name, contacts, logo and stamp printed on every PDF
    Route::prefix('clinic-profile')->name('clinic-profile.')->controller(ClinicProfileController::class)->middleware('access:clinic_profile.write')->group(function (): void {
        Route::put('/', 'update')->middleware('throttle:write')->name('update');
        Route::post('{image}', 'uploadImage')->whereIn('image', ['logo', 'stamp'])->middleware('throttle:write')->name('image.upload');
        Route::delete('{image}', 'removeImage')->whereIn('image', ['logo', 'stamp'])->middleware('throttle:write')->name('image.remove');
    });

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
    Route::dataTransfer('companies', 'companies');
    Route::dataTransfer('countries', 'countries');
});
