<?php

declare(strict_types=1);

namespace App\Models\HR;

use App\Casts\MoneyCast;
use App\Enums\HR\SettingKey;
use App\Models\Base\BaseModel;
use App\Traits\Shared\HasListQuery;
use App\Utilities\Query\ListQueryConfig;
use Brick\Money\Money;
use Carbon\CarbonImmutable;

/**
 * One HR switch (SET-000001): on / off and, for overtime, the pay per hour. One row per SettingKey.
 *
 * @property string $id
 * @property string $code
 * @property SettingKey $key
 * @property bool $is_enabled
 * @property ?Money $amount
 * @property ?string $updated_by
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Setting extends BaseModel
{
    use HasListQuery;

    protected $attributes = ['is_enabled' => false];

    public static function codePrefix(): string
    {
        return 'SET';
    }

    public static function codeResetsYearly(): bool
    {
        return false;
    }

    public static function listQueryConfig(): ListQueryConfig
    {
        return ListQueryConfig::make()
            ->resource('settings')
            ->sortable('key', 'code', 'updated_at')
            ->filterable('key', ['eq', 'in'], enum: SettingKey::class)
            ->filterable('is_enabled', ['eq'], type: 'boolean')
            ->column('key', 'Setting')
            ->column('is_enabled', 'On')
            ->column('amount', 'Amount')
            ->defaultSort('key');
    }

    protected function casts(): array
    {
        return [
            'key' => SettingKey::class,
            'is_enabled' => 'boolean',
            'amount' => MoneyCast::class,
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
