<?php

declare(strict_types=1);

namespace App\Http\Resources\Appointments;

use App\Models\Appointments\Icd10Code;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Icd10Code
 */
final class Icd10Resource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'title_ar' => $this->title_ar,
            'chapter' => $this->chapter,
            'is_billable' => $this->is_billable,
        ];
    }
}
