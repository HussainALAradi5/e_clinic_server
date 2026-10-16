<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/**
 * Segregation of duties: users cannot approve what they created.
 */
final class SelfApprovalException extends ForbiddenActionException
{
    protected string $errorCode = 'SELF_APPROVAL_NOT_ALLOWED';
}
