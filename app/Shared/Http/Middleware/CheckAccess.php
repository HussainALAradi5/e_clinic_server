<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use App\Shared\Access\PermissionExpression;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware `access:<expression>`, e.g. `access:invoices.write&invoices.issue`.
 */
final class CheckAccess
{
    public const ALIAS = 'access';

    public function handle(Request $request, Closure $next, string $expression): Response
    {
        $user = $request->user() ?? throw new AuthenticationException;

        if (! PermissionExpression::parse($expression)->evaluate($user)) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
