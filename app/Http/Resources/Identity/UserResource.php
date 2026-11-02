<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Models\Identity\Role;
use App\Models\Identity\User;
use App\Utilities\Identity\Avatar;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Account with the person's name / contact data (passwords and 2FA secrets are never serialised).
 *
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /** Embed the full person record (detail screens). */
    private bool $withPerson = false;

    public function withPerson(): self
    {
        $this->withPerson = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $person = $this->person;

        return [
            'id' => $this->id,
            'code' => $person->code,
            'first_name' => $person->first_name,
            'last_name' => $person->last_name,
            'full_name' => $person->full_name,
            'avatar_url' => Avatar::url($person),
            'email' => $person->email,
            'phone' => $person->phone,
            'language' => $person->language,
            'user_type' => $this->user_type->value,
            'status' => $this->status->value,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'last_login_ip' => $this->last_login_ip,
            'locked_at' => $this->locked_at?->toIso8601String(),
            'two_factor' => [
                'enabled' => $this->hasTwoFactorEnabled(),
                'required' => $this->requiresTwoFactor(),
            ],
            'roles' => $this->whenLoaded('roles', fn (): array => $this->roles->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
            ])->values()->all()),
            'person' => $this->when($this->withPerson, fn (): PersonResource => new PersonResource($person)),
        ];
    }
}
