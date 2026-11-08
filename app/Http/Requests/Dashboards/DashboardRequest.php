<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboards;

use App\Dtos\Dashboards\DashboardData;
use App\Enums\Dashboards\DashboardSection;
use App\Enums\Dashboards\DashboardWidgetSize;
use App\Interfaces\Dashboards\DashboardServiceInterface;
use App\Services\Dashboards\DashboardMetricRegistry;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create: name, section, widgets. Update: name, description, widgets; the section and the default flag are fixed here
 * (the widgets belong to the section, and PATCH /default moves the default). Each widget's metric must exist in the section.
 */
final class DashboardRequest extends FormRequest
{
    private const MAX_WIDGETS = 24;

    /**
     * @return array<string, mixed>
     */
    public function rules(DashboardMetricRegistry $registry, DashboardServiceInterface $dashboards): array
    {
        $isCreate = $this->isMethod('POST');
        $required = $isCreate ? 'required' : 'sometimes';
        $rawSection = $this->input('section');
        $section = $isCreate
            ? (is_string($rawSection) ? DashboardSection::tryFrom($rawSection) : null)
            : $dashboards->findDashboard((string) $this->route('dashboard'))->section;

        return [
            'name' => [$required, 'string', 'min:2', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'section' => $isCreate ? ['required', Rule::enum(DashboardSection::class)] : ['prohibited'],
            'is_default' => $isCreate ? ['sometimes', 'boolean'] : ['prohibited'],
            'widgets' => [$required, 'array', 'min:1', 'max:'.self::MAX_WIDGETS],
            'widgets.*.metric' => ['required', 'string', 'distinct', function (string $attribute, mixed $value, Closure $fail) use ($registry, $section): void {
                if ($section !== null && (! is_string($value) || $registry->find($section, $value) === null)) {
                    $fail(__('errors.DASHBOARD_UNKNOWN_METRIC'));
                }
            }],
            'widgets.*.title' => ['nullable', 'string', 'max:60'],
            'widgets.*.size' => ['required', Rule::enum(DashboardWidgetSize::class)],
            'widgets.*.position' => ['required', 'integer', 'min:0', 'distinct'],
        ];
    }

    public function toData(): DashboardData
    {
        return DashboardData::fromArray($this->validated());
    }
}
