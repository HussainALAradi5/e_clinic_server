<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Orders\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * Only a pending order can be edited.
 */
final class OrderNotEditableException extends ConflictException
{
    protected string $errorCode = 'ORDER_NOT_EDITABLE';
}
