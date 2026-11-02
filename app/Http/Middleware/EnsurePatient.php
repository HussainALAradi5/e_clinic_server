<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Identity\UserType;
use App\Models\Identity\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware `patient`: portal endpoints (`/portal/*`) act on the signed-in patient's own file only,
 * so staff accounts are refused (403) instead of finding no patient record.
 */
final class EnsurePatient
{
    public const ALIAS = 'patient';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || $user->user_type !== UserType::Patient) {
            throw new AuthorizationException(__('errors.FORBIDDEN'));
        }

        return $next($request);
    }
}
