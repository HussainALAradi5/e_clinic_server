<?php

declare(strict_types=1);

namespace App\Modules\Identity\Users\Exceptions;

use App\Shared\Exceptions\ConflictException;

/**
 * A person with the same national ID, or the same phone + date of birth, already exists; `meta.candidates` lists them.
 */
final class DuplicatePersonException extends ConflictException
{
    protected string $errorCode = 'PERSON_DUPLICATE';
}
