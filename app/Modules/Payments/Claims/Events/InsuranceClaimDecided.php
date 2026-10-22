<?php

declare(strict_types=1);

namespace App\Modules\Payments\Claims\Events;

use App\Modules\Payments\Claims\Models\InsuranceClaim;
use Illuminate\Foundation\Events\Dispatchable;

final class InsuranceClaimDecided
{
    use Dispatchable;

    public function __construct(public readonly InsuranceClaim $claim) {}
}
