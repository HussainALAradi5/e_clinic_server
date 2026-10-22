<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Fiscal\Http\Resources;

use App\Modules\Accounting\Fiscal\Models\FiscalPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FiscalPeriod
 */
final class FiscalPeriodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'fiscal_year_id' => $this->fiscal_year_id,
            'period_number' => $this->period_number,
            'name' => $this->name,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'status' => $this->status->value,
            'closed_at' => $this->closed_at?->toIso8601String(),
        ];
    }
}
