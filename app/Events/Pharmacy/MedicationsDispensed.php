<?php

declare(strict_types=1);

namespace App\Events\Pharmacy;

use App\Models\Pharmacy\Dispensation;
use Illuminate\Foundation\Events\Dispatchable;

/** Medicines left stock for a patient (charged and costed). */
final class MedicationsDispensed
{
    use Dispatchable;

    public function __construct(public readonly Dispensation $dispensation) {}
}
