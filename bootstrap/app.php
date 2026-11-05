<?php

use App\Exceptions\Shared\ApiExceptionRenderer;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\CheckAccess;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\EnsurePatient;
use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: '',
    )
    // Listeners are registered by each module's service provider; discovery in app/Listeners would add them twice.
    ->withEvents(discover: false)
    // Socket channel sign-in uses the API token: POST /broadcasting/auth.
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['prefix' => '', 'middleware' => ['auth:sanctum']])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        // An API without a login page: signed-out calls get 401 JSON, never a redirect (even without Accept: json).
        $middleware->redirectGuestsTo(fn (): ?string => null);
        $middleware->api(append: [SecurityHeaders::class]);
        $middleware->alias([
            CheckAccess::ALIAS => CheckAccess::class,
            EnsureIdempotency::ALIAS => EnsureIdempotency::class,
            EnsureTwoFactor::ALIAS => EnsureTwoFactor::class,
            EnsurePatient::ALIAS => EnsurePatient::class,
            EnsureStaff::ALIAS => EnsureStaff::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        ApiExceptionRenderer::register($exceptions);
    })->create();
