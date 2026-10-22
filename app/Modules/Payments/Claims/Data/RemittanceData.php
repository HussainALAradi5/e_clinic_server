<?php

declare(strict_types=1);

namespace App\Modules\Payments\Claims\Data;

use Carbon\CarbonImmutable;

/**
 * One bank transfer from an insurer settling several approved claims.
 *
 * @param  list<string>  $claimIds
 */
final readonly class RemittanceData
{
    /**
     * @param  list<string>  $claimIds
     */
    public function __construct(
        public array $claimIds,
        public CarbonImmutable $date,
        public string $reference,
        public string $amount,
    ) {}
}
