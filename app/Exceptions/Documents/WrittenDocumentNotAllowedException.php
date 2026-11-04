<?php

declare(strict_types=1);

namespace App\Exceptions\Documents;

use App\Exceptions\Shared\ForbiddenActionException;

/**
 * Only the visit's own provider writes its documents: a doctor, or for an attendance certificate also the nurse
 * running the treatment session.
 */
final class WrittenDocumentNotAllowedException extends ForbiddenActionException
{
    protected string $errorCode = 'WRITTEN_DOCUMENT_NOT_ALLOWED';
}
