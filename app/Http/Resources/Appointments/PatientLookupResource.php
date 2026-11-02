<?php

declare(strict_types=1);

namespace App\Http\Resources\Appointments;

use App\Models\Appointments\Patient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Who a looked-up patient is — enough to confirm the right person before asking for emergency access, nothing
 * medical. `in_care` says whether the file is already open to the caller.
 *
 * @mixin Patient
 */
final class PatientLookupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->person->code,
            'full_name' => $this->person->full_name,
            'gender' => $this->person->gender->value,
            'age' => $this->person->date_of_birth->age,
            'in_care' => (bool) $this->getAttribute('in_care'),
        ];
    }
}
