<?php

declare(strict_types=1);

namespace App\Http\Resources\Communication;

use App\Models\Identity\User;
use App\Utilities\Identity\Avatar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A colleague who can be messaged.
 *
 * @mixin User
 */
final class ContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->person->full_name,
            'avatar_url' => Avatar::url($this->person),
            'user_type' => $this->user_type->value,
            'presence' => $this->presence->value,
            'code' => $this->person->code,
        ];
    }
}
