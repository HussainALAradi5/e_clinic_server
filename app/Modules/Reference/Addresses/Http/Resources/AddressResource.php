<?php

declare(strict_types=1);

namespace App\Modules\Reference\Addresses\Http\Resources;

use App\Modules\Reference\Addresses\Contracts\AddressServiceInterface;
use App\Modules\Reference\Addresses\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Address
 */
final class AddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'country_code' => $this->country_code,
            'flat' => $this->flat,
            'building' => $this->building,
            'road' => $this->road,
            'block' => $this->block,
            'area' => $this->area,
            'city' => $this->city,
            'governorate' => $this->governorate,
            'postal_code' => $this->postal_code,
            'line1' => $this->line1,
            'line2' => $this->line2,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'formatted' => $this->whenLoaded('country', fn (): string => app(AddressServiceInterface::class)->format($this->resource, app()->getLocale())),
        ];
    }
}
