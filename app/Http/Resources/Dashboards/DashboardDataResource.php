<?php

declare(strict_types=1);

namespace App\Http\Resources\Dashboards;

use App\Dtos\Dashboards\DashboardDataResult;
use App\Dtos\Dashboards\DashboardWidgetResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DashboardDataResult
 */
final class DashboardDataResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'dashboard_id' => $this->dashboardId,
            'date' => $this->date->toDateString(),
            'widgets' => array_map(fn (DashboardWidgetResult $w): array => [
                'metric' => $w->metric,
                'title' => $w->title,
                'kind' => $w->kind->value,
                'size' => $w->size,
                'position' => $w->position,
                'value' => $w->value,
                'items' => $w->items,
            ], $this->widgets),
        ];
    }
}
