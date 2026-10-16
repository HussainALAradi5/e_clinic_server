<?php

declare(strict_types=1);

namespace App\Shared\Casts;

use App\Shared\Support\MoneyHelper;
use Brick\Money\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Casts numeric(14,3) columns to Brick\Money\Money and back; serialises as {amount: "25.500", currency: "BHD"}.
 *
 * Usage: `'total' => MoneyCast::class` or `MoneyCast::class.':currency'` to read the currency from another column.
 *
 * @implements CastsAttributes<Money, Money|string|int|float|null>
 */
final class MoneyCast implements CastsAttributes, SerializesCastableAttributes
{
    public function __construct(private readonly ?string $currencyColumn = null) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        return $value === null ? null : MoneyHelper::of((string) $value, $this->currency($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof Money => MoneyHelper::toDb($value),
            is_string($value), is_int($value) => MoneyHelper::toDb(MoneyHelper::of($value, $this->currency($attributes))),
            default => throw new InvalidArgumentException("Money attribute [{$key}] accepts Money, numeric strings or integers — never floats."),
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{amount: string, currency: string}|null
     */
    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        return $value instanceof Money ? MoneyHelper::toApi($value) : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function currency(array $attributes): string
    {
        $currency = $this->currencyColumn !== null ? ($attributes[$this->currencyColumn] ?? null) : null;

        return is_string($currency) && $currency !== '' ? $currency : MoneyHelper::defaultCurrency();
    }
}
