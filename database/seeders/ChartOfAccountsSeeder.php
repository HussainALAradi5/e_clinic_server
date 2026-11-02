<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Accounting\AccountMappingKey;
use App\Enums\Accounting\AccountType;
use App\Models\Accounting\Account;
use App\Models\Accounting\AccountMapping;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;

/**
 * Default clinic chart of accounts and account mappings (config accounting.default_chart). Idempotent:
 * existing codes are left as the accountant edited them.
 */
final class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        /** @var list<array{0: string, 1: string, 2: string, 3: ?string, 4: bool, 5: ?AccountMappingKey}> $chart */
        $chart = Config::array('accounting.default_chart');
        $byCode = [];

        foreach ($chart as [$code, $name, $type, $parentCode, $isGroup, $mapping]) {
            $parent = $parentCode === null ? null : ($byCode[$parentCode] ?? Account::query()->where('code', $parentCode)->first());
            $accountType = AccountType::from($type);

            $account = Account::query()->withDeleted()->firstOrCreate(['code' => $code], [
                'name' => $name,
                'type' => $accountType,
                'normal_balance' => $accountType->normalBalance(),
                'parent_id' => $parent?->id,
                'is_group' => $isGroup,
                'level' => $parent === null ? 1 : $parent->level + 1,
            ]);
            $account->forceFill(['is_system' => true])->save();
            $byCode[$code] = $account;

            if ($mapping !== null) {
                AccountMapping::query()->firstOrCreate(['key' => $mapping], ['account_id' => $account->id]);
            }
        }
    }
}
