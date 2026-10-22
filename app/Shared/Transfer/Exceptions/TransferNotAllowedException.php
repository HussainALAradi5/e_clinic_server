<?php

declare(strict_types=1);

namespace App\Shared\Transfer\Exceptions;

use App\Shared\Exceptions\ForbiddenActionException;

/**
 * Export / import is not offered for the resource, the user lacks `<resource>.export|import`, or the user is a
 * patient (patients never export or import, whatever their roles say).
 */
final class TransferNotAllowedException extends ForbiddenActionException
{
    protected string $errorCode = 'TRANSFER_NOT_ALLOWED';
}
