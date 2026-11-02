<?php

declare(strict_types=1);

namespace App\Exceptions\Identity;

use App\Exceptions\Shared\ConflictException;

final class SavedViewNameTakenException extends ConflictException
{
    protected string $errorCode = 'SAVED_VIEW_NAME_TAKEN';
}
