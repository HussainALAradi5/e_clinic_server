<?php

declare(strict_types=1);

namespace App\Http\Requests\Identity;

use App\Dtos\Identity\SavedViewData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST (all required) and PUT (sometimes) of a saved view.
 */
final class SavedViewRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $presence = $creating ? 'required' : 'sometimes';

        return [
            'resource' => $creating ? ['required', 'string', 'max:50'] : ['prohibited'],
            'name' => [$presence, 'string', 'max:100'],
            'query' => [$presence, 'array'],
            'columns' => ['sometimes', 'array', 'max:50'],
            'columns.*' => ['string', 'max:50'],
            'is_shared' => ['sometimes', 'boolean'],
            'is_default' => $creating ? ['sometimes', 'boolean'] : ['prohibited'],
        ];
    }

    public function toData(): SavedViewData
    {
        return SavedViewData::fromArray($this->validated());
    }
}
