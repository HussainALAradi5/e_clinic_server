<?php

declare(strict_types=1);

namespace App\Modules\HR\Settings\Contracts;

use App\Enums\HR\SettingKey;
use App\Modules\HR\Settings\Models\Setting;
use App\Shared\Query\ListQuery;
use Brick\Money\Money;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * HR switches (admin and HR): late excuse requests on / off, overtime requests on / off and the overtime pay per hour.
 * A key without a row reads as off.
 */
interface SettingServiceInterface
{
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    public function find(SettingKey $key): Setting;

    public function enabled(SettingKey $key): bool;

    /** The amount of a setting that has one (overtime pay per hour), null when not set. */
    public function amount(SettingKey $key): ?Money;

    /** Creates the key's row when missing, else updates it; the amount is kept only by keys that have one. */
    public function save(SettingKey $key, bool $enabled, ?string $amount = null): Setting;
}
