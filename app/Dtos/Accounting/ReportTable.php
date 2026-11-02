<?php

declare(strict_types=1);

namespace App\Dtos\Accounting;

use App\Enums\Accounting\ReportType;

/**
 * One generic shape for every financial report: typed columns, rows, totals and summary figures. The API returns it
 * as JSON (one client table renders every report) and the export writes it to Excel / PDF.
 *
 * Rows carry their cells keyed by column, plus `_kind` (`line` | `group` | `total`) and an optional `_link`
 * (`{type: 'journal' | 'account' | 'patient' | 'doctor', id}`) for click-through. Money cells are 3-decimal strings.
 */
final readonly class ReportTable
{
    /**
     * @param  list<array{key: string, label: string, type: string}>  $columns  type: text | money | date | number
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, string>  $totals  column key => value of the grand-total row
     * @param  list<array{label: string, value: string, type: string}>  $summary  headline figures (opening / closing / net …)
     */
    public function __construct(
        public ReportType $type,
        public string $title,
        public string $subtitle,
        public array $columns,
        public array $rows,
        public array $totals = [],
        public array $summary = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'columns' => $this->columns,
            'rows' => $this->rows,
            'totals' => (object) $this->totals,
            'summary' => $this->summary,
        ];
    }

    /** @return list<string> */
    public function headers(): array
    {
        return array_column($this->columns, 'label');
    }

    /**
     * Rows as plain cells for files (group rows keep their label, totals follow the rows).
     *
     * @return list<list<string>>
     */
    public function cells(): array
    {
        $keys = array_column($this->columns, 'key');
        $line = fn (array $row): array => array_map(fn (string $key): string => (string) ($row[$key] ?? ''), $keys);
        $cells = array_map($line, $this->rows);
        if ($this->totals !== []) {
            $cells[] = $line([$keys[0] => __('accounting.reports.total'), ...$this->totals]);
        }
        foreach ($this->summary as $figure) {
            $cells[] = [$figure['label'], $figure['value']];
        }

        return $cells;
    }
}
