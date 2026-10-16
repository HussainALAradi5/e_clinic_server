<?php

declare(strict_types=1);

namespace App\Modules\Reference\Companies\Http\Resources;

use App\Modules\Reference\Addresses\Http\Resources\AddressResource;
use App\Modules\Reference\Companies\Models\Company;
use App\Modules\Reference\Countries\Http\Resources\CountryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Company
 */
final class CompanyResource extends JsonResource
{
    /** @var array<string, int>|null */
    private ?array $usage = null;

    /**
     * @param  array<string, int>  $usage
     */
    public function withUsage(array $usage): self
    {
        $this->usage = $usage;

        return $this;
    }

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
            'name_ar' => $this->name_ar,
            'description' => $this->description,
            'country_code' => $this->country_code,
            'country' => new CountryResource($this->whenLoaded('country')),
            'address' => $this->address_id === null ? null : new AddressResource($this->whenLoaded('address')),
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'contact_person' => $this->contact_person,
            'vat_number' => $this->vat_number,
            'cr_number' => $this->cr_number,
            'usage' => $this->when($this->usage !== null, fn (): array => $this->usage ?? []),
            'is_archived' => $this->is_archived,
            'is_deleted' => $this->is_deleted,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
