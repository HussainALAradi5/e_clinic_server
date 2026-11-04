<?php

declare(strict_types=1);

namespace App\Exceptions\Documents;

use App\Exceptions\Shared\ConflictException;

/**
 * Ready-made templates belong to the clinic: copy one to change it.
 */
final class TemplateReadOnlyException extends ConflictException
{
    protected string $errorCode = 'TEMPLATE_READ_ONLY';
}
