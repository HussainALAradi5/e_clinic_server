<?php

declare(strict_types=1);

namespace App\Http\Resources\Reference;

use App\Models\Reference\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Country
 */
final class CountryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Lists and tables key rows by `id`; for countries that is the ISO code.
            'id' => $this->code,
            'code' => $this->code,
            'iso3' => $this->iso3,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'nationality' => $this->nationality,
            'nationality_ar' => $this->nationality_ar,
            'phone_code' => $this->phone_code,
            'currency_code' => $this->currency_code,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
