<?php

use App\Http\Controllers\Dashboards\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Dashboards API (prefix /dashboards) — saved dashboards of any section and their figures
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->prefix('dashboards')->name('dashboards.')->controller(DashboardController::class)->group(function (): void {
    Route::middleware(['access:dashboards.read_all', 'throttle:read'])->group(function (): void {
        Route::get('metrics', 'metrics')->name('metrics');
        Route::get('/', 'index')->name('index');
        Route::get('{dashboard}', 'show')->whereUuid('dashboard')->name('show');
        Route::get('{dashboard}/data', 'data')->whereUuid('dashboard')->name('data');
    });
    Route::middleware('throttle:write')->group(function (): void {
        Route::post('/', 'store')->middleware('access:dashboards.write')->name('store');
        Route::put('{dashboard}', 'update')->whereUuid('dashboard')->middleware('access:dashboards.write')->name('update');
        Route::patch('{dashboard}/default', 'setDefault')->whereUuid('dashboard')->middleware('access:dashboards.write')->name('default');
        Route::delete('{dashboard}', 'destroy')->whereUuid('dashboard')->middleware('access:dashboards.delete')->name('destroy');
    });
});
