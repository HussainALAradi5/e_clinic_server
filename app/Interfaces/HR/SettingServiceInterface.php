<?php

declare(strict_types=1);

namespace App\Interfaces\HR;

use App\Dtos\HR\SettingData;
use App\Enums\HR\SettingKey;
use App\Models\HR\OvertimeBand;
use App\Models\HR\Setting;
use App\Utilities\Query\ListQuery;
use Brick\Money\Money;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * HR switches (admin and HR): late excuse requests on / off, overtime requests on / off and the overtime pay per hour.
 * A key without a row reads as off.
 */
interface SettingServiceInterface
{
    /** @return LengthAwarePaginator<int, Setting>|CursorPaginator<int, Setting> */
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    public function find(SettingKey $key): Setting;

    public function enabled(SettingKey $key): bool;

    /** The amount of a setting that has one (overtime pay per hour), null when not set. */
    public function amount(SettingKey $key): ?Money;

    /** Creates the key's row when missing, else updates it; the amount is kept only by keys that have one. */
    /**
     * Overtime time bands, earliest first.
     *
     * @return Collection<int, OvertimeBand>
     */
    public function overtimeBands(): Collection;

    public function save(SettingKey $key, SettingData $data): Setting;
}
