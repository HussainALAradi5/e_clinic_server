<?php

declare(strict_types=1);

namespace App\Http\Resources\Payments;

use App\Models\Payments\ServicePackage;
use App\Utilities\Shared\MoneyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A package in the catalog.
 *
 * @mixin ServicePackage
 */
final class ServicePackageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'covered_service' => ['id' => $this->covered_service_id, 'code' => $this->coveredService->code, 'name' => $this->coveredService->name, 'price' => MoneyHelper::toApi($this->coveredService->price)],
            'sessions' => $this->sessions,
            'price' => MoneyHelper::toApi($this->price),
            'validity_days' => $this->validity_days,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
