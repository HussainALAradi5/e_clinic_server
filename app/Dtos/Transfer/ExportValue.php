<?php

declare(strict_types=1);

namespace App\Dtos\Transfer;

use App\Interfaces\Shared\HasLabel;
use App\Utilities\Shared\ClinicClock;
use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Turns one list cell into export text: enum labels instead of codes, readable dates, Yes / No, and the name of a
 * related record instead of its JSON.
 */
final class ExportValue
{
    private const DATE_TIME = 'Y-m-d H:i';

    /**
     * @param  array<string, mixed>  $row  the record rendered by the list's API resource
     */
    public static function of(Model $model, array $row, string $field): string
    {
        $attribute = self::attribute($model, $field);
        if ($attribute instanceof HasLabel) {
            return $attribute->label();
        }

        return self::text(data_get($row, $field) ?? $attribute);
    }

    /** A real (cast) column or accessor of the model — never a relation, which would lazy-load. */
    private static function attribute(Model $model, string $field): mixed
    {
        if (! array_key_exists($field, $model->getAttributes()) && ! $model->hasGetMutator($field) && ! $model->hasAttributeMutator($field)) {
            return null;
        }

        try {
            return $model->getAttribute($field);
        } catch (Throwable) {
            return null;
        }
    }

    /** Date-times are shown in the clinic's time zone, like on screen. */
    private static function clinicTime(CarbonImmutable $value): string
    {
        return app(ClinicClock::class)->toClinic($value)->format(self::DATE_TIME);
    }

    /**
     * A related record → its name; a list → comma separated; anything else (e.g. audit changes) → JSON.
     *
     * @param  array<mixed>  $value
     */
    private static function related(array $value): string
    {
        foreach (['full_name', 'display_name', 'name', 'label', 'code'] as $key) {
            if (isset($value[$key]) && is_scalar($value[$key])) {
                return (string) $value[$key];
            }
        }

        if (array_is_list($value) && array_filter($value, is_scalar(...)) === $value) {
            return implode(', ', array_map(strval(...), $value));
        }

        return $value === [] ? '' : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function text(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'Yes' : 'No',
            $value instanceof HasLabel => $value->label(),
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof DateTimeInterface => self::clinicTime(CarbonImmutable::instance($value)),
            is_array($value) => self::related($value),
            is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $value) === 1 => self::clinicTime(CarbonImmutable::parse($value)),
            is_scalar($value) => (string) $value,
            default => '',
        };
    }
}
