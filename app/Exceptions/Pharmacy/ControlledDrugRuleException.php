<?php

declare(strict_types=1);

namespace App\Exceptions\Pharmacy;

use App\Exceptions\Shared\ForbiddenActionException;

/**
 * A controlled medicine needs a second staff member as witness and, when taken home, a prescription.
 */
final class ControlledDrugRuleException extends ForbiddenActionException
{
    protected string $errorCode = 'CONTROLLED_DRUG_RULE';
}
