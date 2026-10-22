<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Accounts\Http\Requests;

use App\Enums\Accounting\AccountType;
use App\Modules\Accounting\Accounts\Data\AccountData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST (code, name, type required) and PUT (`sometimes`; the code never changes).
 */
final class AccountRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->isMethod('POST');
        $required = $creating ? 'required' : 'sometimes';

        return [
            'code' => $creating ? ['required', 'string', 'regex:/^[0-9]{3,10}$/'] : ['prohibited'],
            'name' => [$required, 'string', 'max:150'],
            'name_ar' => ['nullable', 'string', 'max:150'],
            'type' => [$required, Rule::enum(AccountType::class)],
            'parent_id' => ['nullable', 'uuid'],
            'is_group' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function toData(): AccountData
    {
        return AccountData::fromArray($this->validated());
    }
}
