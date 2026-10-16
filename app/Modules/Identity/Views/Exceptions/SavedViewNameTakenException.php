<?php

declare(strict_types=1);

namespace App\Modules\Identity\Views\Exceptions;

use App\Shared\Exceptions\ConflictException;

final class SavedViewNameTakenException extends ConflictException
{
    protected string $errorCode = 'SAVED_VIEW_NAME_TAKEN';
}
