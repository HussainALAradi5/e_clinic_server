<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accepts (or generates) the X-Request-Id correlation id, shares it with logs and echoes it on the response.
 */
final class AssignRequestId
{
    public const HEADER = 'X-Request-Id';

    public const ATTRIBUTE = 'request_id';

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = (string) $request->header(self::HEADER);
        $requestId = preg_match('/^[A-Za-z0-9-]{8,64}$/', $incoming) === 1 ? $incoming : (string) Str::uuid();

        $request->headers->set(self::HEADER, $requestId);
        $request->attributes->set(self::ATTRIBUTE, $requestId);
        Log::shareContext([self::ATTRIBUTE => $requestId]);

        $response = $next($request);
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }
}
