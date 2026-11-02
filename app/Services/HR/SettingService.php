<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Enums\HR\SettingKey;
use App\Interfaces\HR\SettingServiceInterface;
use App\Models\HR\Setting;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Query\ListQuery;
use App\Utilities\Shared\MoneyHelper;
use Brick\Money\Money;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

    public function save(SettingKey $key, bool $enabled, ?string $amount = null): Setting
    {
        return DB::transaction(function () use ($key, $enabled, $amount): Setting {
            $setting = Setting::query()->where('key', $key->value)->lockForUpdate()->first() ?? new Setting(['key' => $key]);
            $setting->fill([
                'is_enabled' => $enabled,
                'amount' => $key->hasAmount() && $amount !== null && $amount !== '' ? MoneyHelper::of($amount) : null,
            ])->save();

            return $setting->refresh();
        });
    }
}
