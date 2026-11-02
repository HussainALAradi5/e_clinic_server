<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Dtos\Shared\IdempotentResponse;
use App\Exceptions\Shared\ConcurrencyConflictException;
use App\Exceptions\Shared\IdempotencyKeyConflictException;
use App\Exceptions\Shared\IdempotencyKeyMissingException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Idempotency-Key contract (API conventions §7), route alias `idempotent`.
 *
 * Missing key → 428; same key + same body → stored response replayed; same key + other body → 409;
 * concurrent request with the same key → 409 CONCURRENT_MODIFICATION.
 */
final class EnsureIdempotency
{
    public const ALIAS = 'idempotent';

    public const HEADER = 'Idempotency-Key';

    public const REPLAYED_HEADER = 'Idempotent-Replayed';

    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header(self::HEADER);

        if (! Str::isUuid($key)) {
            throw new IdempotencyKeyMissingException;
        }

        $cacheKey = 'idempotency:'.hash('sha256', implode('|', [$request->user()?->getAuthIdentifier(), $request->method(), $request->path(), $key]));
        $fingerprint = hash('sha256', $request->getContent());

        $stored = Cache::get($cacheKey);
        if ($stored instanceof IdempotentResponse) {
            if (! hash_equals($stored->fingerprint, $fingerprint)) {
                throw new IdempotencyKeyConflictException;
            }

            return $stored->toResponse(self::REPLAYED_HEADER);
        }

        $lock = Cache::lock($cacheKey.':lock', 30);
        if (! $lock->get()) {
            throw new ConcurrencyConflictException;
        }

        try {
            $response = $next($request);

            if ($response->getStatusCode() < 500) {
                Cache::put($cacheKey, IdempotentResponse::capture($fingerprint, $response), now()->addHours(Config::integer('clinic.idempotency.ttl_hours')));
            }

            return $response;
        } finally {
            $lock->release();
        }
    }
}
