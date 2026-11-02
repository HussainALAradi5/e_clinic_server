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
 * Route middleware `staff`: the counterpart of `patient`. Patients hold some create rights for the portal
 * (`appointments.create` books their own visit there); this keeps them off the staff endpoints that accept any
 * patient id or search all people.
 */
final class EnsureStaff
{
    public const ALIAS = 'staff';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || $user->user_type === UserType::Patient) {
            throw new AuthorizationException(__('errors.FORBIDDEN'));
        }

        return $next($request);
    }
}
