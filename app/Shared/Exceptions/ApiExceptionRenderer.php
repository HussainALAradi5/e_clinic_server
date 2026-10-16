<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use App\Shared\Contracts\RendersApiError;
use App\Shared\Http\Middleware\AssignRequestId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Renders every API error in the standard envelope:
 * { success: false, message, error_code, errors, meta, request_id }.
 */
final class ApiExceptionRenderer
{
    /**
     * Framework exceptions → [status, error_code].
     *
     * @var array<class-string<Throwable>, array{0: int, 1: string}>
     */
    private const FRAMEWORK = [
        AuthenticationException::class => [401, 'UNAUTHENTICATED'],
        AuthorizationException::class => [403, 'FORBIDDEN'],
        AccessDeniedHttpException::class => [403, 'FORBIDDEN'],
        ModelNotFoundException::class => [404, 'NOT_FOUND'],
        NotFoundHttpException::class => [404, 'NOT_FOUND'],
        ValidationException::class => [422, 'VALIDATION_FAILED'],
        ThrottleRequestsException::class => [429, 'TOO_MANY_REQUESTS'],
    ];

    public static function register(Exceptions $exceptions): void
    {
        $exceptions->dontReport([DomainException::class]);
        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => self::isApi($request));
        $exceptions->render(fn (Throwable $e, Request $request): ?JsonResponse => self::isApi($request) ? self::render($e, $request) : null);
    }

    public static function isApi(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    public static function render(Throwable $e, Request $request): JsonResponse
    {
        [$status, $code, $errors, $meta] = self::describe($e);

        $usesOwnMessage = $e instanceof RendersApiError || str_starts_with($code, 'HTTP_');
        $message = $usesOwnMessage && $e->getMessage() !== '' ? $e->getMessage() : __('errors.'.$code);

        $headers = $e instanceof HttpExceptionInterface ? $e->getHeaders() : [];

        return new JsonResponse([
            'success' => false,
            'message' => $message,
            'error_code' => $code,
            'errors' => $errors,
            'meta' => $meta,
            'request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE) ?? $request->header(AssignRequestId::HEADER),
        ], $status, $headers);
    }

    /**
     * @return array{0: int, 1: string, 2: array<string, mixed>|null, 3: array<string, mixed>|null}
     */
    private static function describe(Throwable $e): array
    {
        if ($e instanceof RendersApiError) {
            $context = $e->context();

            return [$e->httpStatus(), $e->errorCode(), $context['errors'] ?? null, Arr::except($context, 'errors') ?: null];
        }

        if ($e instanceof ValidationException) {
            return [422, 'VALIDATION_FAILED', $e->errors(), null];
        }

        foreach (self::FRAMEWORK as $class => [$status, $code]) {
            if ($e instanceof $class) {
                return [$status, $code, null, null];
            }
        }

        if ($e instanceof HttpExceptionInterface) {
            return [$e->getStatusCode(), 'HTTP_'.$e->getStatusCode(), null, null];
        }

        return [500, 'SERVER_ERROR', null, null];
    }
}
