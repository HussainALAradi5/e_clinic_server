<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Fiscal\Http\Resources;

use App\Modules\Accounting\Fiscal\Models\FiscalYear;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FiscalYear
 */
final class FiscalYearResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'status' => $this->status->value,
            'closing_journal_entry_id' => $this->closing_journal_entry_id,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'periods' => FiscalPeriodResource::collection($this->whenLoaded('periods')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
