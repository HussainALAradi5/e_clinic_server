<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Accounts\Http\Resources;

use App\Enums\Accounting\AccountMappingKey;
use App\Modules\Accounting\Accounts\Models\AccountMapping;

/**
 * One logical role of automatic postings and the account it uses (null = not mapped).
 */
final class AccountMappingResource
{
    /**
     * @return array<string, mixed>
     */
    public static function make(string $key, ?AccountMapping $mapping): array
    {
        $role = AccountMappingKey::from($key);

        return [
            'key' => $key,
            'label' => $role->label(),
            'account_type' => $role->accountType()->value,
            'account' => $mapping === null ? null : [
                'id' => $mapping->account->id,
                'code' => $mapping->account->code,
                'name' => $mapping->account->name,
            ],
        ];
    }
}
