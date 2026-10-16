<?php

declare(strict_types=1);

namespace App\Modules\Identity\Users\Http\Requests;

use App\Modules\Identity\Users\Data\PersonData;
use App\Modules\Reference\Addresses\Data\AddressData;
use App\Shared\Enums\Gender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdatePersonRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:1'],
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'first_name_ar' => ['nullable', 'string', 'max:100'],
            'last_name_ar' => ['nullable', 'string', 'max:100'],
            'gender' => ['sometimes', 'required', Rule::enum(Gender::class)],
            'date_of_birth' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'national_id' => ['nullable', 'string', 'max:30'],
            'nationality_code' => ['nullable', 'string', 'size:2', Rule::exists('countries', 'code')->where('is_active', true)],
            'phone' => ['sometimes', 'required', 'string', 'max:20', 'phone:INTERNATIONAL,BH'],
            'email' => ['nullable', 'email:rfc', 'max:255'],
            'language' => ['sometimes', 'required', Rule::in(['en', 'ar'])],
            'notification_opt_out' => ['nullable', 'array'],
            'notification_opt_out.*' => ['string', Rule::in(['email', 'sms', 'whatsapp', 'push'])],
            ...AddressData::rules('address'),
        ];
    }

    public function toData(): PersonData
    {
        return PersonData::fromArray(collect($this->validated())->except('version')->all());
    }
}
