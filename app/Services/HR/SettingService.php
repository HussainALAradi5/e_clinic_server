<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Dtos\HR\OvertimeBandData;
use App\Dtos\HR\SettingData;
use App\Enums\HR\SettingKey;
use App\Interfaces\HR\SettingServiceInterface;
use App\Models\HR\OvertimeBand;
use App\Models\HR\Setting;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\MoneyHelper;
use Brick\Money\Money;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class SettingService implements SettingServiceInterface
{
    public function __construct(private readonly ApplyListQuery $listQuery) {}

    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator
    {
        return $this->listQuery->paginate(Setting::query(), $query);
    }

    public function find(SettingKey $key): Setting
    {
        return Setting::query()->where('key', $key->value)->first() ?? new Setting(['key' => $key, 'is_enabled' => false]);
    }

    public function enabled(SettingKey $key): bool
    {
        return (bool) Setting::query()->where('key', $key->value)->value('is_enabled');
    }

    public function amount(SettingKey $key): ?Money
    {
        return $key->hasAmount() ? $this->find($key)->amount : null;
    }

    public function overtimeBands(): Collection
    {
        return OvertimeBand::query()->orderBy('start_minute')->get();
    }

    public function save(SettingKey $key, SettingData $data): Setting
    {
        return DB::transaction(function () use ($key, $data): Setting {
            $setting = Setting::query()->where('key', $key->value)->lockForUpdate()->first() ?? new Setting(['key' => $key]);
            $setting->fill([
                'is_enabled' => $data->enabled,
                'amount' => $key->hasAmount() && $data->amount !== null && $data->amount !== '' ? MoneyHelper::of($data->amount) : null,
            ])->save();

            if ($key->hasAmount() && $data->bands !== null) {
                $this->replaceBands($data->bands);
            }

            return $setting->refresh();
        });
    }

    /**
     * @param  list<OvertimeBandData>  $bands
     */
    private function replaceBands(array $bands): void
    {
        OvertimeBand::query()->lockForUpdate()->get()->each->softDelete();

        foreach ($bands as $band) {
            OvertimeBand::query()->create([
                'start_minute' => $band->startMinute,
                'end_minute' => $band->endMinute,
                'rate' => MoneyHelper::of($band->rate),
            ]);
        }
    }
}
