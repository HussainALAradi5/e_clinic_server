<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Http\Resources\Reference\AddressResource;
use App\Models\Identity\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Person
 */
final class PersonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'first_name_ar' => $this->first_name_ar,
            'last_name_ar' => $this->last_name_ar,
            'full_name' => $this->full_name,
            'gender' => $this->gender->value,
            'date_of_birth' => $this->date_of_birth->toDateString(),
            // Restricted data: only the last digits leave the server.
            'national_id' => $this->national_id !== null ? mask($this->national_id) : null,
            'nationality_code' => $this->nationality_code,
            'nationality' => $this->whenLoaded('nationality', fn (): ?array => $this->nationality === null ? null : [
                'code' => $this->nationality->code,
                'nationality' => $this->nationality->nationality,
                'nationality_ar' => $this->nationality->nationality_ar,
            ]),
            'address' => $this->whenLoaded('address', fn (): ?AddressResource => $this->address === null ? null : new AddressResource($this->address)),
            'phone' => $this->phone,
            'email' => $this->email,
            'language' => $this->language,
            'notification_opt_out' => $this->notification_opt_out,
            'has_account' => $this->whenLoaded('user', fn (): bool => $this->user !== null),
            'user_type' => $this->whenLoaded('user', fn (): ?string => $this->user?->user_type->value),
            'version' => $this->version,
            'is_archived' => $this->is_archived,
            'is_deleted' => $this->is_deleted,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
