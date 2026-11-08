<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboards;

use App\Dtos\Dashboards\DashboardFilterDto;
use App\Utilities\Shared\ClinicClock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/** `date` (Y-m-d, clinic day, not in the future); today when absent. */
final class DashboardDataRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(ClinicClock $clock): array
    {
        return [
            'date' => ['sometimes', 'date_format:Y-m-d', 'before_or_equal:'.$clock->today()->toDateString()],
        ];
    }

    public function toFilter(ClinicClock $clock): DashboardFilterDto
    {
        return new DashboardFilterDto($this->filled('date')
            ? CarbonImmutable::parse($this->string('date')->toString(), $clock->timezone())->startOfDay()
            : $clock->today());
    }
}
