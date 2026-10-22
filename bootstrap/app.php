<?php

use App\Shared\Exceptions\ApiExceptionRenderer;
use App\Shared\Http\Middleware\AssignRequestId;
use App\Shared\Http\Middleware\CheckAccess;
use App\Shared\Http\Middleware\EnsureIdempotency;
use App\Shared\Http\Middleware\EnsurePatient;
use App\Shared\Http\Middleware\EnsureTwoFactor;
use App\Shared\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api/v1',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(AssignRequestId::class);
        $middleware->api(append: [SecurityHeaders::class]);
        $middleware->alias([
            CheckAccess::ALIAS => CheckAccess::class,
            EnsureIdempotency::ALIAS => EnsureIdempotency::class,
            EnsureTwoFactor::ALIAS => EnsureTwoFactor::class,
            EnsurePatient::ALIAS => EnsurePatient::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        ApiExceptionRenderer::register($exceptions);
    })->create();
