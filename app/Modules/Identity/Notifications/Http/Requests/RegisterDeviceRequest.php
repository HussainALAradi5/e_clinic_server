<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications\Http\Requests;

use App\Enums\Identity\DevicePlatform;
use App\Enums\Identity\MobileApp;
use App\Modules\Identity\Notifications\Data\DeviceData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RegisterDeviceRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:255'],
            'app' => ['required', Rule::enum(MobileApp::class)],
            'platform' => ['required', Rule::enum(DevicePlatform::class)],
            'device_name' => ['nullable', 'string', 'max:100'],
            'app_version' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function toData(): DeviceData
    {
        return DeviceData::fromArray($this->validated());
    }
}
