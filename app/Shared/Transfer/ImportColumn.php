<?php

declare(strict_types=1);

namespace App\Shared\Transfer;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * One column of an import template: its header, the input field it fills, whether it is required,
 * an example value and the help text written on the Instructions sheet.
 */
final readonly class ImportColumn
{
    /** Excel stores dates as days since 1899-12-30. */
    private const EXCEL_EPOCH = '1899-12-30';

    /**
     * @param  array<string, string>  $options  value => label (Choice columns)
     */
    public function __construct(
        public string $field,
        public string $header,
        public bool $required = false,
        public string $example = '',
        public string $help = '',
        public ImportColumnType $type = ImportColumnType::Text,
        public array $options = [],
    ) {}

    /** Cell text → input value ('' → null). */
    public function read(string $cell): ?string
    {
        $cell = trim($cell);
        if ($cell === '') {
            return null;
        }

        return match ($this->type) {
            ImportColumnType::Date => $this->date($cell),
            ImportColumnType::Choice => $this->choice($cell),
            ImportColumnType::Country => strtoupper($cell),
            ImportColumnType::Text => $cell,
        };
    }

    /** Format line on the Instructions sheet. */
    public function format(): string
    {
        return match ($this->type) {
            ImportColumnType::Date => 'Date as YYYY-MM-DD (or an Excel date)',
            ImportColumnType::Choice => 'One of: '.implode(', ', $this->options),
            ImportColumnType::Country => '2-letter country code, e.g. BH, SA, IN',
            ImportColumnType::Text => 'Text',
        };
    }

    private function date(string $cell): string
    {
        try {
            return is_numeric($cell)
                ? CarbonImmutable::parse(self::EXCEL_EPOCH)->addDays((int) $cell)->toDateString()
                : CarbonImmutable::parse($cell)->toDateString();
        } catch (Throwable) {
            return $cell; // left for the validator to reject
        }
    }

    private function choice(string $cell): string
    {
        foreach ($this->options as $value => $label) {
            if (strcasecmp($cell, (string) $value) === 0 || strcasecmp($cell, $label) === 0) {
                return (string) $value;
            }
        }

        return $cell;
    }
}
