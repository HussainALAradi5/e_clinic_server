<?php

declare(strict_types=1);

namespace App\Shared\Http\Middleware;

use App\Shared\Contracts\TwoFactorAuthenticatable;
use App\Shared\Exceptions\TwoFactorEnrollmentRequiredException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware `ensure.2fa`: privileged roles (super admin, admin, accountant, HR manager)
 * must have confirmed 2FA before using sensitive endpoints (403 AUTH_2FA_REQUIRED).
 */
final class EnsureTwoFactor
{
    public const ALIAS = 'ensure.2fa';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof TwoFactorAuthenticatable && $user->requiresTwoFactor() && ! $user->hasTwoFactorEnabled()) {
            throw new TwoFactorEnrollmentRequiredException;
        }

        return $next($request);
    }
}
