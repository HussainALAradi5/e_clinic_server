<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Enums\Accounting\AccountMappingKey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateMappingsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mappings' => ['required', 'array', 'min:1'],
            'mappings.*.key' => ['required', 'distinct', Rule::enum(AccountMappingKey::class)],
            'mappings.*.account_id' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string> key => account id
     */
    public function mappings(): array
    {
        /** @var list<array{key: string, account_id: string}> $rows */
        $rows = $this->validated('mappings');

        return array_column($rows, 'account_id', 'key');
    }
}
