<?php

declare(strict_types=1);

namespace App\Http\Resources\Payments;

use App\Dtos\Payments\CoverageSplit;
use App\Utilities\Shared\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The insurer / patient split of an invoice; `patient_due` is what the desk takes now.
 *
 * @property CoverageSplit $resource
 */
final class CoverageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $s = $this->resource;

        return [
            'covered' => $s->covered,
            'reason' => $s->reason,
            'insurer' => $s->insurerName,
            'coverage_percent' => $s->coveragePercent,
            'insurable_total' => MoneyHelper::toApi($s->insurableTotal),
            'insurer_share' => MoneyHelper::toApi($s->insurerShare),
            'patient_share' => MoneyHelper::toApi($s->patientShare),
            'patient_paid' => MoneyHelper::toApi($s->patientPaid),
            'patient_due' => MoneyHelper::toApi($s->patientDue),
        ];
    }
}
