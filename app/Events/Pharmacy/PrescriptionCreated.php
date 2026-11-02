<?php

declare(strict_types=1);

namespace App\Events\Pharmacy;

use App\Models\Pharmacy\Prescription;
use Illuminate\Foundation\Events\Dispatchable;

/** A new prescription joined the pharmacy queue. */
final class PrescriptionCreated
{
    use Dispatchable;

    public function __construct(public readonly Prescription $prescription) {}
}
