<?php

declare(strict_types=1);

namespace App\Modules\Appointments\Records\Http\Resources;

use App\Modules\Appointments\Records\Models\Icd10Code;
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
