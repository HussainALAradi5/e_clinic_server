<?php

declare(strict_types=1);

namespace App\Http\Resources\Reference;

use App\Models\Reference\ClinicProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The clinic profile; `logo_path` / `stamp_path` (public, relative to the API) are null while none is set (the logo
 * falls back to the e-Clinic mark), and change with every new image so caches refresh.
 *
 * @mixin ClinicProfile
 */
final class ClinicProfileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'logo_path' => $this->logo_attachment_id !== null ? 'public/clinic-profile/logo?v='.$this->logo_attachment_id : null,
            'stamp_path' => $this->stamp_attachment_id !== null ? 'public/clinic-profile/stamp?v='.$this->stamp_attachment_id : null,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
