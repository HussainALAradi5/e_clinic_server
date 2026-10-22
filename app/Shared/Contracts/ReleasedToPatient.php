<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Owner records whose sensitive files the patient they are about may open once released (e.g. a completed result
 * in the portal), without the staff right `patients.view_medical`.
 */
interface ReleasedToPatient
{
    public function isReleasedTo(Authenticatable $user): bool;
}
