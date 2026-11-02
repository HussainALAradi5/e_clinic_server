<?php

declare(strict_types=1);

namespace App\Http\Requests\HR;

use App\Dtos\HR\ClockLocationData;
use Illuminate\Foundation\Http\FormRequest;

/** Self check-in / check-out: the phone may send its position for the clinic geofence (the web sends nothing). */
final class ClockRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ];
    }

    public function location(): ?ClockLocationData
    {
        if (! $this->filled('latitude')) {
            return null;
        }

        return new ClockLocationData(
            $this->float('latitude'),
            $this->float('longitude'),
            $this->filled('accuracy') ? $this->float('accuracy') : null,
        );
    }
}
