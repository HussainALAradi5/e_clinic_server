<?php

declare(strict_types=1);

namespace App\Modules\Communication\Chat\Http\Resources;

use App\Modules\Identity\Users\Models\User;
use App\Modules\Identity\Users\Support\Avatar;
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
        ];
    }
}
