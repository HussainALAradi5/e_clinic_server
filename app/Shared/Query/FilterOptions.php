<?php

declare(strict_types=1);

namespace App\Shared\Query;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Locale;

/**
 * Ready-made choice lists for filters on code columns (country, language, file type …), so people pick a name
 * instead of typing a code. Each returns a closure — the list is only built when a client asks for the schema.
 */
final class FilterOptions
{
    /** Language names used when the intl extension is not installed (`Locale` missing). */
    private const LANGUAGES = [
        'ar' => 'Arabic', 'bn' => 'Bangla', 'de' => 'German', 'en' => 'English', 'es' => 'Spanish', 'fa' => 'Persian',
        'fil' => 'Filipino', 'fr' => 'French', 'hi' => 'Hindi', 'it' => 'Italian', 'ml' => 'Malayalam', 'ne' => 'Nepali',
        'ru' => 'Russian', 'si' => 'Sinhala', 'ta' => 'Tamil', 'tl' => 'Tagalog', 'tr' => 'Turkish', 'ur' => 'Urdu',
        'zh' => 'Chinese',
    ];

    /**
     * The values in use in a column, labelled by `$labels` (codes → names); unlabelled values show as they are.
     *
     * @param  class-string<Model>  $model
     * @param  (Closure(list<string>): array<string, string>)|null  $labels
     * @return Closure(): array<string, string>
     */
    public static function inUse(string $model, string $column, ?Closure $labels = null): Closure
    {
        return static function () use ($model, $column, $labels): array {
            /** @var list<string> $values */
            $values = $model::query()->withoutGlobalScopes()->whereNotNull($column)
                ->distinct()->orderBy($column)->pluck($column)->map(fn (mixed $v): string => (string) $v)->all();
            $names = $labels !== null ? $labels($values) : [];

            return self::sorted(array_combine($values, array_map(fn (string $v): string => $names[$v] ?? $v, $values)));
        };
    }

    /**
     * Language codes in use (`ar` → Arabic).
     *
     * @param  class-string<Model>  $model
     * @return Closure(): array<string, string>
     */
    public static function languages(string $model, string $column = 'language'): Closure
    {
        return self::inUse($model, $column, fn (array $codes): array => array_combine(
            $codes,
            array_map(self::language(...), $codes),
        ));
    }

    /** `ar` → Arabic; the code itself when it is not known. */
    public static function language(string $code): string
    {
        if (class_exists(Locale::class)) {
            return Locale::getDisplayLanguage($code, 'en') ?: $code;
        }

        return self::LANGUAGES[strtolower($code)] ?? $code;
    }

    /**
     * A fixed list of morph aliases or keys in words (`medical_record` → Medical record).
     *
     * @param  list<string>  $keys
     * @return Closure(): array<string, string>
     */
    public static function words(array $keys): Closure
    {
        return static fn (): array => self::sorted(self::inWords()($keys));
    }

    /**
     * Labeller for `inUse`: keys or class names in words (`clinical_order`, `…\\ClinicalOrder` → Clinical order).
     *
     * @return Closure(list<string>): array<string, string>
     */
    public static function inWords(): Closure
    {
        return static fn (array $keys): array => array_combine($keys, array_map(
            fn (string $key): string => Str::ucfirst(str_replace('_', ' ', Str::snake(class_basename($key)))),
            $keys,
        ));
    }

    /**
     * MIME types in plain words (`application/pdf` → PDF document, `image/png` → PNG image).
     *
     * @param  list<string>  $mimes
     * @return Closure(): array<string, string>
     */
    public static function fileTypes(array $mimes): Closure
    {
        return static fn (): array => self::sorted(array_combine($mimes, array_map(self::fileType(...), $mimes)));
    }

    public static function fileType(string $mime): string
    {
        [$kind, $sub] = array_pad(explode('/', $mime, 2), 2, '');

        return match (true) {
            $mime === 'application/pdf' => 'PDF document',
            $mime === 'application/dicom' => 'DICOM scan',
            $mime === 'application/octet-stream' => 'Other file',
            str_contains($sub, 'wordprocessing') || $sub === 'msword' => 'Word document',
            str_contains($sub, 'spreadsheet') || $sub === 'vnd.ms-excel' => 'Excel sheet',
            $mime === 'text/csv' => 'CSV file',
            $kind === 'image' => strtoupper($sub === 'jpeg' ? 'jpeg' : $sub).' image',
            $kind === 'text' => 'Text file',
            default => strtoupper($sub),
        };
    }

    /**
     * @param  array<string, string>  $options
     * @return array<string, string>
     */
    private static function sorted(array $options): array
    {
        asort($options, SORT_NATURAL | SORT_FLAG_CASE);

        return $options;
    }
}
