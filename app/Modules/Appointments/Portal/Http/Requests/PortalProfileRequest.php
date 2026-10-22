<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Portal\Http\Requests;

use App\Modules\Appointments\Patients\Data\PatientData;
use App\Modules\Identity\Users\Data\PersonData;
use App\Modules\Reference\Addresses\Data\AddressData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PUT /portal/profile — what a patient may change about themselves: how to reach them (phone, email, address,
 * language, channels) and their emergency contact. Name, birth date, ID and insurance stay with the front desk.
 */
final class PortalProfileRequest extends FormRequest
{
    private const PERSON = ['phone', 'email', 'language', 'notification_opt_out', 'address'];

    private const PATIENT = ['emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'person_version' => ['required', 'integer', 'min:1'],
            'phone' => ['sometimes', 'required', 'string', 'max:20', 'phone:INTERNATIONAL,BH'],
            'email' => ['sometimes', 'required', 'email:rfc', 'max:255'],
            'language' => ['sometimes', 'required', Rule::in(['en', 'ar'])],
            'notification_opt_out' => ['nullable', 'array'],
            'notification_opt_out.*' => ['string', Rule::in(['email', 'sms', 'whatsapp', 'push'])],
            ...AddressData::rules('address'),
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20', 'phone:INTERNATIONAL,BH'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function person(): ?PersonData
    {
        $input = collect($this->validated())->only(self::PERSON)->all();

        return $input === [] ? null : PersonData::fromArray($input);
    }

    public function patient(): ?PatientData
    {
        $input = collect($this->validated())->only(self::PATIENT)->all();

        return $input === [] ? null : PatientData::fromArray($input);
    }
}
