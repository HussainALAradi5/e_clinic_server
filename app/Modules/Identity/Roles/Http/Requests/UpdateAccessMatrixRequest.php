<?php

declare(strict_types=1);

namespace App\Modules\Identity\Roles\Http\Requests;

use App\Modules\Identity\Roles\Data\AccessRowData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAccessMatrixRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.resource' => ['required', 'string', 'distinct', Rule::in(array_keys((array) config('access.resources')))],
            'rows.*.read_own' => ['boolean'],
            'rows.*.read_all' => ['boolean'],
            'rows.*.create' => ['boolean'],
            'rows.*.write' => ['boolean'],
            'rows.*.delete' => ['boolean'],
            'rows.*.actions' => ['array'],
            'rows.*.actions.*' => ['string', 'max:50'],
        ];
    }

    /**
     * @return list<AccessRowData>
     */
    public function rows(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->validated('rows');

        return array_map(fn (array $row): AccessRowData => AccessRowData::fromArray($row), $rows);
    }
}
