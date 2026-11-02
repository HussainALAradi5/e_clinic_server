<?php

declare(strict_types=1);

namespace App\Exceptions\Appointments;

use App\Exceptions\Shared\ConflictException;

/**
 * Only a pending order can be edited.
 */
final class OrderNotEditableException extends ConflictException
{
    protected string $errorCode = 'ORDER_NOT_EDITABLE';
}
