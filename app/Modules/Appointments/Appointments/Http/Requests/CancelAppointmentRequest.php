<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Appointments\Http\Requests;

use App\Enums\Appointments\CancelledBy;
use App\Modules\Appointments\Appointments\Data\CancelAppointmentData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CancelAppointmentRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
            'cancelled_by' => ['required', Rule::enum(CancelledBy::class)->except([CancelledBy::System])],
        ];
    }

    public function toData(): CancelAppointmentData
    {
        return new CancelAppointmentData(CancelledBy::from($this->string('cancelled_by')->toString()), $this->string('reason')->toString());
    }
}
