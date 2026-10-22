<?php

declare(strict_types=1);

namespace App\Modules\HR\Employees\Http\Resources;

use App\Modules\HR\Employees\Models\EmployeeSalaryComponent;
use App\Shared\Support\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmployeeSalaryComponent
 */
final class SalaryComponentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type->value,
            'name' => $this->name,
            'amount' => MoneyHelper::toApi($this->amount),
            'effective_from' => $this->effective_from->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'is_taxable' => $this->is_taxable,
        ];
    }
}
