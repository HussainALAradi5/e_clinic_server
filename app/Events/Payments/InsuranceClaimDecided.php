<?php

declare(strict_types=1);

namespace App\Events\Payments;

use App\Models\Payments\InsuranceClaim;
use Illuminate\Foundation\Events\Dispatchable;

final class InsuranceClaimDecided
{
    use Dispatchable;

    public function __construct(public readonly InsuranceClaim $claim) {}
}
