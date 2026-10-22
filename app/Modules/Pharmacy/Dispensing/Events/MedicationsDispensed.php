<?php

declare(strict_types=1);

namespace App\Modules\Pharmacy\Dispensing\Events;

use App\Modules\Pharmacy\Dispensing\Models\Dispensation;
use Illuminate\Foundation\Events\Dispatchable;

/** Medicines left stock for a patient (charged and costed). */
final class MedicationsDispensed
{
    use Dispatchable;

    public function __construct(public readonly Dispensation $dispensation) {}
}
