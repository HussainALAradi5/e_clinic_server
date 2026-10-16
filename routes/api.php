<?php

use App\Shared\Http\Controllers\FileController;
use App\Shared\Http\Controllers\MetaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 (prefix /api/v1)
|--------------------------------------------------------------------------
| Shared endpoints live here; every module keeps its own routes in app/Modules/<Module>/routes/api.php.
*/

Route::middleware(['auth:sanctum', 'throttle:read'])->prefix('meta')->name('meta.')->group(function (): void {
    Route::get('enums', [MetaController::class, 'enums'])->name('enums');
    Route::get('resources/{resource}', [MetaController::class, 'resource'])->name('resources.show');
});

// Private files behind short-lived signed links (attachments, task results) — the signature is the credential.
Route::get('files', FileController::class)->middleware(['signed', 'throttle:read'])->name('files.show');

foreach (glob(app_path('Modules/*/routes/api.php')) ?: [] as $moduleRoutes) {
    require $moduleRoutes;
}
