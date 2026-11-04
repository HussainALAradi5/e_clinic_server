<?php

declare(strict_types=1);

namespace App\Exceptions\Payments;

use App\Exceptions\Shared\BusinessValidationException;

/**
 * A balance rule was broken: deposit below the minimum, balance above the maximum, adjustment above the role's limit,
 * a method not allowed for the movement.
 */
final class WalletLimitException extends BusinessValidationException
{
    protected string $errorCode = 'WALLET_LIMIT';
}
