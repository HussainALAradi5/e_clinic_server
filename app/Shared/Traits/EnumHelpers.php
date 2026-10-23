<?php

declare(strict_types=1);

namespace App\Shared\Traits;

use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;

/**
 * Shared helpers for backed enums (labels, select options, validation rule).
 *
 * @mixin \BackedEnum
 */
trait EnumHelpers
{
    /**
     * Translated label from lang/{locale}/enums.php, falling back to a headline of the value.
     */
    public function label(): string
    {
        $key = 'enums.'.class_basename(static::class).'.'.$this->value;
        $label = __($key);

        // Sentence case like the rest of the interface: `checked_in` → `Checked in`.
        return $label === $key ? Str::ucfirst(str_replace('_', ' ', (string) $this->value)) : $label;
    }

    /**
     * @return list<string|int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<array{value: string|int, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }

    public static function rule(): Enum
    {
        return new Enum(self::class);
    }
}
