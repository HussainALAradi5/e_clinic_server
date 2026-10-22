<?php

declare(strict_types=1);

namespace App\Modules\HR\Leaves\Exceptions;

use App\Shared\Exceptions\BusinessValidationException;

/**
 * This leave type needs a supporting document (e.g. a sick-leave certificate) before it can be sent.
 */
final class LeaveAttachmentRequiredException extends BusinessValidationException
{
    protected string $errorCode = 'LEAVE_ATTACHMENT_REQUIRED';
}
