<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboards;

use App\Enums\Dashboards\DashboardSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** `section`: whose metrics to list. */
final class DashboardMetricsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['section' => ['required', Rule::enum(DashboardSection::class)]];
    }

    public function section(): DashboardSection
    {
        return DashboardSection::from($this->string('section')->toString());
    }
}
