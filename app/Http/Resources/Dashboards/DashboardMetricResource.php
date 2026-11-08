<?php

declare(strict_types=1);

namespace App\Http\Resources\Dashboards;

use App\Dtos\Dashboards\DashboardMetric;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DashboardMetric
 */
final class DashboardMetricResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'kind' => $this->kind->value,
            'access' => $this->access,
        ];
    }
}
